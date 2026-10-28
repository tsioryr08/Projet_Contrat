<?php

namespace App\Controllers;

use App\Models\ServiceModel;

/** T11 : gestion des utilisateurs. */
class Utilisateurs extends BaseController
{
    protected $helpers = ['audit', 'ui', 'form'];

    public function index()
    {
        $recherche = trim((string) $this->request->getGet('q'));
        $db        = db_connect();
        $builder   = $db->table('utilisateurs u')
            ->select('u.*, r.code AS role_code, r.libelle AS role_libelle, s.nom AS service_nom, TRIM(CONCAT_WS(\' \', sup.prenom, sup.nom)) AS superieur_nom', false)
            ->join('roles r', 'r.id = u.role_id')
            ->join('services s', 's.id = u.service_id', 'left')
            ->join('utilisateurs sup', 'sup.id = u.superieur_id', 'left')
            ->orderBy('u.nom', 'ASC');

        if ($recherche !== '') {
            $builder->groupStart()
                ->like('u.nom', $recherche)
                ->orLike('u.prenom', $recherche)
                ->orLike('u.email', $recherche)
                ->groupEnd();
        }

        return view('utilisateurs/index', [
            'titre'  => 'Utilisateurs',
            'lignes' => $builder->get()->getResultArray(),
            'q'      => $recherche,
        ]);
    }

    public function nouveau()
    {
        return view('utilisateurs/form', [
            'titre'       => 'Nouvel utilisateur',
            'utilisateur' => null,
            'action'      => site_url('utilisateurs/enregistrer'),
            'roles'       => $this->roles(),
            'services'    => $this->services(),
            'utilisateurs' => $this->utilisateursListe(),
        ]);
    }

    public function enregistrer()
    {
        $db         = db_connect();
        $donnees    = $this->saisie();
        $motDePasse = trim((string) $this->request->getPost('mot_de_passe'));

        if ((int) $donnees['role_id'] === 0) {
            return redirect()->back()->withInput()->with('errors', ['Le rôle est obligatoire.']);
        }
        if ($motDePasse === '') {
            return redirect()->back()->withInput()->with('errors', ['Le mot de passe est obligatoire.']);
        }
        if (strlen($motDePasse) < 6) {
            return redirect()->back()->withInput()->with('errors', ['Le mot de passe doit contenir au moins 6 caractères.']);
        }
        if ($this->emailExiste($db, $donnees['email'])) {
            return redirect()->back()->withInput()->with('errors', ['Cet e-mail est déjà utilisé.']);
        }

        $donnees['mot_de_passe'] = password_hash($motDePasse, PASSWORD_DEFAULT);

        $db->table('utilisateurs')->insert($donnees);
        $id = (int) $db->insertID();

        audit_log('creation', 'utilisateurs', $id, null, $this->nettoyerPourAudit($donnees));

        return redirect()->to(site_url('utilisateurs'))
            ->with('success', 'L\'utilisateur a été créé.');
    }

    public function modifier(int $id)
    {
        $db          = db_connect();
        $utilisateur = $db->table('utilisateurs')->where('id', $id)->get()->getRowArray();
        if (! $utilisateur) {
            return $this->introuvable();
        }

        return view('utilisateurs/form', [
            'titre'       => 'Modifier l\'utilisateur',
            'utilisateur' => $utilisateur,
            'action'      => site_url('utilisateurs/mettre-a-jour/' . $id),
            'roles'       => $this->roles(),
            'services'    => $this->services(),
            'utilisateurs' => $this->utilisateursListe($id),
        ]);
    }

    public function mettreAJour(int $id)
    {
        $db          = db_connect();
        $donnees     = $this->saisie();
        $utilisateur = $db->table('utilisateurs')->where('id', $id)->get()->getRowArray();
        if (! $utilisateur) {
            return $this->introuvable();
        }

        if ((int) $donnees['role_id'] === 0) {
            return redirect()->back()->withInput()->with('errors', ['Le rôle est obligatoire.']);
        }
        if ($this->emailExiste($db, $donnees['email'], $id)) {
            return redirect()->back()->withInput()->with('errors', ['Cet e-mail est déjà utilisé.']);
        }

        $motDePasse = trim((string) $this->request->getPost('mot_de_passe'));
        if ($motDePasse !== '') {
            if (strlen($motDePasse) < 6) {
                return redirect()->back()->withInput()->with('errors', ['Le mot de passe doit contenir au moins 6 caractères.']);
            }
            $donnees['mot_de_passe'] = password_hash($motDePasse, PASSWORD_DEFAULT);
        } else {
            unset($donnees['mot_de_passe']);
        }

        $avant = $this->instantane($utilisateur);
        $db->table('utilisateurs')->where('id', $id)->update($donnees);
        $apres = $this->instantane($db->table('utilisateurs')->where('id', $id)->get()->getRowArray());

        if ($avant !== $apres) {
            audit_log('modification', 'utilisateurs', $id, $avant, $apres);
        }

        return redirect()->to(site_url('utilisateurs'))
            ->with('success', 'L\'utilisateur a été modifié.');
    }

