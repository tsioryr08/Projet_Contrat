<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php $classesStatut = ['actif' => 'bg-success', 'suspendu' => 'bg-warning text-dark', 'blackliste' => 'bg-dark']; ?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <form method="get" action="<?= site_url('fournisseurs') ?>" class="d-flex flex-wrap gap-2">
        <input type="search" name="q" class="form-control" style="width: 18rem;"
               placeholder="Raison sociale ou NIF" value="<?= esc($q, 'attr') ?>">
        <select name="statut" class="form-select" style="width: 11rem;">
            <option value="">Tous les statuts</option>
            <?php foreach ($statuts as $code => $libelle): ?>
                <option value="<?= esc($code, 'attr') ?>" <?= $statut === $code ? 'selected' : '' ?>><?= esc($libelle) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-outline-secondary" type="submit">Filtrer</button>
        <a class="btn btn-link" href="<?= site_url('fournisseurs') ?>">Réinitialiser</a>
    </form>
    <a class="btn btn-primary" href="<?= site_url('fournisseurs/nouveau') ?>">Nouveau fournisseur</a>
</div>

<p class="text-muted small"><?= (int) $pager->getTotal() ?> fournisseur(s)</p>

<div class="table-responsive">
    <table class="table table-striped align-middle bg-white">
        <thead class="table-light">
            <tr>
                <th>Raison sociale</th><th>NIF</th><th>Ville</th><th>Statut</th>
                <th class="text-center">Contrats</th><th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($lignes)): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">Aucun fournisseur.</td></tr>
        <?php endif; ?>

        <?php foreach ($lignes as $f): ?>
            <tr>
                <td><a href="<?= site_url('fournisseurs/voir/' . (int) $f['id']) ?>"><?= esc($f['raison_sociale']) ?></a></td>
                <td><?= $f['nif'] ? esc($f['nif']) : '<span class="text-muted">—</span>' ?></td>
                <td><?= esc($f['ville'] ?? '') ?></td>
                <td><span class="badge <?= $classesStatut[$f['statut']] ?? 'bg-secondary' ?>"><?= esc($statuts[$f['statut']] ?? $f['statut']) ?></span></td>
                <td class="text-center"><?= (int) $f['nb_contrats'] ?></td>
                <td class="text-end text-nowrap">
                    <a class="btn btn-sm btn-outline-secondary" href="<?= site_url('fournisseurs/voir/' . (int) $f['id']) ?>">Fiche</a>
                    <a class="btn btn-sm btn-outline-primary" href="<?= site_url('fournisseurs/modifier/' . (int) $f['id']) ?>">Modifier</a>
                    <?php if ((int) $f['nb_contrats'] > 0): ?>
                        <span title="Fournisseur lié à des contrats : suppression impossible" class="d-inline-block">
                            <button type="button" class="btn btn-sm btn-outline-danger" disabled>Supprimer</button>
                        </span>
                    <?php else: ?>
                        <button type="button" class="btn btn-sm btn-outline-danger"
                                data-confirm-url="<?= site_url('fournisseurs/supprimer/' . (int) $f['id']) ?>"
                                data-confirm-text="<?= esc('Supprimer le fournisseur « ' . $f['raison_sociale'] . ' » ? Ses contacts et ses évaluations seront aussi supprimés.', 'attr') ?>">
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
