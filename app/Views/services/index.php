<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <form method="get" action="<?= site_url('services') ?>" class="d-flex gap-2">
        <input type="search" name="q" class="form-control" placeholder="Rechercher un service"
               value="<?= esc($q, 'attr') ?>">
        <button class="btn btn-outline-secondary" type="submit">Rechercher</button>
    </form>
    <a class="btn btn-primary" href="<?= site_url('services/nouveau') ?>">Nouveau service</a>
</div>

<div class="table-responsive">
    <table class="table table-striped align-middle bg-white">
        <thead class="table-light">
            <tr>
                <th>Service</th><th>Responsable</th>
                <th class="text-center">Utilisateurs</th><th class="text-center">Contrats</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($lignes)): ?>
            <tr><td colspan="5" class="text-center text-muted py-4">Aucun service.</td></tr>
        <?php endif; ?>

        <?php foreach ($lignes as $s): ?>
            <?php $utilise = ((int) $s['nb_utilisateurs'] + (int) $s['nb_contrats']) > 0; ?>
            <tr>
                <td><?= esc($s['nom']) ?></td>
                <td><?= $s['responsable_nom'] ? esc($s['responsable_nom']) : '<span class="text-muted">—</span>' ?></td>
                <td class="text-center"><?= (int) $s['nb_utilisateurs'] ?></td>
                <td class="text-center"><?= (int) $s['nb_contrats'] ?></td>
                <td class="text-end text-nowrap">
                    <a class="btn btn-sm btn-outline-primary" href="<?= site_url('services/modifier/' . (int) $s['id']) ?>">Modifier</a>
                    <?php if ($utilise): ?>
                        <span title="Service utilisé : suppression impossible" class="d-inline-block">
                            <button type="button" class="btn btn-sm btn-outline-danger" disabled>Supprimer</button>
                        </span>
                    <?php else: ?>
                        <button type="button" class="btn btn-sm btn-outline-danger"
                                data-confirm-url="<?= site_url('services/supprimer/' . (int) $s['id']) ?>"
                                data-confirm-text="<?= esc('Supprimer le service « ' . $s['nom'] . ' » ?', 'attr') ?>">
                            Supprimer
                        </button>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($pager->getPageCount() > 1): ?>
    <?= view('partials/pagination', ['pager' => $pager]) ?>
<?php endif; ?>

<?= $this->endSection() ?>
