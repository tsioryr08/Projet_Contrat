<?php

namespace App\Controllers;

use App\Models\FournisseurEvaluationModel;
use App\Models\FournisseurModel;

/** T6 : évaluations des fournisseurs. */
class FournisseurEvaluations extends BaseController
{
    protected $helpers = ['audit', 'ui', 'form'];

    public function liste(int $fournisseurId)
    {
        $fournisseurModel = new FournisseurModel();
        $fournisseur      = $fournisseurModel->find($fournisseurId);
        if (! $fournisseur) {
            return $this->introuvable();
        }

        $model      = new FournisseurEvaluationModel();
        $evaluations = $model->pourFournisseur($fournisseurId);
        $stats      = $model->moyennePourFournisseur($fournisseurId);

        return view('fournisseurs/evaluations', [
            'titre'       => 'Évaluations - ' . $fournisseur['raison_sociale'],
            'fournisseur' => $fournisseur,
            'evaluations' => $evaluations,
            'stats'       => $stats,
            'evaluation'  => null,
            'action'      => site_url('fournisseurs/evaluations/enregistrer/' . $fournisseurId),
            'aujourdhui'  => date('Y-m-d'),
        ]);
    }

    public function nouveau(int $fournisseurId)
    {
        $fournisseur = (new FournisseurModel())->find($fournisseurId);
        if (! $fournisseur) {
            return $this->introuvable();
        }

        $model       = new FournisseurEvaluationModel();
        $evaluations = $model->pourFournisseur($fournisseurId);
        $stats       = $model->moyennePourFournisseur($fournisseurId);

        return view('fournisseurs/evaluations', [
            'titre'       => 'Nouvelle évaluation - ' . $fournisseur['raison_sociale'],
            'fournisseur' => $fournisseur,
            'evaluations' => $evaluations,
            'stats'       => $stats,
            'evaluation'  => null,
            'action'      => site_url('fournisseurs/evaluations/enregistrer/' . $fournisseurId),
            'aujourdhui'  => date('Y-m-d'),
        ]);
    }

    public function enregistrer(int $fournisseurId)
    {
        $fournisseur = (new FournisseurModel())->find($fournisseurId);
        if (! $fournisseur) {
            return $this->introuvable();
        }

        $model   = new FournisseurEvaluationModel();
        $donnees = $this->saisie($fournisseurId);

        if (! $model->insert($donnees)) {
            return redirect()->back()->withInput()->with('errors', $model->errors());
        }

        $evaluationId = (int) $model->getInsertID();
        audit_log('creation', 'fournisseur_evaluations', $evaluationId, null, $donnees);

        return redirect()->to(site_url('fournisseurs/voir/' . $fournisseurId))
            ->with('success', 'L\'évaluation a été enregistrée.');
    }

    public function supprimer(int $id)
    {
        $model       = new FournisseurEvaluationModel();
        $evaluation  = $model->find($id);
        if (! $evaluation) {
            return $this->introuvable();
        }

        $fournisseurId = (int) $evaluation['fournisseur_id'];

        $model->delete($id);
        audit_log('suppression', 'fournisseur_evaluations', $id, $this->instantane($evaluation), null);

        return redirect()->to(site_url('fournisseurs/voir/' . $fournisseurId))
            ->with('success', 'L\'évaluation a été supprimée.');
    }

    private function saisie(int $fournisseurId): array
    {
        $evaluateurId = (int) (session('utilisateur_id') ?? 1);
        $note         = $this->request->getPost('note');
        $dateEval     = trim((string) $this->request->getPost('date_evaluation'));
        $commentaire  = trim((string) $this->request->getPost('commentaire'));

        return [
            'fournisseur_id'  => $fournisseurId,
            'evaluateur_id'   => $evaluateurId,
            'note'            => ($note === null || $note === '') ? null : (int) $note,
            'date_evaluation' => $dateEval === '' ? date('Y-m-d') : $dateEval,
            'commentaire'     => $commentaire === '' ? null : $commentaire,
        ];
    }

    private function instantane(array $evaluation): array
    {
        return [
            'fournisseur_id'  => (int) $evaluation['fournisseur_id'],
            'evaluateur_id'   => (int) $evaluation['evaluateur_id'],
            'note'            => (int) $evaluation['note'],
            'date_evaluation' => $evaluation['date_evaluation'],
            'commentaire'     => $evaluation['commentaire'] === '' ? null : $evaluation['commentaire'],
        ];
    }

    private function introuvable()
    {
        return redirect()->to(site_url('fournisseurs'))->with('error', 'Évaluation ou fournisseur introuvable.');
    }
}
