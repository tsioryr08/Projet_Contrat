<?php

namespace App\Controllers;

use App\Models\LitigeHistoriqueModel;
use App\Models\LitigeModel;
use App\Services\LitigeService;
use CodeIgniter\Exceptions\PageNotFoundException;
use RuntimeException;

class Litiges extends BaseController
{
    private LitigeModel $litiges;
    private LitigeService $service;

    public function __construct()
    {
        $this->litiges = new LitigeModel();
        $this->service = new LitigeService();
    }

    public function index()
    {
        $f = [
            'etat'           => (string) $this->request->getGet('etat'),
            'type'           => (string) $this->request->getGet('type'),
            'responsable_id' => (string) $this->request->getGet('responsable_id'),
        ];

        $this->litiges
            ->select("litiges.*, contrats.reference AS contrat_reference, CONCAT_WS(' ', u.prenom, u.nom) AS responsable_nom", false)
            ->join('contrats', 'contrats.id = litiges.contrat_id')
            ->join('utilisateurs u', 'u.id = litiges.responsable_id', 'left');
        if ($f['etat'] !== '')           $this->litiges->where('litiges.etat_code', $f['etat']);
        if ($f['type'] !== '')           $this->litiges->where('litiges.type_litige', $f['type']);
        if ($f['responsable_id'] !== '') $this->litiges->where('litiges.responsable_id', (int) $f['responsable_id']);

        $lignes = $this->litiges->orderBy('litiges.id', 'DESC')->paginate(15);

        return view('litiges/index', [
            'titre'        => 'Litiges',
            'litiges'      => $lignes,
            'pager'        => $this->litiges->pager,
            'filtres'      => $f,
            'etats'        => $this->service->etats(),
            'types'        => LitigeService::TYPES,
            'responsables' => $this->responsables(),
            'peutCreer'    => $this->peutCreer(),
        ]);
    }

