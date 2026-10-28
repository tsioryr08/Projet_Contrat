<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php helper('form'); ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <span class="text-muted">Les règles de départ (J30, J10, J0) ne peuvent pas être supprimées.</span>
</div>

<div class="table-responsive">
    <table class="table table-striped align-middle bg-white">
        <thead class="table-light">
            <tr>
                <th>Code</th><th>Jours avant</th><th>Gravité</th><th>Notifier le supérieur</th><th>État</th><th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($regles)): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">Aucune règle d'alerte.</td></tr>
        <?php endif; ?>

        <?php foreach ($regles as $r): ?>
            <tr>
                <td><strong><?= esc($r['code']) ?></strong></td>
                <td><?= (int) $r['jours_avant'] ?> jour(s)</td>
                <td><?= esc($gravites[$r['gravite']] ?? $r['gravite']) ?></td>
                <td><?= ((int) $r['notifier_superieur'] === 1) ? 'Oui' : 'Non' ?></td>
                <td><?= ((int) $r['actif'] === 1) ? '<span class="badge bg-success">Actif</span>' : '<span class="badge bg-secondary">Inactif</span>' ?></td>
                <td class="text-end text-nowrap">
                    <a class="btn btn-sm btn-outline-primary" href="<?= site_url('alertes-regles/modifier/' . (int) $r['id']) ?>">Modifier</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?= $this->endSection() ?>
