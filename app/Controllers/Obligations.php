<?php

namespace App\Controllers;

use App\Models\ObligationModel;
use App\Models\ContratModel;

/** T9 : obligations. */
class Obligations extends BaseController
{
    protected $helpers = ['audit', 'ui', 'form'];

    public function index()
    {
        $recherche = trim((string) $this->request->getGet('q'));
        $statut    = trim((string) $this->request->getGet('statut'));
        $contratId = trim((string) $this->request->getGet('contrat_id'));
        $model     = new ObligationModel();

        return view('obligations/index', [
            'titre'    => 'Obligations',
            'lignes'   => $model->avecContrat($recherche, $statut, $contratId),
            'q'        => $recherche,
            'statut'   => $statut,
            'contratId' => $contratId,
            'statuts'  => ObligationModel::STATUTS,
            'types'    => ObligationModel::TYPES,
            'contrats' => $model->contratsListe(),
        ]);
    }

    public function nouveau()
    {
        $contratId = (int) ($this->request->getGet('contrat_id') ?? 0);
        $model     = new ObligationModel();

        return view('obligations/form', [
            'titre'     => 'Nouvelle obligation',
            'obligation' => null,
            'action'    => site_url('obligations/enregistrer'),
            'types'     => ObligationModel::TYPES,
            'statuts'   => ObligationModel::STATUTS,
            'contrats'  => $model->contratsListe(),
            'contratId' => $contratId,
        ]);
    }

    public function enregistrer()
    {
        $model   = new ObligationModel();
        $donnees = $this->saisie();
        if (isset($donnees['statut']) && $donnees['statut'] === 'en_retard') {
            $donnees['statut'] = 'a_venir';
        }

        if (isset($donnees['statut']) && $donnees['statut'] === 'executee' && empty($donnees['executee_le'])) {
            $donnees['executee_le'] = date('Y-m-d');
        } elseif (isset($donnees['statut']) && $donnees['statut'] !== 'executee') {
            $donnees['executee_le'] = null;
        }

        if (! $model->insert($donnees)) {
            return redirect()->back()->withInput()->with('errors', $model->errors());
        }

        audit_log('creation', 'obligations', (int) $model->getInsertID(), null, $donnees);

        $redirect = $this->request->getGet('retour');
        if ($redirect === 'fiche' && ! empty($donnees['contrat_id'])) {
            return redirect()->to(site_url('contrats/voir/' . (int) $donnees['contrat_id']))
                ->with('success', 'L\'obligation a été ajoutée.');
        }

        return redirect()->to(site_url('obligations'))
            ->with('success', 'L\'obligation a été ajoutée.');
    }

    public function modifier(int $id)
    {
        $model       = new ObligationModel();
        $obligation  = $model->find($id);
        if (! $obligation) {
            return $this->introuvable();
        }

        return view('obligations/form', [
            'titre'      => 'Modifier l\'obligation',
            'obligation' => $obligation,
            'action'     => site_url('obligations/mettre-a-jour/' . $id),
            'types'      => ObligationModel::TYPES,
            'statuts'    => ObligationModel::STATUTS,
            'contrats'   => $model->contratsListe(),
            'contratId'  => (int) $obligation['contrat_id'],
        ]);
    }

    public function mettreAJour(int $id)
    {
        $model       = new ObligationModel();
        $obligation  = $model->find($id);
        if (! $obligation) {
            return $this->introuvable();
        }

        $donnees = $this->saisie();
        if (isset($donnees['statut']) && $donnees['statut'] === 'en_retard') {
            unset($donnees['statut']);
        }

        if (isset($donnees['statut']) && $donnees['statut'] === 'executee' && empty($donnees['executee_le'])) {
            $donnees['executee_le'] = date('Y-m-d');
        } elseif (isset($donnees['statut']) && $donnees['statut'] !== 'executee') {
            $donnees['executee_le'] = null;
        } elseif (! isset($donnees['statut']) && isset($donnees['executee_le'])) {
            // ne pas forcer
        } elseif (isset($donnees['statut'])) {
            // ok
        }

        if (! $model->update($id, $donnees)) {
            return redirect()->back()->withInput()->with('errors', $model->errors());
        }

        $avant = $this->instantane($obligation);
        if ($avant !== $donnees) {
            audit_log('modification', 'obligations', $id, $avant, $donnees);
        }

        $redirect = $this->request->getGet('retour');
        if ($redirect === 'fiche' && ! empty($donnees['contrat_id'])) {
            return redirect()->to(site_url('contrats/voir/' . (int) $donnees['contrat_id']))
                ->with('success', 'L\'obligation a été modifiée.');
        }

        return redirect()->to(site_url('obligations'))
            ->with('success', 'L\'obligation a été modifiée.');
    }

