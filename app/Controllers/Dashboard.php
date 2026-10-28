<?php

namespace App\Controllers;

use App\Services\ContratService;
use App\Services\DashboardService;

class Dashboard extends BaseController
{
    private DashboardService $service;

    public function __construct()
    {
        $this->service = new DashboardService();
    }

    public function index()
    {
        $f  = $this->filtres();
        $db = db_connect();
        $cs = new ContratService();

        $prochaines = $this->service->prochaines($f);
        foreach ($prochaines as &$p) {
            $p['niveau'] = $cs->niveau($p['date_limite_non_reconduction']);
        }
        unset($p);

        // Contrats de la liste ayant au moins une de mes alertes non traitée
        $aTraiter = [];
        $ids      = array_column($prochaines, 'id');
        if ($ids) {
            $rows = $db->table('alertes')->select('contrat_id')
                ->where('destinataire_id', (int) session()->get('utilisateur_id'))
                ->where('canal', 'application')
                ->whereIn('statut', ['envoyee', 'lue'])
                ->whereIn('contrat_id', $ids)
                ->groupBy('contrat_id')->get()->getResultArray();
            $aTraiter = array_map('intval', array_column($rows, 'contrat_id'));
        }

        return view('dashboard/index', [
            'titre'        => 'Tableau de bord',
            'f'            => $f,
            'indic'        => $this->service->indicateurs($f),
            'prochaines'   => $prochaines,
            'aTraiter'     => $aTraiter,
            'niveauxDef'   => ContratService::NIVEAUX,
            'services'     => $db->table('services')->orderBy('nom')->get()->getResultArray(),
            'responsables' => $db->table('utilisateurs')->select("id, CONCAT_WS(' ', prenom, nom) AS nom", false)->where('actif', 1)->orderBy('nom')->get()->getResultArray(),
            'verrouille'   => session()->get('role') === 'responsable',
            'peutLitige'   => in_array(session()->get('role'), ['admin', 'juriste', 'responsable'], true),
        ]);
    }

    public function echeancesJson()
    {
        return $this->response->setJSON($this->service->echeancesMensuelles($this->filtres()));
    }

    /** Un responsable ne voit que ses propres contrats. */
    private function filtres(): array
    {
        $f = [
            'service_id'     => (string) $this->request->getGet('service_id'),
            'responsable_id' => (string) $this->request->getGet('responsable_id'),
        ];
        if (session()->get('role') === 'responsable') {
            $f['responsable_id'] = (string) session()->get('utilisateur_id');
        }
        return $f;
    }
}
