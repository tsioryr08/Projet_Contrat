<?php

namespace App\Services;

use App\Models\ContratHistoriqueModel;
use App\Models\ContratModel;
use DateInterval;
use DateTimeImmutable;
use RuntimeException;

class ContratService
{
    public const STATUTS = [
        'en_preparation'    => 'En préparation',
        'en_validation'     => 'En validation',
        'a_signer'          => 'À signer',
        'en_execution'      => 'En exécution',
        'en_renouvellement' => 'En renouvellement',
        'cloture'           => 'Clôturé',
    ];

    public const STATUT_CLASSES = [
        'en_preparation'    => 'bg-secondary',
        'en_validation'     => 'bg-info text-dark',
        'a_signer'          => 'bg-primary',
        'en_execution'      => 'bg-success',
        'en_renouvellement' => 'bg-warning text-dark',
        'cloture'           => 'bg-dark',
    ];

    /**
     * Transitions autorisées. Question ouverte (retours en arrière) :
     * les retours "en_validation -> en_preparation" et "a_signer -> en_validation"
     * sont autorisés ici ; supprimez-les si le prof les interdit.
     */
    public const TRANSITIONS = [
        'en_preparation'    => ['en_validation', 'cloture'],
        'en_validation'     => ['a_signer', 'en_preparation', 'cloture'],
        'a_signer'          => ['en_execution', 'en_validation', 'cloture'],
        'en_execution'      => ['en_renouvellement', 'cloture'],
        'en_renouvellement' => ['cloture'],
        'cloture'           => [],
    ];

    public const MOTIFS = [
        'arrive_a_terme' => 'Arrivé à terme',
        'non_reconduit'  => 'Non reconduit',
        'resilie'        => 'Résilié',
        'annule'         => 'Annulé',
    ];

    public const NIVEAUX = [
        'ok'         => ['label' => 'OK',          'classe' => 'bg-success'],
        'a_examiner' => ['label' => 'À examiner',  'classe' => 'bg-warning text-dark'],
        'urgent'     => ['label' => 'Urgent',      'classe' => 'bg-danger'],
        'depasse'    => ['label' => 'Dépassé',     'classe' => 'bg-secondary'],
    ];

    private $db;
    private ContratModel $contrats;
    private ContratHistoriqueModel $historique;

    public function __construct()
    {
        $this->db         = db_connect();
        $this->contrats   = new ContratModel();
        $this->historique = new ContratHistoriqueModel();
    }

    /** Niveau d'échéance d'une date limite (null si pas de date). */
    public function niveau(?string $dateLimite): ?array
    {
        if (! $dateLimite) {
            return null;
        }
        $auj   = new DateTimeImmutable('today');
        $jours = (int) $auj->diff(new DateTimeImmutable($dateLimite))->format('%r%a');

        $code = $jours < 0 ? 'depasse' : ($jours <= 10 ? 'urgent' : ($jours <= 30 ? 'a_examiner' : 'ok'));

        return self::NIVEAUX[$code] + ['code' => $code, 'jours' => $jours];
    }

    public function transitionsPossibles(string $statut): array
    {
        return self::TRANSITIONS[$statut] ?? [];
    }

