<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// Redirection vers le tableau de bord si connecté (géré par AuthFilter)
// $routes->get('/', 'Home::index');

// =========================================================
// BLOC OLIVIER : authentification et socle (O1)
// =========================================================
$routes->get('connexion', 'Auth::connexion');
$routes->post('connexion', 'Auth::authentifier');
$routes->get('deconnexion', 'Auth::deconnexion');

// Accueil = tableau de bord (O6)
$routes->get('/', 'Dashboard::index', ['filter' => 'auth']);

// =========================================================
// BLOC OLIVIER : tableau de bord (O6)
// =========================================================
$routes->group('dashboard', ['filter' => 'auth'], function ($routes) {
    $routes->get('/', 'Dashboard::index');
    $routes->get('echeances-json', 'Dashboard::echeancesJson');
});

// =========================================================
// BLOC OLIVIER : alertes (O3)
// =========================================================
$routes->group('alertes', ['filter' => 'auth'], function ($routes) {
    $routes->get('/', 'Alertes::index');
    $routes->post('lue/(:num)', 'Alertes::lue/$1');
    $routes->post('traitee/(:num)', 'Alertes::traitee/$1');
    $routes->post('toutes-lues', 'Alertes::toutesLues');
    $routes->post('traiter-contrat/(:num)', 'Alertes::traiterContrat/$1');
});

// =========================================================
// BLOC OLIVIER : contrats (O2)
// =========================================================
// Lecture et export PDF : tous les rôles
$routes->group('contrats', ['filter' => 'role:admin,juriste,responsable,direction,lecteur'], function ($routes) {
    $routes->get('/', 'Contrats::index');
    $routes->get('voir/(:num)', 'Contrats::voir/$1');
    $routes->get('pdf/(:num)', 'Contrats::pdf/$1');
});

// Écriture : admin, juriste, responsable
$routes->group('contrats', ['filter' => 'role:admin,juriste,responsable'], function ($routes) {
    $routes->get('nouveau', 'Contrats::nouveau');
    $routes->post('enregistrer', 'Contrats::enregistrer');
    $routes->get('modifier/(:num)', 'Contrats::modifier/$1');
    $routes->post('mettre-a-jour/(:num)', 'Contrats::mettreAJour/$1');
    $routes->post('transition/(:num)', 'Contrats::transition/$1');
    $routes->post('renouveler/(:num)', 'Contrats::renouveler/$1');
    $routes->post('clauses/ajouter/(:num)', 'Contrats::ajouterClause/$1');
    $routes->post('clauses/supprimer/(:num)', 'Contrats::supprimerClause/$1');
});

// =========================================================
// BLOC OLIVIER : litiges (O4)
// =========================================================
// Lecture : tous les rôles
$routes->group('litiges', ['filter' => 'role:admin,juriste,responsable,direction,lecteur'], function ($routes) {
    $routes->get('/', 'Litiges::index');
    $routes->get('voir/(:num)', 'Litiges::voir/$1');
});

// Création : admin, juriste, responsable
$routes->group('litiges', ['filter' => 'role:admin,juriste,responsable'], function ($routes) {
    $routes->get('nouveau', 'Litiges::nouveau');
    $routes->post('enregistrer', 'Litiges::enregistrer');
});

// Transitions : le rôle exact est contrôlé par litige_transitions (role_requis)
$routes->group('litiges', ['filter' => 'role:admin,juriste,responsable,direction'], function ($routes) {
    $routes->post('transition/(:num)', 'Litiges::transition/$1');
});

// =========================================================
// BLOC OLIVIER : module IA (O5)
// =========================================================
$routes->group('ia', ['filter' => 'role:admin,juriste,responsable'], function ($routes) {
    $routes->get('/', 'Ia::index');
    $routes->post('televerser', 'Ia::televerser');
    $routes->post('relancer', 'Ia::relancerPost');
    $routes->post('relancer/(:num)', 'Ia::relancer/$1');
    $routes->get('valider/(:num)', 'Ia::valider/$1');
    $routes->post('creer-contrat/(:num)', 'Ia::creerContrat/$1');
    $routes->post('rejeter/(:num)', 'Ia::rejeter/$1');
});

// =========================================================
// BLOC OLIVIER : fiche d'analyse IA (O7)
// =========================================================
// Lecture et PDF : tous les rôles
$routes->group('analyse', ['filter' => 'role:admin,juriste,responsable,direction,lecteur'], function ($routes) {
    $routes->get('fiche/(:num)', 'Analyse::fiche/$1');
    $routes->get('pdf/(:num)', 'Analyse::pdf/$1');
});

