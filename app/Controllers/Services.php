<?php

namespace App\Controllers;

use App\Models\ServiceModel;

/** T3 : gestion des services de l'organisation. */
class Services extends BaseController
{
    protected $helpers = ['audit', 'ui', 'form'];

    public function index()
    {
        $recherche = trim((string) $this->request->getGet('q'));
        $model     = new ServiceModel();

        return view('services/index', [
            'titre'  => 'Services',
            'lignes' => $model->avecResponsable($recherche)->paginate(15),
            'pager'  => $model->pager,
            'q'      => $recherche,
        ]);
    }

    public function nouveau()
    {
        return view('services/form', [
            'titre'        => 'Nouveau service',
            'service'      => null,
            'action'       => site_url('services/enregistrer'),
            'responsables' => $this->responsables(),
        ]);
    }

    public function enregistrer()
    {
        $model   = new ServiceModel();
        $donnees = $this->saisie();

        if (! $this->responsableActif($donnees['responsable_id'])) {
            return redirect()->back()->withInput()->with('errors', ['Le responsable doit être un utilisateur actif.']);
        }
        if (! $model->insert($donnees)) {
            return redirect()->back()->withInput()->with('errors', $model->errors());
        }

        audit_log('creation', 'services', (int) $model->getInsertID(), null, $donnees);

        return redirect()->to(site_url('services'))
            ->with('success', 'Le service « ' . $donnees['nom'] . ' » a été créé.');
    }

    public function modifier(int $id)
    {
        $service = (new ServiceModel())->find($id);
        if (! $service) {
            return $this->introuvable();
        }

        return view('services/form', [
            'titre'        => 'Modifier le service',
            'service'      => $service,
            'action'       => site_url('services/mettre-a-jour/' . $id),
            'responsables' => $this->responsables(),
        ]);
    }

    public function mettreAJour(int $id)
    {
        $model   = new ServiceModel();
        $service = $model->find($id);
        if (! $service) {
            return $this->introuvable();
        }

        $donnees = $this->saisie();
        if (! $this->responsableActif($donnees['responsable_id'])) {
            return redirect()->back()->withInput()->with('errors', ['Le responsable doit être un utilisateur actif.']);
        }

        // Le nom doit rester unique, en ignorant le service en cours de modification.
        $model->setValidationRule('nom', 'required|min_length[2]|max_length[100]|is_unique[services.nom,id,' . $id . ']');
        if (! $model->update($id, $donnees)) {
            return redirect()->back()->withInput()->with('errors', $model->errors());
        }

        $avant = $this->instantane($service);
        if ($avant !== $donnees) {
            audit_log('modification', 'services', $id, $avant, $donnees);
        }

        return redirect()->to(site_url('services'))
            ->with('success', 'Le service « ' . $donnees['nom'] . ' » a été modifié.');
    }

    public function supprimer(int $id)
    {
        $model   = new ServiceModel();
        $service = $model->find($id);
        if (! $service) {
            return $this->introuvable();
        }

        [$nbUtilisateurs, $nbContrats] = $model->utilisations($id);
        if ($nbUtilisateurs > 0 || $nbContrats > 0) {
            return redirect()->to(site_url('services'))->with(
                'error',
                'Le service « ' . $service['nom'] . ' » est utilisé par ' . $nbUtilisateurs
                . ' utilisateur(s) et ' . $nbContrats . ' contrat(s) : suppression impossible.'
            );
        }

        $model->delete($id);
        audit_log('suppression', 'services', $id, $this->instantane($service), null);

        return redirect()->to(site_url('services'))
            ->with('success', 'Le service « ' . $service['nom'] . ' » a été supprimé.');
    }

    // ------------------------------------------------------------------
    private function saisie(): array
    {
        $responsable = $this->request->getPost('responsable_id');

        return [
            'nom'            => trim((string) $this->request->getPost('nom')),
            'responsable_id' => ($responsable === null || $responsable === '') ? null : (int) $responsable,
        ];
    }

    /** Valeurs d'un service telles qu'elles sont enregistrées dans le journal. */
    private function instantane(array $service): array
    {
        return [
            'nom'            => $service['nom'],
            'responsable_id' => $service['responsable_id'] === null ? null : (int) $service['responsable_id'],
        ];
    }

    /** Utilisateurs actifs proposés comme responsables (lecture directe de utilisateurs). */
    private function responsables(): array
    {
        return db_connect()->table('utilisateurs')
            ->select("id, TRIM(CONCAT_WS(' ', prenom, nom)) AS nom_complet", false)
            ->where('actif', 1)->orderBy('nom')->get()->getResultArray();
    }

    private function responsableActif(?int $id): bool
    {
        if ($id === null) {
            return true;
        }

        return db_connect()->table('utilisateurs')->where('id', $id)->where('actif', 1)->countAllResults() > 0;
    }

    private function introuvable()
    {
        return redirect()->to(site_url('services'))->with('error', 'Service introuvable.');
    }
}
