<?php

namespace App\Services;

use App\Models\LitigeHistoriqueModel;
use App\Models\LitigeModel;
use App\Models\LitigeTransitionModel;
use RuntimeException;

class LitigeService
{
    public const ETAT_FINAL = 'archivage';

    public const TYPES = [
        'retard_livraison'       => 'Retard de livraison',
        'non_conformite'         => 'Non-conformité',
        'penalite_contestee'     => 'Pénalité contestée',
        'facturation'            => 'Facturation',
        'resiliation_contestee'  => 'Résiliation contestée',
        'reconduction_contestee' => 'Reconduction contestée',
        'autre'                  => 'Autre',
    ];

    public const ISSUES = [
        'resolu_contrat_maintenu' => 'Résolu, contrat maintenu',
        'resolu_resiliation'      => 'Résolu, résiliation',
        'classe_sans_suite'       => 'Classé sans suite',
    ];

    private $db;
    private LitigeModel $litiges;

    public function __construct()
    {
        $this->db      = db_connect();
        $this->litiges = new LitigeModel();
    }

    /** code => libellé, dans l'ordre du workflow. */
    public function etats(): array
    {
        $b = $this->db->table('litige_etats');
        if ($this->db->fieldExists('ordre', 'litige_etats')) {
            $b->orderBy('ordre');
        }
        $out = [];
        foreach ($b->get()->getResultArray() as $r) {
            $out[$r['code']] = $r['libelle'] ?? $r['nom'] ?? $r['code'];
        }
        return $out;
    }

    /** Etats cibles autorisés depuis $etat pour ce rôle (lus dans litige_transitions). */
    public function transitionsPour(string $etat, ?string $role): array
    {
        $m   = new LitigeTransitionModel();
        $src = $m->colonne('etat_source');
        $cib = $m->colonne('etat_cible');
        $rol = $m->colonne('role_requis');
        if (! $src || ! $cib) {
            throw new RuntimeException("Colonnes 'état source' / 'état cible' introuvables dans litige_transitions.");
        }

        $res = [];
        foreach ($m->findAll() as $t) {
            if ($t[$src] !== $etat) {
                continue;
            }
            $requis = $rol ? trim((string) $t[$rol]) : '';
            if ($requis !== '' && $role !== 'admin') {
                $roles = array_map('trim', preg_split('/[,;|]/', $requis));
                if (! in_array($role, $roles, true)) {
                    continue;
                }
            }
            $res[] = $t[$cib];
        }
        return array_values(array_unique($res));
    }

    public function genererReference(): string
    {
        $prefixe = 'LIT-' . date('Y') . '-';
        $der = $this->db->table('litiges')->select('reference')
            ->like('reference', $prefixe, 'after')->orderBy('reference', 'DESC')->limit(1)
            ->get()->getRowArray();
        $n = $der ? ((int) substr($der['reference'], strlen($prefixe))) + 1 : 1;
        return $prefixe . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    public function creer(array $d, int $userId): int
    {
        $d['reference']      = $this->genererReference();
        $d['etat_code']      = 'signalement';
        $d['issue']          = 'en_cours';
        $d['date_ouverture'] = date('Y-m-d');
        $d['created_by']     = $userId;

        $this->db->transStart();
        $id = (int) $this->litiges->insert($d, true);
        $this->historiser($id, null, 'signalement', $userId, 'Signalement du litige');
        $this->synchroniserContrat((int) $d['contrat_id']);
        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            throw new RuntimeException('La création du litige a échoué.');
        }
        return $id;
    }

    /**
     * Code d'exception : 403 transition interdite, 422 donnée manquante, 404 introuvable.
     * Retourne ['avant' => etat, 'contrat_clos_depuis' => ?statut, 'issue' => ?issue].
     */
    public function transitionner(int $id, string $cible, string $commentaire, ?string $issue,
                                  bool $cloturerContrat, ?string $role, int $userId): array
    {
        $l = $this->litiges->find($id);
        if (! $l) {
            throw new RuntimeException('Litige introuvable.', 404);
        }

        if (! in_array($cible, $this->transitionsPour($l['etat_code'], $role), true)) {
            $e = $this->etats();
            throw new RuntimeException(sprintf(
                "Transition interdite : de « %s » vers « %s » (non prévue ou non autorisée pour votre rôle).",
                $e[$l['etat_code']] ?? $l['etat_code'], $e[$cible] ?? $cible
            ), 403);
        }

        $commentaire = trim($commentaire);
        if ($commentaire === '') {
            throw new RuntimeException('Le commentaire est obligatoire pour une transition.', 422);
        }

        $maj = ['etat_code' => $cible];
        if ($cible === 'resolution') {
            if (! isset(self::ISSUES[$issue ?? ''])) {
                throw new RuntimeException("L'issue du litige est obligatoire à l'état « résolution ».", 422);
            }
            $maj['issue'] = $issue;
        }
        if ($cible === self::ETAT_FINAL) {
            $maj['date_cloture'] = date('Y-m-d');
        }

        $this->db->transStart();
        $this->litiges->update($id, $maj);
        $this->historiser($id, $l['etat_code'], $cible, $userId, $commentaire);
        $this->synchroniserContrat((int) $l['contrat_id']);
        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            throw new RuntimeException('La transition a échoué.');
        }

        $clos = null;
        if ($cible === 'resolution' && $issue === 'resolu_resiliation' && $cloturerContrat) {
            $c = $this->db->table('contrats')->select('statut')->where('id', $l['contrat_id'])->get()->getRowArray();
            if ($c && $c['statut'] !== 'cloture') {
                try {
                    (new ContratService())->changerStatut(
                        (int) $l['contrat_id'], 'cloture', 'resilie',
                        'Résiliation suite au litige ' . $l['reference'], $userId
                    );
                    $clos = $c['statut'];
                } catch (RuntimeException $e) {
                    $clos = null;
                }
            }
        }

        return ['avant' => $l['etat_code'], 'contrat_clos_depuis' => $clos, 'issue' => $maj['issue'] ?? null];
    }

    /** en_litige = 1 tant qu'un litige du contrat n'est pas archivé. */
    public function synchroniserContrat(int $contratId): void
    {
        $n = $this->db->table('litiges')->where('contrat_id', $contratId)
            ->where('etat_code !=', self::ETAT_FINAL)->countAllResults();
        $this->db->table('contrats')->where('id', $contratId)->update(['en_litige' => $n > 0 ? 1 : 0]);
    }

    private function historiser(int $litigeId, ?string $avant, string $apres, int $userId, ?string $commentaire): void
    {
        (new LitigeHistoriqueModel())->creer([
            'litige_id'      => $litigeId,
            'etat_avant'     => $avant,
            'etat_apres'     => $apres,
            'utilisateur_id' => $userId,
            'commentaire'    => $commentaire,
        ]);
    }
}