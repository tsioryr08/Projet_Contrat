<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion - Suivi des contrats</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container" style="max-width: 420px; margin-top: 10vh;">
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <h1 class="h4 mb-1">Suivi des contrats</h1>
            <p class="text-muted">Connexion</p>

            <?php if (session()->getFlashdata('erreur')): ?>
                <div class="alert alert-danger"><?= esc(session()->getFlashdata('erreur')) ?></div>
            <?php endif; ?>
            <?php if (session()->getFlashdata('succes')): ?>
                <div class="alert alert-success"><?= esc(session()->getFlashdata('succes')) ?></div>
            <?php endif; ?>
            <?php if (session()->getFlashdata('erreurs')): ?>
                <div class="alert alert-danger mb-3">
                    <?php foreach (session()->getFlashdata('erreurs') as $e): ?>
                        <div><?= esc($e) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form action="<?= base_url('connexion') ?>" method="post">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label for="email" class="form-label">Adresse e-mail</label>
                    <input type="email" class="form-control" id="email" name="email"
                           value="<?= esc(old('email')) ?>" required autofocus>
                </div>
                <div class="mb-3">
                    <label for="mot_de_passe" class="form-label">Mot de passe</label>
                    <input type="password" class="form-control" id="mot_de_passe" name="mot_de_passe" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Se connecter</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>