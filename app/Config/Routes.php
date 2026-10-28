<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');
// ---- Tsiory : routes de test (T1), à supprimer avant le rendu ----
$routes->get('demo', 'Demo::index');
$routes->get('demo/gabarit', 'Demo::gabarit');
$routes->get('audit', 'Audit::index');
$routes->get('demo/audit', 'Demo::audit');   // test, à supprimer ensuite
$routes->group('services', function ($routes) {
    $routes->get('/', 'Services::index');
    $routes->get('nouveau', 'Services::nouveau');
    $routes->post('enregistrer', 'Services::enregistrer');
    $routes->get('modifier/(:num)', 'Services::modifier/$1');
    $routes->post('mettre-a-jour/(:num)', 'Services::mettreAJour/$1');
    $routes->post('supprimer/(:num)', 'Services::supprimer/$1');
});
$routes->group('exports', function ($routes) {
    $routes->get('fournisseurs', 'Exports::fournisseurs');
    $routes->get('contrats', 'Exports::contrats');
    $routes->get('penalites', 'Exports::penalites');
    $routes->get('obligations', 'Exports::obligations');
    $routes->get('echeances', 'Exports::echeances');
});
$routes->group('fournisseurs', function ($routes) {
         $routes->get('/', 'Fournisseurs::index');
         $routes->get('nouveau', 'Fournisseurs::nouveau');
         $routes->post('enregistrer', 'Fournisseurs::enregistrer');
         $routes->get('voir/(:num)', 'Fournisseurs::voir/$1');
         $routes->get('modifier/(:num)', 'Fournisseurs::modifier/$1');
         $routes->post('mettre-a-jour/(:num)', 'Fournisseurs::mettreAJour/$1');
         $routes->post('supprimer/(:num)', 'Fournisseurs::supprimer/$1');
         $routes->get('contacts/(:num)', 'FournisseurContacts::liste/$1');
         $routes->get('contacts/nouveau/(:num)', 'FournisseurContacts::nouveau/$1');
         $routes->post('contacts/enregistrer/(:num)', 'FournisseurContacts::enregistrer/$1');
         $routes->get('contacts/modifier/(:num)', 'FournisseurContacts::modifier/$1');
         $routes->post('contacts/mettre-a-jour/(:num)', 'FournisseurContacts::mettreAJour/$1');
         $routes->post('contacts/supprimer/(:num)', 'FournisseurContacts::supprimer/$1');
         $routes->get('evaluations/(:num)', 'FournisseurEvaluations::liste/$1');
         $routes->get('evaluations/nouveau/(:num)', 'FournisseurEvaluations::nouveau/$1');
         $routes->post('evaluations/enregistrer/(:num)', 'FournisseurEvaluations::enregistrer/$1');
         $routes->post('evaluations/supprimer/(:num)', 'FournisseurEvaluations::supprimer/$1');
     });
$routes->group('types-contrat', function ($routes) {
    $routes->get('/', 'TypesContrat::index');
    $routes->get('nouveau', 'TypesContrat::nouveau');
    $routes->post('enregistrer', 'TypesContrat::enregistrer');
    $routes->get('modifier/(:num)', 'TypesContrat::modifier/$1');
    $routes->post('mettre-a-jour/(:num)', 'TypesContrat::mettreAJour/$1');
    $routes->get('activer/(:num)', 'TypesContrat::activer/$1');
    $routes->get('desactiver/(:num)', 'TypesContrat::desactiver/$1');
});
$routes->group('alertes-regles', function ($routes) {
    $routes->get('/', 'AlertesRegles::index');
    $routes->get('modifier/(:num)', 'AlertesRegles::modifier/$1');
    $routes->post('mettre-a-jour/(:num)', 'AlertesRegles::mettreAJour/$1');
});
$routes->group('obligations', function ($routes) {
    $routes->get('/', 'Obligations::index');
    $routes->get('nouveau', 'Obligations::nouveau');
    $routes->post('enregistrer', 'Obligations::enregistrer');
    $routes->get('modifier/(:num)', 'Obligations::modifier/$1');
    $routes->post('mettre-a-jour/(:num)', 'Obligations::mettreAJour/$1');
    $routes->post('changer-statut/(:num)', 'Obligations::changerStatut/$1');
    $routes->post('supprimer/(:num)', 'Obligations::supprimer/$1');
});
$routes->group('penalites', function ($routes) {
    $routes->get('/', 'Penalites::index');
    $routes->get('nouveau', 'Penalites::nouveau');
    $routes->post('enregistrer', 'Penalites::enregistrer');
    $routes->get('modifier/(:num)', 'Penalites::modifier/$1');
    $routes->post('mettre-a-jour/(:num)', 'Penalites::mettreAJour/$1');
    $routes->post('supprimer/(:num)', 'Penalites::supprimer/$1');
});
$routes->group('utilisateurs', function ($routes) {
    $routes->get('/', 'Utilisateurs::index');
    $routes->get('nouveau', 'Utilisateurs::nouveau');
    $routes->post('enregistrer', 'Utilisateurs::enregistrer');
    $routes->get('modifier/(:num)', 'Utilisateurs::modifier/$1');
    $routes->post('mettre-a-jour/(:num)', 'Utilisateurs::mettreAJour/$1');
    $routes->get('reinitialiser-mot-de-passe/(:num)', 'Utilisateurs::reinitialiserMotDePasse/$1');
    $routes->get('desactiver/(:num)', 'Utilisateurs::desactiver/$1');
    $routes->get('activer/(:num)', 'Utilisateurs::activer/$1');
});