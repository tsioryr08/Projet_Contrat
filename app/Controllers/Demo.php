<?php

namespace App\Controllers;

/** Page d'exemple du gabarit (T1) et test du journal d'audit (T2). À supprimer avant le rendu final. */
class Demo extends BaseController
{
    /** Simule un utilisateur connecté, en attendant la connexion d'Olivier. */
    private function simulerConnexion(): void
    {
        session()->set([
            'role'            => 'admin',
            'utilisateur_nom' => 'Hanta Rakotoarisoa',
            'utilisateur_id'  => 1,   // admin@suivi.test, créé par le seeder
        ]);
    }

    public function index()
    {
        $this->simulerConnexion();

        // Les messages flash ne s'affichent qu'à la requête suivante : on redirige.
        session()->setFlashdata('success', 'Exemple de message de succès.');
        session()->setFlashdata('error', 'Exemple de message d\'erreur.');

        return redirect()->to(site_url('demo/gabarit'));
    }

    public function gabarit()
    {
        $this->simulerConnexion();

        return view('demo/gabarit', ['titre' => 'Page de test du gabarit']);
    }

    public function audit()
    {
        $this->simulerConnexion();

        helper('audit');
        audit_log(
            'modification',
            'fournisseurs',
            1,
            ['raison_sociale' => 'Ancien nom', 'mot_de_passe' => 'secret'],
            ['raison_sociale' => 'Nouveau nom', 'mot_de_passe' => 'secret2']
        );

        return redirect()->to(site_url('audit'))
            ->with('success', 'Une ligne de test a été ajoutée au journal.');
    }
}