    /** Référence CTR-AAAA-NNNN avec compteur annuel. */
    public function genererReference(?int $annee = null): string
    {
        $annee  ??= (int) date('Y');
        $prefixe = "CTR-{$annee}-";

        $derniere = $this->db->table('contrats')
            ->select('reference')
            ->like('reference', $prefixe, 'after')
            ->orderBy('reference', 'DESC')
            ->limit(1)
            ->get()->getRowArray();

        $n = $derniere ? ((int) substr($derniere['reference'], strlen($prefixe))) + 1 : 1;

        return $prefixe . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    /** Règles métier ; retourne la liste des erreurs (vide si tout est bon). */
    public function verifierRegles(array $d, ?array $existant = null): array
    {
        $erreurs = [];

        if ($d['date_fin'] < $d['date_debut']) {
            $erreurs[] = 'La date de fin doit être postérieure ou égale à la date de début.';
        }

        if ($existant === null || (int) $existant['fournisseur_id'] !== (int) $d['fournisseur_id']) {
            $f = $this->db->table('fournisseurs')->where('id', (int) $d['fournisseur_id'])->get()->getRowArray();
            if (! $f || $f['statut'] !== 'actif') {
                $erreurs[] = "Le fournisseur choisi n'est pas actif (statut : " . ($f['statut'] ?? 'inconnu') . ').';
            }
        }

        return $erreurs;
    }

    /** Crée le contrat et sa première ligne d'historique. Retourne l'id. */
    public function creer(array $d, int $userId): int
    {
        $d = $this->completer($d);
        $d['reference']     = $this->genererReference();
        $d['statut']        = 'en_preparation';
        $d['created_by']    = $userId;
        $d['source_saisie'] = $d['source_saisie'] ?? 'manuelle';

        $this->db->transStart();
        $id = (int) $this->contrats->insert($d, true);
        $this->historique->insert([
            'contrat_id'     => $id,
            'statut_avant'   => null,
            'statut_apres'   => 'en_preparation',
            'acteur'         => 'humain',
            'utilisateur_id' => $userId,
            'commentaire'    => 'Création du contrat',
        ]);
        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            throw new RuntimeException("La création du contrat a échoué.");
        }
        return $id;
    }

    public function modifier(int $id, array $d): void
    {
        $this->contrats->update($id, $this->completer($d));
    }