// Recommandation et score : admin, juriste, responsable
$routes->group('analyse', ['filter' => 'role:admin,juriste,responsable'], function ($routes) {
    $routes->post('recommander/(:num)', 'Analyse::recommander/$1');
    $routes->post('recalculer/(:num)', 'Analyse::recalculer/$1');
});

// Décision humaine : la direction peut aussi décider
$routes->group('analyse', ['filter' => 'role:admin,juriste,responsable,direction'], function ($routes) {
    $routes->post('decider/(:num)', 'Analyse::decider/$1');
});

// =========================================================
// BLOC TSIORY (protégé par AuthFilter / RoleFilter)
// =========================================================
// Routes de test (T1)
$routes->get('demo', 'Demo::index');
$routes->get('demo/gabarit', 'Demo::gabarit');
$routes->get('demo/audit', 'Demo::audit');

$routes->get('audit', 'Audit::index', ['filter' => 'role:admin,direction']);

$routes->group('services', ['filter' => 'role:admin,juriste,responsable'], function ($routes) {
    $routes->get('/', 'Services::index');
    $routes->get('nouveau', 'Services::nouveau');
    $routes->post('enregistrer', 'Services::enregistrer');
    $routes->get('modifier/(:num)', 'Services::modifier/$1');
    $routes->post('mettre-a-jour/(:num)', 'Services::mettreAJour/$1');
    $routes->post('supprimer/(:num)', 'Services::supprimer/$1');
});

$routes->group('exports', ['filter' => 'auth'], function ($routes) {
    $routes->get('fournisseurs', 'Exports::fournisseurs');
    $routes->get('contrats', 'Exports::contrats');
    $routes->get('penalites', 'Exports::penalites');
    $routes->get('obligations', 'Exports::obligations');
    $routes->get('echeances', 'Exports::echeances');
});

$routes->group('fournisseurs', ['filter' => 'role:admin,juriste,responsable'], function ($routes) {
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

$routes->group('types-contrat', ['filter' => 'role:admin,juriste'], function ($routes) {
    $routes->get('/', 'TypesContrat::index');
    $routes->get('nouveau', 'TypesContrat::nouveau');
    $routes->post('enregistrer', 'TypesContrat::enregistrer');
    $routes->get('modifier/(:num)', 'TypesContrat::modifier/$1');
    $routes->post('mettre-a-jour/(:num)', 'TypesContrat::mettreAJour/$1');
    $routes->get('activer/(:num)', 'TypesContrat::activer/$1');
    $routes->get('desactiver/(:num)', 'TypesContrat::desactiver/$1');
});

$routes->group('alertes-regles', ['filter' => 'role:admin'], function ($routes) {
    $routes->get('/', 'AlertesRegles::index');
    $routes->get('modifier/(:num)', 'AlertesRegles::modifier/$1');
    $routes->post('mettre-a-jour/(:num)', 'AlertesRegles::mettreAJour/$1');
});

$routes->group('obligations', ['filter' => 'role:admin,juriste,responsable'], function ($routes) {
    $routes->get('/', 'Obligations::index');
    $routes->get('nouveau', 'Obligations::nouveau');
    $routes->post('enregistrer', 'Obligations::enregistrer');
    $routes->get('modifier/(:num)', 'Obligations::modifier/$1');
    $routes->post('mettre-a-jour/(:num)', 'Obligations::mettreAJour/$1');
    $routes->post('changer-statut/(:num)', 'Obligations::changerStatut/$1');
    $routes->post('supprimer/(:num)', 'Obligations::supprimer/$1');
});

$routes->group('penalites', ['filter' => 'role:admin,juriste,responsable,direction'], function ($routes) {
    $routes->get('/', 'Penalites::index');
    $routes->get('nouveau', 'Penalites::nouveau');
    $routes->post('enregistrer', 'Penalites::enregistrer');
    $routes->get('modifier/(:num)', 'Penalites::modifier/$1');
    $routes->post('mettre-a-jour/(:num)', 'Penalites::mettreAJour/$1');
    $routes->post('supprimer/(:num)', 'Penalites::supprimer/$1');
});

$routes->group('utilisateurs', ['filter' => 'role:admin'], function ($routes) {
    $routes->get('/', 'Utilisateurs::index');
    $routes->get('nouveau', 'Utilisateurs::nouveau');
    $routes->post('enregistrer', 'Utilisateurs::enregistrer');
    $routes->get('modifier/(:num)', 'Utilisateurs::modifier/$1');
    $routes->post('mettre-a-jour/(:num)', 'Utilisateurs::mettreAJour/$1');
    $routes->get('reinitialiser-mot-de-passe/(:num)', 'Utilisateurs::reinitialiserMotDePasse/$1');
    $routes->get('desactiver/(:num)', 'Utilisateurs::desactiver/$1');
    $routes->get('activer/(:num)', 'Utilisateurs::activer/$1');
});
