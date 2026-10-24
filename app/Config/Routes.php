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