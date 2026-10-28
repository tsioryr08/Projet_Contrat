<?php

namespace App\Controllers;

use App\Models\AlerteModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class Alertes extends BaseController
{
    private AlerteModel $alertes;

    public function __construct()
    {
        $this->alertes = new AlerteModel();
    }

    public function index()
    {
        $uid   = (int) session()->get('utilisateur_id');
        $tous  = session()->get('role') === 'admin' && $this->request->getGet('tous') === '1';
        $etat  = (string) ($this->request->getGet('etat') ?: 'actives');

        $this->alertes
            ->select("alertes.*, r.code AS regle_code, r.gravite, c.reference AS contrat_reference,
                      c.intitule AS contrat_intitule, CONCAT_WS(' ', u.prenom, u.nom) AS destinataire_nom", false)
            ->join('alertes_regles r', 'r.id = alertes.regle_id')
            ->join('contrats c', 'c.id = alertes.contrat_id')
            ->join('utilisateurs u', 'u.id = alertes.destinataire_id')
            ->where('alertes.canal', 'application');

        match ($etat) {
            'non_lues' => $this->alertes->where('alertes.statut', 'envoyee'),
            'traitees' => $this->alertes->where('alertes.statut', 'traitee'),
            'toutes'   => $this->alertes->whereIn('alertes.statut', ['envoyee', 'lue', 'traitee']),
            default    => $this->alertes->whereIn('alertes.statut', ['envoyee', 'lue']),
        };
        if (! $tous) {
            $this->alertes->where('alertes.destinataire_id', $uid);
        }

        $lignes = $this->alertes->orderBy('alertes.genere_par_ia', 'DESC')->orderBy('alertes.id', 'DESC')->paginate(20);

        return view('alertes/index', [
            'titre'    => 'Alertes',
            'alertes'  => $lignes,
            'pager'    => $this->alertes->pager,
            'etat'     => $etat,
            'tous'     => $tous,
            'estAdmin' => session()->get('role') === 'admin',
        ]);
    }

    public function lue(int $id)
    {
        return $this->changer($id, 'lue');
    }

    public function traitee(int $id)
    {
        return $this->changer($id, 'traitee');
    }

    public function toutesLues()
    {
        $this->alertes
            ->where('destinataire_id', (int) session()->get('utilisateur_id'))
            ->where('canal', 'application')->where('statut', 'envoyee')
            ->set(['statut' => 'lue', 'lue_at' => date('Y-m-d H:i:s')])->update();

        return redirect()->to('alertes')->with('succes', 'Toutes les alertes sont marquées comme lues.');
    }

    /** Depuis le tableau de bord : traite toutes mes alertes d'un contrat. */
    public function traiterContrat(int $contratId)
    {
        $uid  = (int) session()->get('utilisateur_id');
        $rows = $this->alertes->where('contrat_id', $contratId)->where('destinataire_id', $uid)
            ->where('canal', 'application')->whereIn('statut', ['envoyee', 'lue'])->findAll();

        foreach ($rows as $a) {
            $this->alertes->update($a['id'], [
                'statut' => 'traitee',
                'lue_at' => $a['lue_at'] ?: date('Y-m-d H:i:s'),
            ]);
            audit_log('traitement', 'alertes', (int) $a['id'], ['statut' => $a['statut']], ['statut' => 'traitee']);
        }

        return redirect()->back()->with('succes', count($rows) . ' alerte(s) marquée(s) comme traitée(s).');
    }

    private function changer(int $id, string $statut)
    {
        $a = $this->alertes->find($id);
        if (! $a || $a['canal'] !== 'application') {
            throw PageNotFoundException::forPageNotFound('Alerte introuvable.');
        }
        if ((int) $a['destinataire_id'] !== (int) session()->get('utilisateur_id') && session()->get('role') !== 'admin') {
            return redirect()->to('alertes')->with('erreur', "Cette alerte ne vous est pas destinée.");
        }
        if ($a['statut'] === 'traitee' || ($statut === 'lue' && $a['statut'] !== 'envoyee')) {
            return redirect()->back();
        }

        $this->alertes->update($id, [
            'statut' => $statut,
            'lue_at' => $a['lue_at'] ?: date('Y-m-d H:i:s'),
        ]);
        if ($statut === 'traitee') {
            audit_log('traitement', 'alertes', $id, ['statut' => $a['statut']], ['statut' => 'traitee']);
        }

        return redirect()->back();
    }
}
