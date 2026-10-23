<?php

namespace App\Controllers;

use App\Models\AuditModel;

/** Consultation du journal d'audit, en lecture seule. */
class Audit extends BaseController
{
    protected $helpers = ['audit', 'ui'];

    public function index()
    {
        $filtres = [
            'utilisateur_id' => $this->request->getGet('utilisateur_id'),
            'entite'         => $this->request->getGet('entite'),
            'action'         => $this->request->getGet('action'),
            'du'             => $this->request->getGet('du'),
            'au'             => $this->request->getGet('au'),
        ];

        $model  = new AuditModel();
        $lignes = $model->filtrer($filtres)->paginate(25);

        $db = db_connect();

        return view('audit/index', [
            'titre'        => "Journal d'audit",
            'lignes'       => $lignes,
            'pager'        => $model->pager,
            'filtres'      => $filtres,
            'utilisateurs' => $db->table('utilisateurs')
                                  ->select("id, TRIM(CONCAT_WS(' ', prenom, nom)) AS nom_complet", false)
                                  ->orderBy('nom')->get()->getResultArray(),
            'entites'      => array_column($db->query('SELECT DISTINCT entite FROM journal_audit ORDER BY entite')->getResultArray(), 'entite'),
            'actions'      => array_column($db->query('SELECT DISTINCT action FROM journal_audit ORDER BY action')->getResultArray(), 'action'),
        ]);
    }
}
