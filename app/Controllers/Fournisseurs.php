<?php

namespace App\Controllers;

use App\Models\FournisseurModel;

/** T4 : répertoire des fournisseurs. */
class Fournisseurs extends BaseController
{
    protected $helpers = ['audit', 'ui', 'form'];

    private const CHAMPS = [
        'raison_sociale', 'nif', 'stat', 'rcs', 'secteur', 'adresse', 'ville',
        'pays', 'email', 'telephone', 'representant_legal', 'statut',
    ];

    public function index()
    {
        $recherche = trim((string) $this->request->getGet('q'));
        $statut    = trim((string) $this->request->getGet('statut'));
        $model     = new FournisseurModel();

        return view('fournisseurs/index', [
            'titre'    => 'Fournisseurs',
            'lignes'   => $model->avecStats($recherche, $statut)->paginate(15),
            'pager'    => $model->pager,
            'q'        => $recherche,
            'statut'   => $statut,
            'statuts'  => FournisseurModel::STATUTS,
        ]);
    }

    public function voir(int $id)
    {
        $model       = new FournisseurModel();
        $fournisseur = $model->find($id);
        if (! $fournisseur) {
            return $this->introuvable();
        }

        return view('fournisseurs/fiche', [
            'titre'       => $fournisseur['raison_sociale'],
            'fournisseur' => $fournisseur,
            'contrats'    => $model->contratsDe($id),
            'statuts'     => FournisseurModel::STATUTS,
        ]);
    }

    public function nouveau()
    {
        return view('fournisseurs/form', [
            'titre'       => 'Nouveau fournisseur',
            'fournisseur' => null,
            'action'      => site_url('fournisseurs/enregistrer'),
            'statuts'     => FournisseurModel::STATUTS,
        ]);
    }

    public function enregistrer()
    {
        $model   = new FournisseurModel();
        $donnees = $this->saisie();

        $erreurs = $this->erreurs($model, $donnees);
        if ($erreurs) {
            return redirect()->back()->withInput()->with('errors', $erreurs);
        }
        $model->insert($donnees);

        audit_log('creation', 'fournisseurs', (int) $model->getInsertID(), null, $donnees);

        return redirect()->to(site_url('fournisseurs'))
            ->with('success', 'Le fournisseur « ' . $donnees['raison_sociale'] . ' » a été créé.');
    }

    public function modifier(int $id)
    {
        $fournisseur = (new FournisseurModel())->find($id);
        if (! $fournisseur) {
            return $this->introuvable();
        }

        return view('fournisseurs/form', [
            'titre'       => 'Modifier le fournisseur',
            'fournisseur' => $fournisseur,
            'action'      => site_url('fournisseurs/mettre-a-jour/' . $id),
            'statuts'     => FournisseurModel::STATUTS,
        ]);
    }

    public function mettreAJour(int $id)
    {
        $model       = new FournisseurModel();
        $fournisseur = $model->find($id);
        if (! $fournisseur) {
            return $this->introuvable();
        }

        $donnees = $this->saisie();
        $model->ignorerPourUnicite($id);

        $erreurs = $this->erreurs($model, $donnees);
        if ($erreurs) {
            return redirect()->back()->withInput()->with('errors', $erreurs);
        }
        $model->update($id, $donnees);

        $avant = $this->instantane($fournisseur);
        if ($avant !== $donnees) {
            audit_log('modification', 'fournisseurs', $id, $avant, $donnees);
        }

        return redirect()->to(site_url('fournisseurs/voir/' . $id))
            ->with('success', 'Le fournisseur « ' . $donnees['raison_sociale'] . ' » a été modifié.');
    }

    public function supprimer(int $id)
    {
        $model       = new FournisseurModel();
        $fournisseur = $model->find($id);
        if (! $fournisseur) {
            return $this->introuvable();
        }

        $nbContrats = $model->nombreContrats($id);
        if ($nbContrats > 0) {
            return redirect()->to(site_url('fournisseurs'))->with(
                'error',
                'Le fournisseur « ' . $fournisseur['raison_sociale'] . ' » a ' . $nbContrats
                . ' contrat(s) : suppression impossible. Vous pouvez le suspendre.'
            );
        }

        // Les contacts sont supprimés en cascade par la base ; les évaluations sont supprimées ici.
        $db = db_connect();
        $db->transStart();
        $db->table('fournisseur_evaluations')->where('fournisseur_id', $id)->delete();
        $model->delete($id);
        $db->transComplete();

        audit_log('suppression', 'fournisseurs', $id, $this->instantane($fournisseur), null);

        return redirect()->to(site_url('fournisseurs'))
            ->with('success', 'Le fournisseur « ' . $fournisseur['raison_sociale'] . ' » a été supprimé.');
    }

    // ------------------------------------------------------------------
    /** Lit le formulaire : espaces supprimés, champs vides refusés par le modèle. Normalise NIF, STAT, téléphone. */
    private function saisie(): array
    {
        $donnees = [];
        foreach (self::CHAMPS as $champ) {
            $valeur = trim((string) $this->request->getPost($champ));
            if (in_array($champ, ['nif', 'stat', 'telephone'], true)) {
                $valeur = preg_replace('/[\s.\-\(\)]/', '', $valeur);
            }
            $donnees[$champ] = $valeur === '' ? '' : $valeur;
        }
        $donnees['pays']   = $donnees['pays'] ?? 'Madagascar';
        $donnees['statut'] = $donnees['statut'] ?? 'actif';

        return $donnees;
    }

    /** Erreurs du modèle et contrôle de l'année du STAT. */
    private function erreurs(FournisseurModel $model, array $donnees): array
    {
        $erreurs = $model->validate($donnees) ? [] : $model->errors();

        $erreurStat = $model->erreurStat($donnees['stat']);
        if ($erreurStat !== null && ! isset($erreurs['stat'])) {
            $erreurs['stat'] = $erreurStat;
        }

        return $erreurs;
    }

    /** Valeurs enregistrées dans le journal : mêmes champs que la saisie. */
    private function instantane(array $fournisseur): array
    {
        $valeurs = [];
        foreach (self::CHAMPS as $champ) {
            $valeurs[$champ] = $fournisseur[$champ] === '' ? null : $fournisseur[$champ];
        }

        return $valeurs;
    }

    private function introuvable()
    {
        return redirect()->to(site_url('fournisseurs'))->with('error', 'Fournisseur introuvable.');
    }
}