    /** Change le statut ; retourne le statut précédent. */
    public function changerStatut(int $id, string $nouveau, ?string $motif, ?string $commentaire, int $userId): string
    {
        $c = $this->contrats->find($id);
        if (! $c) {
            throw new RuntimeException('Contrat introuvable.');
        }
        if (! in_array($nouveau, self::TRANSITIONS[$c['statut']] ?? [], true)) {
            throw new RuntimeException(sprintf(
                'Transition interdite : %s vers %s.',
                self::STATUTS[$c['statut']] ?? $c['statut'],
                self::STATUTS[$nouveau] ?? $nouveau
            ));
        }
        if ($nouveau === 'cloture' && ! isset(self::MOTIFS[$motif ?? ''])) {
            throw new RuntimeException('Le motif de clôture est obligatoire.');
        }

        $maj = ['statut' => $nouveau, 'motif_cloture' => $nouveau === 'cloture' ? $motif : null];
        if ($nouveau === 'en_execution' && empty($c['date_signature'])) {
            $maj['date_signature'] = date('Y-m-d'); // signature constatée au passage en exécution
        }

        $texte = trim((string) $commentaire);
        if ($nouveau === 'cloture') {
            $texte = trim('Motif : ' . self::MOTIFS[$motif] . ($texte !== '' ? ' - ' . $texte : ''));
        }

        $this->db->transStart();
        $this->contrats->update($id, $maj);
        $this->historique->insert([
            'contrat_id'     => $id,
            'statut_avant'   => $c['statut'],
            'statut_apres'   => $nouveau,
            'acteur'         => 'humain',
            'utilisateur_id' => $userId,
            'commentaire'    => $texte !== '' ? $texte : null,
        ]);
        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            throw new RuntimeException('Le changement de statut a échoué.');
        }
        return $c['statut'];
    }

    public function peutRenouveler(array $c): bool
    {
        if (! in_array($c['statut'], ['en_execution', 'en_renouvellement'], true)) {
            return false;
        }
        if ($c['nb_reconductions_max'] !== null
            && (int) $c['nb_reconductions_effectuees'] >= (int) $c['nb_reconductions_max']) {
            return false;
        }
        return ! $this->db->table('contrats')->where('contrat_parent_id', (int) $c['id'])->countAllResults();
    }

    /**
     * Renouvelle : crée un contrat lié (en exécution) et clôture l'ancien
     * (motif arrive_a_terme). Retourne [id, reference] du nouveau contrat.
     */
    public function renouveler(int $id, int $userId): array
    {
        $c = $this->contrats->find($id);
        if (! $c) {
            throw new RuntimeException('Contrat introuvable.');
        }
        if (! in_array($c['statut'], ['en_execution', 'en_renouvellement'], true)) {
            throw new RuntimeException("Seul un contrat en exécution ou en renouvellement peut être renouvelé.");
        }
        if ($c['nb_reconductions_max'] !== null
            && (int) $c['nb_reconductions_effectuees'] >= (int) $c['nb_reconductions_max']) {
            throw new RuntimeException('Le nombre maximal de reconductions est atteint.');
        }
        if ($this->db->table('contrats')->where('contrat_parent_id', $id)->countAllResults()) {
            throw new RuntimeException('Ce contrat a déjà été renouvelé.');
        }

        $duree = (int) ($c['duree_reconduction_mois'] ?: $c['duree_mois']);
        if ($duree <= 0) {
            throw new RuntimeException('Renseignez la durée de reconduction (ou la durée) avant de renouveler.');
        }

        $debut = (new DateTimeImmutable($c['date_fin']))->add(new DateInterval('P1D'));
        $fin   = $debut->add(new DateInterval("P{$duree}M"))->sub(new DateInterval('P1D'));

        $nouveau = $c;
        unset($nouveau['id'], $nouveau['date_limite_non_reconduction'],
              $nouveau['created_at'], $nouveau['updated_at'], $nouveau['score_risque']);
        $nouveau['reference']                   = $this->genererReference();
        $nouveau['statut']                      = 'en_execution';
        $nouveau['motif_cloture']               = null;
        $nouveau['en_litige']                   = 0;
        $nouveau['date_signature']              = null;
        $nouveau['date_debut']                  = $debut->format('Y-m-d');
        $nouveau['date_fin']                    = $fin->format('Y-m-d');
        $nouveau['duree_mois']                  = $duree;
        $nouveau['nb_reconductions_effectuees'] = (int) $c['nb_reconductions_effectuees'] + 1;
        $nouveau['contrat_parent_id']           = $id;
        $nouveau['source_saisie']               = 'manuelle';
        $nouveau['created_by']                  = $userId;

        $this->db->transStart();
        $nid = (int) $this->contrats->insert($nouveau, true);
        $this->historique->insert([
            'contrat_id' => $nid, 'statut_avant' => null, 'statut_apres' => 'en_execution',
            'acteur' => 'humain', 'utilisateur_id' => $userId,
            'commentaire' => 'Renouvellement du contrat ' . $c['reference'],
        ]);
        $this->contrats->update($id, ['statut' => 'cloture', 'motif_cloture' => 'arrive_a_terme']);
        $this->historique->insert([
            'contrat_id' => $id, 'statut_avant' => $c['statut'], 'statut_apres' => 'cloture',
            'acteur' => 'humain', 'utilisateur_id' => $userId,
            'commentaire' => 'Renouvelé par ' . $nouveau['reference'],
        ]);
        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            throw new RuntimeException('Le renouvellement a échoué.');
        }
        return [$nid, $nouveau['reference']];
    }

    /** Calcule la durée en mois si elle n'est pas saisie. */
    private function completer(array $d): array
    {
        if (empty($d['duree_mois']) && ! empty($d['date_debut']) && ! empty($d['date_fin'])) {
            $diff = (new DateTimeImmutable($d['date_debut']))->diff(
                (new DateTimeImmutable($d['date_fin']))->add(new DateInterval('P1D'))
            );
            $d['duree_mois'] = max(1, $diff->y * 12 + $diff->m + ($diff->d >= 15 ? 1 : 0));
        }
        return $d;
    }
}