    public function reinitialiserMotDePasse(int $id)
    {
        $db          = db_connect();
        $utilisateur = $db->table('utilisateurs')->where('id', $id)->get()->getRowArray();
        if (! $utilisateur) {
            return $this->introuvable();
        }

        $nouveau = $this->genererMotDePasse();
        $db->table('utilisateurs')->where('id', $id)->update(['mot_de_passe' => password_hash($nouveau, PASSWORD_DEFAULT)]);
        audit_log('modification', 'utilisateurs', $id, ['mot_de_passe' => '***'], ['mot_de_passe' => '***']);

        return redirect()->to(site_url('utilisateurs/modifier/' . $id))
            ->with('success', 'Mot de passe réinitialisé. ' );
    }

    public function desactiver(int $id)
    {
        return $this->basculerActif($id, 0);
    }

    public function activer(int $id)
    {
        return $this->basculerActif($id, 1);
    }

    private function basculerActif(int $id, int $valeur)
    {
        $db          = db_connect();
        $utilisateur = $db->table('utilisateurs')->where('id', $id)->get()->getRowArray();
        if (! $utilisateur) {
            return $this->introuvable();
        }

        $sessionId = (int) (session('utilisateur_id') ?? 0);
        if ($sessionId === $id && $valeur === 0) {
            return redirect()->to(site_url('utilisateurs'))->with('error', 'Vous ne pouvez pas vous désactiver vous-même.');
        }

        $avant = $this->instantane($utilisateur);
        $db->table('utilisateurs')->where('id', $id)->update(['actif' => $valeur]);
        $apres = $this->instantane($db->table('utilisateurs')->where('id', $id)->get()->getRowArray());

        if ($avant !== $apres) {
            audit_log('modification', 'utilisateurs', $id, $avant, $apres);
        }

        return redirect()->to(site_url('utilisateurs'))
            ->with('success', $valeur === 1 ? 'L\'utilisateur a été activé.' : 'L\'utilisateur a été désactivé.');
    }

    private function saisie(): array
    {
        $roleId      = $this->request->getPost('role_id');
        $serviceId   = $this->request->getPost('service_id');
        $superieurId = $this->request->getPost('superieur_id');
        $actif       = $this->request->getPost('actif');

        return [
            'nom'          => trim((string) $this->request->getPost('nom')),
            'prenom'       => trim((string) $this->request->getPost('prenom')) === '' ? null : trim((string) $this->request->getPost('prenom')),
            'email'        => trim((string) $this->request->getPost('email')),
            'role_id'      => ($roleId === null || $roleId === '') ? 0 : (int) $roleId,
            'service_id'   => ($serviceId === null || $serviceId === '') ? null : (int) $serviceId,
            'superieur_id' => ($superieurId === null || $superieurId === '') ? null : (int) $superieurId,
            'actif'        => ($actif === null) ? 0 : 1,
        ];
    }

    private function roles(): array
    {
        return db_connect()->table('roles')->orderBy('libelle', 'ASC')->get()->getResultArray();
    }

    private function services(): array
    {
        return db_connect()->table('services')->orderBy('nom', 'ASC')->get()->getResultArray();
    }

    private function utilisateursListe(?int $exclure = null): array
    {
        $builder = db_connect()->table('utilisateurs u')
            ->select('u.id, u.nom, u.prenom, u.email', false)
            ->orderBy('u.nom', 'ASC');
        if ($exclure !== null) {
            $builder->where('u.id !=', $exclure);
        }

        $result = $builder->get()->getResultArray();
        foreach ($result as &$r) {
            $r['nom_complet'] = trim(($r['prenom'] ?? '') . ' ' . $r['nom']);
        }
        unset($r);

        return $result;
    }

    private function emailExiste($db, string $email, ?int $id = null): bool
    {
        $builder = $db->table('utilisateurs')->where('email', $email);
        if ($id !== null) {
            $builder->where('id !=', $id);
        }

        return $builder->countAllResults() > 0;
    }

    private function genererMotDePasse(): string
    {
        return 'Tmp' . bin2hex(random_bytes(3)) . '!';
    }

    private function instantane(array $utilisateur): array
    {
        return [
            'nom'          => $utilisateur['nom'],
            'prenom'       => $utilisateur['prenom'],
            'email'        => $utilisateur['email'],
            'role_id'      => (int) $utilisateur['role_id'],
            'service_id'   => $utilisateur['service_id'] === null ? null : (int) $utilisateur['service_id'],
            'superieur_id' => $utilisateur['superieur_id'] === null ? null : (int) $utilisateur['superieur_id'],
            'actif'        => (int) $utilisateur['actif'],
        ];
    }

    private function nettoyerPourAudit(array $donnees): array
    {
        unset($donnees['mot_de_passe']);

        return $donnees;
    }

    private function introuvable()
    {
        return redirect()->to(site_url('utilisateurs'))->with('error', 'Utilisateur introuvable.');
    }
}
