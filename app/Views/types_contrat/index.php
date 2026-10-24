<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php helper('form'); ?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <form method="get" action="<?= site_url('types-contrat') ?>" class="d-flex gap-2">
        <input type="search" name="q" class="form-control" placeholder="Rechercher par famille ou libellé"
               value="<?= esc($q, 'attr') ?>">
        <button class="btn btn-outline-secondary" type="submit">Rechercher</button>
    </form>
    <a class="btn btn-primary" href="<?= site_url('types-contrat/nouveau') ?>">Nouveau type de contrat</a>
</div>

<?php if (empty($groupes)): ?>
    <div class="alert alert-info">Aucun type de contrat.</div>
<?php endif; ?>

<?php foreach ($groupes as $famille => $lignes): ?>
    <div class="card mb-4">
        <div class="card-header"><?= esc($famille) ?></div>
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Libellé</th><th>Préavis par défaut</th><th>Reconduction tacite</th><th>Durée par défaut</th><th>État</th><th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($lignes as $t): ?>
                    <tr>
                        <td>
                            <strong><?= esc($t['libelle']) ?></strong>
                            <?php if ($t['description']): ?>
                                <div class="text-muted small"><?= esc($t['description']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= (int) $t['preavis_defaut_jours'] ?> jour(s)</td>
                        <td><?= ((int) $t['reconduction_tacite_defaut'] === 1) ? 'Oui' : 'Non' ?></td>
                        <td><?= $t['duree_defaut_mois'] !== null ? (int) $t['duree_defaut_mois'] . ' mois' : '<span class="text-muted">—</span>' ?></td>
                        <td><?= ((int) $t['actif'] === 1) ? '<span class="badge bg-success">Actif</span>' : '<span class="badge bg-secondary">Inactif</span>' ?></td>
        <td class="text-end text-nowrap">
            <a class="btn btn-sm btn-outline-primary" href="<?= site_url('types-contrat/modifier/' . (int) $t['id']) ?>">Modifier</a>
            <?php if ((int) $t['actif'] === 1): ?>
                <a class="btn btn-sm btn-outline-warning" href="<?= site_url('types-contrat/desactiver/' . (int) $t['id']) ?>" onclick="return confirm('Désactiver ce type de contrat ?');">Désactiver</a>
            <?php else: ?>
                <a class="btn btn-sm btn-outline-success" href="<?= site_url('types-contrat/activer/' . (int) $t['id']) ?>" onclick="return confirm('Activer ce type de contrat ?');">Activer</a>
            <?php endif; ?>
        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endforeach; ?>

<?= $this->endSection() ?>
