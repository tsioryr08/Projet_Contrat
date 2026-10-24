<?php

namespace App\Controllers;

use App\Models\TypeContratModel;

/** T7 : types de contrat. */
class TypesContrat extends BaseController
{
    protected $helpers = ['audit', 'ui', 'form'];

    public function index()
    {
        $recherche = trim((string) $this->request->getGet('q'));
        $model     = new TypeContratModel();
        $builder   = $model->orderBy('famille', 'ASC')->orderBy('libelle', 'ASC');

        if ($recherche !== '') {
            $builder->groupStart()
                ->like('famille', $recherche)
                ->orLike('libelle', $recherche)
                ->groupEnd();
        }

        return view('types_contrat/index', [
            'titre'  => 'Types de contrat',
            'groupes' => $this->regrouper($builder->findAll()),
            'q'      => $recherche,
        ]);
    }

    public function nouveau()
    {
        return view('types_contrat/form', [
            'titre' => 'Nouveau type de contrat',
            'type'  => null,
            'action' => site_url('types-contrat/enregistrer'),
        ]);
    }

    public function enregistrer()
    {
        $model   = new TypeContratModel();
        $donnees = $this->saisie();

        if ($this->existe($model, $donnees['famille'], $donnees['libelle'])) {
            return redirect()->back()->withInput()->with('errors', ['Le couple famille et libellé est déjà utilisé.']);
        }

        if (! $model->insert($donnees)) {
            return redirect()->back()->withInput()->with('errors', $model->errors());
        }

        audit_log('creation', 'types_contrat', (int) $model->getInsertID(), null, $donnees);

        return redirect()->to(site_url('types-contrat'))
            ->with('success', 'Le type de contrat « ' . $donnees['libelle'] . ' » a été créé.');
    }

    public function modifier(int $id)
    {
        $type = (new TypeContratModel())->find($id);
        if (! $type) {
            return $this->introuvable();
        }

        return view('types_contrat/form', [
            'titre' => 'Modifier le type de contrat',
            'type'  => $type,
            'action' => site_url('types-contrat/mettre-a-jour/' . $id),
        ]);
    }

    public function mettreAJour(int $id)
    {
        $model = new TypeContratModel();
        $type  = $model->find($id);
        if (! $type) {
            return $this->introuvable();
        }

        $donnees = $this->saisie();
        if ($this->existe($model, $donnees['famille'], $donnees['libelle'], $id)) {
            return redirect()->back()->withInput()->with('errors', ['Le couple famille et libellé est déjà utilisé.']);
        }

        if (! $model->update($id, $donnees)) {
            return redirect()->back()->withInput()->with('errors', $model->errors());
        }

        $avant = $this->instantane($type);
        if ($avant !== $donnees) {
            audit_log('modification', 'types_contrat', $id, $avant, $donnees);
        }

        return redirect()->to(site_url('types-contrat'))
            ->with('success', 'Le type de contrat « ' . $donnees['libelle'] . ' » a été modifié.');
    }

    public function activer(int $id)
    {
        return $this->basculer($id, 1, 'activé');
    }

    public function desactiver(int $id)
    {
        return $this->basculer($id, 0, 'désactivé');
    }

    private function basculer(int $id, int $valeur, string $libelle)
    {
        $model = new TypeContratModel();
        $type  = $model->find($id);
        if (! $type) {
            return $this->introuvable();
        }

        $avant = $this->instantane($type);
        $model->update($id, ['actif' => $valeur]);
        $apres = $this->instantane($model->find($id));

        if ($avant !== $apres) {
            audit_log('modification', 'types_contrat', $id, $avant, $apres);
        }

        return redirect()->to(site_url('types-contrat'))
            ->with('success', 'Le type de contrat « ' . $type['libelle'] . ' » a été ' . $libelle . '.');
    }

    private function saisie(): array
    {
        $reconduction = $this->request->getPost('reconduction_tacite_defaut');
        $actif        = $this->request->getPost('actif');

        $duree   = trim((string) $this->request->getPost('duree_defaut_mois'));
        $preavis = trim((string) $this->request->getPost('preavis_defaut_jours'));

        return [
            'famille'                   => trim((string) $this->request->getPost('famille')),
            'libelle'                   => trim((string) $this->request->getPost('libelle')),
            'description'               => trim((string) $this->request->getPost('description')) === '' ? null : trim((string) $this->request->getPost('description')),
            'preavis_defaut_jours'      => $preavis === '' ? 0 : (int) $preavis,
            'reconduction_tacite_defaut' => ($reconduction === null) ? 0 : 1,
            'duree_defaut_mois'         => $duree === '' ? null : (int) $duree,
            'modele_template'           => trim((string) $this->request->getPost('modele_template')) === '' ? null : trim((string) $this->request->getPost('modele_template')),
            'actif'                     => ($actif === null) ? 0 : 1,
        ];
    }

    private function existe(TypeContratModel $model, string $famille, string $libelle, ?int $id = null): bool
    {
        $builder = $model->where('famille', $famille)->where('libelle', $libelle);
        if ($id !== null) {
            $builder->where('id !=', $id);
        }

        return $builder->countAllResults() > 0;
    }

    private function instantane(array $type): array
    {
        return [
            'famille'                   => $type['famille'],
            'libelle'                   => $type['libelle'],
            'description'               => $type['description'],
            'preavis_defaut_jours'      => (int) $type['preavis_defaut_jours'],
            'reconduction_tacite_defaut' => (int) $type['reconduction_tacite_defaut'],
            'duree_defaut_mois'         => $type['duree_defaut_mois'] === null ? null : (int) $type['duree_defaut_mois'],
            'modele_template'           => $type['modele_template'],
            'actif'                     => (int) $type['actif'],
        ];
    }

    private function regrouper(array $lignes): array
    {
        $groupes = [];
        foreach ($lignes as $ligne) {
            $famille = $ligne['famille'];
            if (! isset($groupes[$famille])) {
                $groupes[$famille] = [];
            }
            $groupes[$famille][] = $ligne;
        }

        return $groupes;
    }

    private function introuvable()
    {
        return redirect()->to(site_url('types-contrat'))->with('error', 'Type de contrat introuvable.');
    }
}
