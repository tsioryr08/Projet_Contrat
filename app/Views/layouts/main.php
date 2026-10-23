<?php
helper(['ui', 'url']);

// Variables de session à convenir avec Olivier (O1) : 'role' et 'utilisateur_nom'.
$role  = session()->get('role') ?? 'lecteur';
$nom   = session()->get('utilisateur_nom') ?? 'Invité';
$titre = $titre ?? 'Suivi des contrats';
$menu  = menu_pour_role($role);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($titre) ?> – Suivi des contrats</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= base_url('css/app.css') ?>">
    <?= $this->renderSection('styles') ?>
</head>
<body class="d-flex flex-column min-vh-100">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?= site_url('/') ?>">Suivi des contrats</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                data-bs-target="#menuPrincipal" aria-controls="menuPrincipal"
                aria-expanded="false" aria-label="Afficher le menu">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="menuPrincipal">
            <ul class="navbar-nav me-auto">
                <?php foreach ($menu as $groupe => $entrees): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button"
                           data-bs-toggle="dropdown" aria-expanded="false"><?= esc($groupe) ?></a>
                        <ul class="dropdown-menu">
                            <?php foreach ($entrees as [$libelle, $chemin]): ?>
                                <li>
                                    <a class="dropdown-item <?= url_is($chemin . '*') ? 'active' : '' ?>"
                                       href="<?= site_url($chemin) ?>"><?= esc($libelle) ?></a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                <?php endforeach; ?>
            </ul>
            <span class="navbar-text me-3"><?= esc($nom) ?> (<?= esc($role) ?>)</span>
            <a class="btn btn-outline-light btn-sm" href="<?= site_url('deconnexion') ?>">Déconnexion</a>
        </div>
    </div>
</nav>

<main class="container-fluid py-4 flex-grow-1">
    <?php if ($m = session()->getFlashdata('success')): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= esc($m) ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
        </div>
    <?php endif; ?>
    <?php if ($m = session()->getFlashdata('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= esc($m) ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
        </div>
    <?php endif; ?>
    <?php if ($erreurs = session()->getFlashdata('errors')): ?>
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0">
                <?php foreach ((array) $erreurs as $e): ?><li><?= esc($e) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <h1 class="h3 mb-4"><?= esc($titre) ?></h1>

    <?= $this->renderSection('content') ?>
</main>

<footer class="bg-light text-center text-muted py-3 small">
    Suivi des contrats fournisseurs – projet LegalTech
</footer>

<?= view('partials/confirm_suppression') ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= base_url('js/app.js') ?>"></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