    public function voir(int $id)
    {
        $db = db_connect();
        $l  = $db->table('litiges l')
            ->select("l.*, c.reference AS contrat_reference, c.intitule AS contrat_intitule, c.statut AS contrat_statut,
                      CONCAT_WS(' ', u.prenom, u.nom) AS responsable_nom", false)
            ->join('contrats c', 'c.id = l.contrat_id')
            ->join('utilisateurs u', 'u.id = l.responsable_id', 'left')
            ->where('l.id', $id)->get()->getRowArray();
        if (! $l) {
            throw PageNotFoundException::forPageNotFound('Litige introuvable.');
        }

        $hm    = new LitigeHistoriqueModel();
        $cl    = $hm->colonne('litige_id');
        $rows  = $cl ? $hm->where($cl, $id)->orderBy('id', 'DESC')->findAll() : [];
        $users = array_column(
            $db->table('utilisateurs')->select("id, CONCAT_WS(' ', prenom, nom) AS n", false)->get()->getResultArray(), 'n', 'id'
        );
        $hist = array_map(fn($r) => [
            'date'        => $hm->lire($r, 'date_changement'),
            'avant'       => $hm->lire($r, 'etat_avant'),
            'apres'       => $hm->lire($r, 'etat_apres'),
            'utilisateur' => $users[$hm->lire($r, 'utilisateur_id')] ?? '—',
            'commentaire' => $hm->lire($r, 'commentaire'),
        ], $rows);

        $role  = session()->get('role');
        $agit  = in_array($role, ['admin', 'juriste', 'responsable', 'direction'], true);

        return view('litiges/fiche', [
            'titre'       => 'Litige ' . $l['reference'],
            'l'           => $l,
            'etats'       => $this->service->etats(),
            'types'       => LitigeService::TYPES,
            'issues'      => LitigeService::ISSUES,
            'historique'  => $hist,
            'transitions' => $agit ? $this->service->transitionsPour($l['etat_code'], $role) : [],
        ]);
    }

    public function nouveau()
    {
        $db = db_connect();
        return view('litiges/form', [
            'titre'        => 'Nouveau litige',
            'contratId'    => (int) $this->request->getGet('contrat_id'),
            'contrats'     => $db->table('contrats')->select('id, reference, intitule')->orderBy('id', 'DESC')->get()->getResultArray(),
            'types'        => LitigeService::TYPES,
            'responsables' => $this->responsables(),
        ]);
    }

    public function enregistrer()
    {
        $regles = [
            'contrat_id'           => 'required|is_natural_no_zero|is_not_unique[contrats.id]',
            'type_litige'          => 'required|in_list[' . implode(',', array_keys(LitigeService::TYPES)) . ']',
            'description'          => 'required',
            'montant_enjeu'        => 'permit_empty|decimal|greater_than_equal_to[0]',
            'responsable_id'       => 'permit_empty|is_not_unique[utilisateurs.id]',
            'date_echeance_action' => 'permit_empty|valid_date[Y-m-d]',
        ];
        $messages = [
            'contrat_id'    => ['required' => 'Le contrat est obligatoire.', 'is_not_unique' => 'Contrat inconnu.'],
            'type_litige'   => ['required' => 'Le type de litige est obligatoire.', 'in_list' => 'Type de litige invalide.'],
            'description'   => ['required' => 'La description est obligatoire.'],
            'montant_enjeu' => ['decimal' => 'Le montant en jeu est invalide.', 'greater_than_equal_to' => 'Le montant en jeu ne peut pas être négatif.'],
            'responsable_id'       => ['is_not_unique' => 'Responsable inconnu.'],
            'date_echeance_action' => ['valid_date' => "La date d'échéance d'action est invalide."],
        ];
        if (! $this->validate($regles, $messages)) {
            return redirect()->back()->withInput()->with('erreurs', $this->validator->getErrors());
        }

        $p = $this->request->getPost();
        $nul = fn($k) => (isset($p[$k]) && trim((string) $p[$k]) !== '') ? trim((string) $p[$k]) : null;
        $data = [
            'contrat_id'           => (int) $p['contrat_id'],
            'type_litige'          => $p['type_litige'],
            'fondement'            => $nul('fondement'),
            'description'          => trim((string) $p['description']),
            'montant_enjeu'        => $nul('montant_enjeu'),
            'responsable_id'       => $nul('responsable_id') ? (int) $p['responsable_id'] : null,
            'date_echeance_action' => $nul('date_echeance_action'),
        ];

        try {
            $id = $this->service->creer($data, (int) session()->get('utilisateur_id'));
        } catch (RuntimeException $e) {
            return redirect()->back()->withInput()->with('erreurs', [$e->getMessage()]);
        }

        audit_log('creation', 'litiges', $id, null, $data);
        return redirect()->to('litiges/voir/' . $id)->with('succes', 'Litige créé.');
    }

    public function transition(int $id)
    {
        $cible = (string) $this->request->getPost('etat_cible');
        $uid   = (int) session()->get('utilisateur_id');

        try {
            $r = $this->service->transitionner(
                $id, $cible,
                (string) $this->request->getPost('commentaire'),
                $this->request->getPost('issue') ?: null,
                (bool) $this->request->getPost('cloturer_contrat'),
                session()->get('role'), $uid
            );
        } catch (RuntimeException $e) {
            if ($e->getCode() === 403) {
                return $this->response->setStatusCode(403)->setBody(
                    '<!doctype html><meta charset="utf-8"><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">'
                    . '<div class="container py-5"><div class="alert alert-danger">' . esc($e->getMessage()) . '</div>'
                    . '<a class="btn btn-primary" href="' . base_url('litiges/voir/' . $id) . '">Retour au litige</a></div>'
                );
            }
            return redirect()->to('litiges/voir/' . $id)->with('erreur', $e->getMessage());
        }

        audit_log('transition', 'litiges', $id,
            ['etat_code' => $r['avant']],
            ['etat_code' => $cible, 'issue' => $r['issue'], 'commentaire' => trim((string) $this->request->getPost('commentaire'))]
        );

        $msg = 'Transition effectuée.';
        if ($r['contrat_clos_depuis']) {
            $l = $this->litiges->find($id);
            audit_log('transition', 'contrats', (int) $l['contrat_id'],
                ['statut' => $r['contrat_clos_depuis']], ['statut' => 'cloture', 'motif_cloture' => 'resilie']);
            $msg .= ' Le contrat a été clôturé (motif : résilié).';
        }
        return redirect()->to('litiges/voir/' . $id)->with('succes', $msg);
    }

    private function peutCreer(): bool
    {
        return in_array(session()->get('role'), ['admin', 'juriste', 'responsable'], true);
    }

    private function responsables(): array
    {
        return db_connect()->table('utilisateurs')->select("id, CONCAT_WS(' ', prenom, nom) AS nom", false)
            ->where('actif', 1)->orderBy('nom')->get()->getResultArray();
    }
}