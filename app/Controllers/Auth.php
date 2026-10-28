<?php

namespace App\Controllers;

use App\Models\UtilisateurModel;

class Auth extends BaseController
{
    public function connexion()
    {
        if (session()->get('utilisateur_id')) {
            return redirect()->to('/');
        }
        return view('auth/connexion');
    }

    public function authentifier()
    {
        $regles = [
            'email'        => 'required|valid_email',
            'mot_de_passe' => 'required',
        ];
        $messages = [
            'email' => [
                'required'    => "L'adresse e-mail est obligatoire.",
                'valid_email' => "L'adresse e-mail n'est pas valide.",
            ],
            'mot_de_passe' => [
                'required' => 'Le mot de passe est obligatoire.',
            ],
        ];

        if (! $this->validate($regles, $messages)) {
            return redirect()->back()->withInput()->with('erreurs', $this->validator->getErrors());
        }

        $email = trim((string) $this->request->getPost('email'));
        $mdp   = (string) $this->request->getPost('mot_de_passe');

        $utilisateur = (new UtilisateurModel())->trouverParEmail($email);

        // Message volontairement identique pour ne pas révéler si le compte existe
        if (! $utilisateur || ! password_verify($mdp, $utilisateur['mot_de_passe'])) {
            return redirect()->back()->withInput()
                ->with('erreur', 'Identifiants incorrects.');
        }

        if ((int) $utilisateur['actif'] !== 1) {
            return redirect()->back()->withInput()
                ->with('erreur', "Ce compte est désactivé. Contactez l'administrateur.");
        }

        // Anti fixation de session
        session()->regenerate(true);

        session()->set([
            'utilisateur_id'  => (int) $utilisateur['id'],
            'utilisateur_nom' => trim(($utilisateur['prenom'] ?? '') . ' ' . $utilisateur['nom']),
            'role'            => $utilisateur['role_code'],
            'service_id'      => $utilisateur['service_id'] ?? null,
        ]);

        if (function_exists('audit_log')) {
            audit_log('connexion', 'utilisateurs', (int) $utilisateur['id']);
        }

        $cible = session()->get('url_demandee') ?: '/';
        session()->remove('url_demandee');

        return redirect()->to($cible);
    }

    public function deconnexion()
    {
        if (session()->get('utilisateur_id') && function_exists('audit_log')) {
            audit_log('deconnexion', 'utilisateurs', (int) session()->get('utilisateur_id'));
        }

        session()->destroy();

        return redirect()->to('/connexion')->with('succes', 'Vous êtes déconnecté.');
    }
}