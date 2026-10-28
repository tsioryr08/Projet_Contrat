<?php

namespace App\Controllers;

use App\Models\PenaliteModel;

/** T10 : pénalités. */
class Penalites extends BaseController
{
    protected $helpers = ['audit', 'ui', 'form'];

    public function index()
    {
        $recherche = trim((string) $this->request->getGet('q'));
        $statut    = trim((string) $this->request->getGet('statut'));
        $contratId = trim((string) $this->request->getGet('contrat_id'));
        $model     = new PenaliteModel();

        $lignes = $model->avecContrat($recherche, $statut, $contratId);
        foreach ($lignes as $k => $ligne) {
            $lignes[$k]['retard_jours'] = $model->retardDecouverte($ligne);
            if (isset($lignes[$k]['fournisseur_nom']) && ! isset($lignes[$k]['fournisseur'])) {
                $lignes[$k]['fournisseur'] = $lignes[$k]['fournisseur_nom'];
            }
        }

        return view('penalites/index', [
            'titre'    => 'Pénalités',
            'lignes'   => $lignes,
            'q'        => $recherche,
            'statut'   => $statut,
            'contratId' => $contratId,
            'statuts'  => PenaliteModel::STATUTS,
            'contrats' => $model->contratsListe(),
            'totaux'   => $model->totauxParStatut(),
        ]);
    }

    public function nouveau()
    {
        $model = new PenaliteModel();

        return view('penalites/form', [
            'titre'    => 'Nouvelle pénalité',
            'penalite' => null,
            'action'   => site_url('penalites/enregistrer'),
            'statuts'  => PenaliteModel::STATUTS,
            'contrats' => $model->contratsListe(),
            'litiges'  => $model->litigesListe(),
        ]);
    }

    public function enregistrer()
    {
        $model   = new PenaliteModel();
        $donnees = $this->saisie();

        if (! $model->insert($donnees)) {
            return redirect()->back()->withInput()->with('errors', $model->errors());
        }

        audit_log('creation', 'penalites', (int) $model->getInsertID(), null, $donnees);

        return redirect()->to(site_url('penalites'))
            ->with('success', 'La pénalité a été enregistrée.');
    }

    public function modifier(int $id)
    {
        $model    = new PenaliteModel();
        $penalite = $model->find($id);
        if (! $penalite) {
            return $this->introuvable();
        }

        return view('penalites/form', [
            'titre'    => 'Modifier la pénalité',
            'penalite' => $penalite,
            'action'   => site_url('penalites/mettre-a-jour/' . $id),
            'statuts'  => PenaliteModel::STATUTS,
            'contrats' => $model->contratsListe(),
            'litiges'  => $model->litigesListe(),
        ]);
    }

    public function mettreAJour(int $id)
    {
        $model    = new PenaliteModel();
        $penalite = $model->find($id);
        if (! $penalite) {
            return $this->introuvable();
        }

        $donnees = $this->saisie();

        if (! $model->update($id, $donnees)) {
            return redirect()->back()->withInput()->with('errors', $model->errors());
        }

        $avant = $this->instantane($penalite);
        if ($avant !== $donnees) {
            audit_log('modification', 'penalites', $id, $avant, $donnees);
        }

        return redirect()->to(site_url('penalites'))
            ->with('success', 'La pénalité a été modifiée.');
    }

    public function supprimer(int $id)
    {
        $model    = new PenaliteModel();
        $penalite = $model->find($id);
        if (! $penalite) {
            return $this->introuvable();
        }

        $model->delete($id);
        audit_log('suppression', 'penalites', $id, $this->instantane($penalite), null);

        return redirect()->to(site_url('penalites'))
            ->with('success', 'La pénalité a été supprimée.');
    }

    private function saisie(): array
    {
        $litigeId = $this->request->getPost('litige_id');
        $motif    = trim((string) $this->request->getPost('motif'));
        $montant  = trim((string) $this->request->getPost('montant'));

        return [
            'contrat_id'           => (int) $this->request->getPost('contrat_id'),
            'litige_id'            => ($litigeId === null || $litigeId === '') ? null : (int) $litigeId,
            'nature'               => trim((string) $this->request->getPost('nature')),
            'montant'              => $montant,
            'date_fait_generateur' => trim((string) $this->request->getPost('date_fait_generateur')),
            'date_decouverte'      => trim((string) $this->request->getPost('date_decouverte')),
            'statut'               => $this->request->getPost('statut'),
            'motif'                => $motif === '' ? null : $motif,
        ];
    }

    private function instantane(array $penalite): array
    {
        return [
            'contrat_id'           => (int) $penalite['contrat_id'],
            'litige_id'            => $penalite['litige_id'] === null ? null : (int) $penalite['litige_id'],
            'nature'               => $penalite['nature'],
            'montant'              => $penalite['montant'],
            'date_fait_generateur' => $penalite['date_fait_generateur'],
            'date_decouverte'      => $penalite['date_decouverte'],
            'statut'               => $penalite['statut'],
            'motif'                => $penalite['motif'],
        ];
    }

    private function introuvable()
    {
        return redirect()->to(site_url('penalites'))->with('error', 'Pénalité introuvable.');
    }
}
