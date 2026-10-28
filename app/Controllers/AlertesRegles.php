<?php

namespace App\Controllers;

use App\Models\AlerteRegleModel;

/** T8 : règles d'alertes. */
class AlertesRegles extends BaseController
{
    protected $helpers = ['audit', 'ui', 'form'];

    public function index()
    {
        $model = new AlerteRegleModel();

        return view('alertes_regles/index', [
            'titre'   => 'Règles d\'alertes',
            'regles'  => $model->orderBy('jours_avant', 'DESC')->findAll(),
            'gravites' => AlerteRegleModel::GRAVITES,
        ]);
    }

    public function modifier(int $id)
    {
        $model = new AlerteRegleModel();
        $regle = $model->find($id);
        if (! $regle) {
            return $this->introuvable();
        }

        return view('alertes_regles/form', [
            'titre'   => 'Modifier la règle d\'alerte',
            'regle'   => $regle,
            'action'  => site_url('alertes-regles/mettre-a-jour/' . $id),
            'gravites' => AlerteRegleModel::GRAVITES,
        ]);
    }

    public function mettreAJour(int $id)
    {
        $model = new AlerteRegleModel();
        $regle = $model->find($id);
        if (! $regle) {
            return $this->introuvable();
        }

        $donnees = $this->saisie();

        if ($donnees['jours_avant'] < 0) {
            return redirect()->back()->withInput()->with('errors', ['Le nombre de jours avant doit être positif ou nul.']);
        }

        if (! $model->update($id, $donnees)) {
            return redirect()->back()->withInput()->with('errors', $model->errors());
        }

        $avant = $this->instantane($regle);
        $apres = $this->instantane($model->find($id));
        if ($avant !== $apres) {
            audit_log('modification', 'alertes_regles', $id, $avant, $apres);
        }

        return redirect()->to(site_url('alertes-regles'))
            ->with('success', 'La règle « ' . $regle['code'] . ' » a été modifiée.');
    }

    private function saisie(): array
    {
        $notifier = $this->request->getPost('notifier_superieur');
        $actif    = $this->request->getPost('actif');
        $jours    = trim((string) $this->request->getPost('jours_avant'));

        $joursVal = $jours === '' ? -1 : (int) $jours;

        return [
            'jours_avant'       => $joursVal,
            'gravite'           => $this->request->getPost('gravite'),
            'notifier_superieur' => ($notifier === null) ? 0 : 1,
            'actif'             => ($actif === null) ? 0 : 1,
        ];
    }

    private function instantane(array $regle): array
    {
        return [
            'jours_avant'       => (int) $regle['jours_avant'],
            'gravite'           => $regle['gravite'],
            'notifier_superieur' => (int) $regle['notifier_superieur'],
            'actif'             => (int) $regle['actif'],
        ];
    }

    private function introuvable()
    {
        return redirect()->to(site_url('alertes-regles'))->with('error', 'Règle d\'alerte introuvable.');
    }
}
