<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php helper('form'); ?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <form method="get" action="<?= site_url('utilisateurs') ?>" class="d-flex gap-2">
        <input type="search" name="q" class="form-control" placeholder="Rechercher..."
               value="<?= esc($q, 'attr') ?>">
        <button class="btn btn-outline-secondary" type="submit">Rechercher</button>
    </form>
    <a class="btn btn-primary" href="<?= site_url('utilisateurs/nouveau') ?>">Nouvel utilisateur</a>
</div>

<div class="table-responsive">
    <table class="table table-striped align-middle bg-white">
        <thead class="table-light">
            <tr>
                <th>Nom et prénom</th><th>E-mail</th><th>Rôle</th><th>Service</th><th>Supérieur hiérarchique</th><th>État</th><th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($lignes)): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">Aucun utilisateur.</td></tr>
        <?php endif; ?>

        <?php foreach ($lignes as $u): ?>
            <tr>
                <td><?= esc(trim(($u['prenom'] ?? '') . ' ' . $u['nom'])) ?></td>
                <td><?= esc($u['email']) ?></td>
                <td><?= esc($u['role_libelle']) ?></td>
                <td><?= $u['service_nom'] ? esc($u['service_nom']) : '<span class="text-muted">—</span>' ?></td>
                <td><?= $u['superieur_nom'] ? esc($u['superieur_nom']) : '<span class="text-muted">—</span>' ?></td>
                <td><?= ((int) $u['actif'] === 1) ? '<span class="badge bg-success">Actif</span>' : '<span class="badge bg-secondary">Inactif</span>' ?></td>
                <td class="text-end text-nowrap">
                    <a class="btn btn-sm btn-outline-primary" href="<?= site_url('utilisateurs/modifier/' . (int) $u['id']) ?>">Modifier</a>
                    <?php if ((int) $u['actif'] === 1): ?>
                        <a class="btn btn-sm btn-outline-warning" href="<?= site_url('utilisateurs/desactiver/' . (int) $u['id']) ?>" onclick="return confirm('Désactiver cet utilisateur ?');">Désactiver</a>
                    <?php else: ?>
                        <a class="btn btn-sm btn-outline-success" href="<?= site_url('utilisateurs/activer/' . (int) $u['id']) ?>" onclick="return confirm('Activer cet utilisateur ?');">Activer</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?= $this->endSection() ?>
