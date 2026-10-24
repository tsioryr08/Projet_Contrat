<?php

namespace App\Controllers;

use App\Models\FournisseurContactModel;
use App\Models\FournisseurModel;

/** T5 : contacts d'un fournisseur. */
class FournisseurContacts extends BaseController
{
    protected $helpers = ['audit', 'ui', 'form'];

    public function liste(int $fournisseurId)
    {
        $fournisseurModel = new FournisseurModel();
        $fournisseur      = $fournisseurModel->find($fournisseurId);
        if (! $fournisseur) {
            return $this->introuvable();
        }

        $model = new FournisseurContactModel();

        return view('fournisseurs/contacts', [
            'titre'       => 'Contacts - ' . $fournisseur['raison_sociale'],
            'fournisseur' => $fournisseur,
            'contacts'    => $model->pourFournisseur($fournisseurId),
            'contact'     => null,
            'action'      => site_url('fournisseurs/contacts/enregistrer/' . $fournisseurId),
        ]);
    }

    public function nouveau(int $fournisseurId)
    {
        $fournisseur = (new FournisseurModel())->find($fournisseurId);
        if (! $fournisseur) {
            return $this->introuvable();
        }

        return view('fournisseurs/contacts', [
            'titre'       => 'Nouveau contact - ' . $fournisseur['raison_sociale'],
            'fournisseur' => $fournisseur,
            'contacts'    => (new FournisseurContactModel())->pourFournisseur($fournisseurId),
            'contact'     => null,
            'action'      => site_url('fournisseurs/contacts/enregistrer/' . $fournisseurId),
        ]);
    }

    public function enregistrer(int $fournisseurId)
    {
        $fournisseur = (new FournisseurModel())->find($fournisseurId);
        if (! $fournisseur) {
            return $this->introuvable();
        }

        $model   = new FournisseurContactModel();
        $donnees = $this->saisie($fournisseurId);

        if (! $model->insert($donnees)) {
            return redirect()->back()->withInput()->with('errors', $model->errors());
        }

        $contactId = (int) $model->getInsertID();
        audit_log('creation', 'fournisseur_contacts', $contactId, null, $donnees);

        return redirect()->to(site_url('fournisseurs/voir/' . $fournisseurId))
            ->with('success', 'Le contact « ' . $donnees['nom'] . ' » a été ajouté.');
    }

    public function modifier(int $id)
    {
        $model   = new FournisseurContactModel();
        $contact = $model->find($id);
        if (! $contact) {
            return $this->introuvable();
        }

        $fournisseur = (new FournisseurModel())->find($contact['fournisseur_id']);
        if (! $fournisseur) {
            return $this->introuvable();
        }

        return view('fournisseurs/contacts', [
            'titre'       => 'Modifier le contact - ' . $fournisseur['raison_sociale'],
            'fournisseur' => $fournisseur,
            'contacts'    => $model->pourFournisseur((int) $contact['fournisseur_id']),
            'contact'     => $contact,
            'action'      => site_url('fournisseurs/contacts/mettre-a-jour/' . $id),
        ]);
    }

    public function mettreAJour(int $id)
    {
        $model   = new FournisseurContactModel();
        $contact = $model->find($id);
        if (! $contact) {
            return $this->introuvable();
        }

        $fournisseurId = (int) $contact['fournisseur_id'];
        $fournisseur   = (new FournisseurModel())->find($fournisseurId);
        if (! $fournisseur) {
            return $this->introuvable();
        }

        $donnees = $this->saisie($fournisseurId);

        if (! $model->update($id, $donnees)) {
            return redirect()->back()->withInput()->with('errors', $model->errors());
        }

        $avant = $this->instantane($contact);
        if ($avant !== $donnees) {
            audit_log('modification', 'fournisseur_contacts', $id, $avant, $donnees);
        }

        return redirect()->to(site_url('fournisseurs/voir/' . $fournisseurId))
            ->with('success', 'Le contact « ' . $donnees['nom'] . ' » a été modifié.');
    }

    public function supprimer(int $id)
    {
        $model   = new FournisseurContactModel();
        $contact = $model->find($id);
        if (! $contact) {
            return $this->introuvable();
        }

        $fournisseurId = (int) $contact['fournisseur_id'];
        $nom           = $contact['nom'];

        $model->delete($id);
        audit_log('suppression', 'fournisseur_contacts', $id, $this->instantane($contact), null);

        return redirect()->to(site_url('fournisseurs/voir/' . $fournisseurId))
            ->with('success', 'Le contact « ' . $nom . ' » a été supprimé.');
    }

    private function saisie(int $fournisseurId): array
    {
        $telephone = trim((string) $this->request->getPost('telephone'));
        $telephone = preg_replace('/[\s.\-\(\)\+]/', '', $telephone);

        return [
            'fournisseur_id' => $fournisseurId,
            'nom'            => trim((string) $this->request->getPost('nom')),
            'fonction'       => trim((string) $this->request->getPost('fonction')) === '' ? null : trim((string) $this->request->getPost('fonction')),
            'email'          => trim((string) $this->request->getPost('email')) === '' ? null : trim((string) $this->request->getPost('email')),
            'telephone'      => $telephone === '' ? null : $telephone,
        ];
    }

    private function instantane(array $contact): array
    {
        return [
            'fournisseur_id' => (int) $contact['fournisseur_id'],
            'nom'            => $contact['nom'],
            'fonction'       => $contact['fonction'] === '' ? null : $contact['fonction'],
            'email'          => $contact['email'] === '' ? null : $contact['email'],
            'telephone'      => $contact['telephone'] === '' ? null : $contact['telephone'],
        ];
    }

    private function introuvable()
    {
        return redirect()->to(site_url('fournisseurs'))->with('error', 'Contact ou fournisseur introuvable.');
    }
}