    public function changerStatut(int $id)
    {
        $model      = new ObligationModel();
        $obligation = $model->find($id);
        if (! $obligation) {
            return $this->introuvable();
        }

        $statut = $this->request->getPost('statut');
        if (! in_array($statut, ['a_venir', 'en_cours', 'executee', 'annulee'], true)) {
            return redirect()->back()->with('error', 'Statut invalide.');
        }

        $donnees = ['statut' => $statut];
        if ($statut === 'executee') {
            $donnees['executee_le'] = date('Y-m-d');
        } elseif ($statut !== 'executee') {
            $donnees['executee_le'] = null;
        }

        $model->update($id, $donnees);

        $apres = $model->find($id);
        $avant = $obligation;
        if ($avant['statut'] !== $apres['statut'] || $avant['executee_le'] !== $apres['executee_le']) {
            audit_log('modification', 'obligations', $id, $this->instantane($avant), $this->instantane($apres));
        }

        return redirect()->back()->with('success', 'Statut de l\'obligation mis à jour.');
    }

    public function supprimer(int $id)
    {
        $model      = new ObligationModel();
        $obligation = $model->find($id);
        if (! $obligation) {
            return $this->introuvable();
        }

        $contratId = (int) $obligation['contrat_id'];

        $db = db_connect();
        $db->transStart();
        $db->table('alertes')->where('obligation_id', $id)->delete();
        $model->delete($id);
        $db->transComplete();

        audit_log('suppression', 'obligations', $id, $this->instantane($obligation), null);

        $redirect = $this->request->getGet('retour');
        if ($redirect === 'fiche') {
            return redirect()->to(site_url('contrats/voir/' . $contratId))
                ->with('success', 'L\'obligation a été supprimée.');
        }

        return redirect()->to(site_url('obligations'))
            ->with('success', 'L\'obligation a été supprimée.');
    }

    private function saisie(): array
    {
        $montant     = trim((string) $this->request->getPost('montant'));
        $description = trim((string) $this->request->getPost('description'));
        $executeeLe  = trim((string) $this->request->getPost('executee_le'));

        return [
            'contrat_id'      => (int) $this->request->getPost('contrat_id'),
            'debiteur'        => trim((string) $this->request->getPost('debiteur')),
            'creancier'       => trim((string) $this->request->getPost('creancier')),
            'type_obligation' => $this->request->getPost('type_obligation'),
            'description'     => $description === '' ? null : $description,
            'montant'         => $montant === '' ? null : $montant,
            'echeance'        => trim((string) $this->request->getPost('echeance')),
            'statut'          => $this->request->getPost('statut'),
            'executee_le'     => $executeeLe === '' ? null : $executeeLe,
        ];
    }

    private function instantane(array $obligation): array
    {
        return [
            'contrat_id'      => (int) $obligation['contrat_id'],
            'debiteur'        => $obligation['debiteur'],
            'creancier'       => $obligation['creancier'],
            'type_obligation' => $obligation['type_obligation'],
            'description'     => $obligation['description'],
            'montant'         => $obligation['montant'],
            'echeance'        => $obligation['echeance'],
            'statut'          => $obligation['statut'],
            'executee_le'     => $obligation['executee_le'],
        ];
    }

    private function introuvable()
    {
        return redirect()->to(site_url('obligations'))->with('error', 'Obligation introuvable.');
    }
}
