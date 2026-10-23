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