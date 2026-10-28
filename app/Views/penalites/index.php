<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php helper('form', 'ui'); ?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <form method="get" action="<?= site_url('penalites') ?>" class="d-flex flex-wrap gap-2">
        <input type="search" name="q" class="form-control" style="max-width: 240px;" placeholder="Rechercher..."
               value="<?= esc($q ?? '', 'attr') ?>">
        <select name="statut" class="form-select" style="max-width: 200px;">
            <option value="">Tous les statuts</option>
            <?php foreach ($statuts as $code => $libelle): ?>
                <option value="<?= esc($code, 'attr') ?>" <?= ($statut ?? '') === $code ? 'selected' : '' ?>><?= esc($libelle) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="contrat_id" class="form-select" style="max-width: 260px;">
            <option value="">Tous les contrats</option>
            <?php foreach ($contrats as $c): ?>
                <option value="<?= (int) $c['id'] ?>" <?= (string) ($contratId ?? '') === (string) $c['id'] ? 'selected' : '' ?>>
                    <?= esc($c['reference']) ?> - <?= isset($c['fournisseur_nom']) ? esc($c['fournisseur_nom']) : '' ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-outline-secondary" type="submit">Filtrer</button>
        <a class="btn btn-outline-secondary" href="<?= site_url('penalites') ?>">Réinitialiser</a>
    </form>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-success" href="<?= site_url('exports/penalites?' . http_build_query(['q' => $q ?? '', 'statut' => $statut ?? '', 'contrat_id' => $contratId ?? ''])) ?>">Exporter pénalités CSV</a>
        <a class="btn btn-primary" href="<?= site_url('penalites/nouveau') ?>">Nouvelle pénalité</a>
    </div>
</div>

    <?php if (! empty($totaux)): ?>
<div class="row g-2 mb-3">
    <?php foreach ($statuts as $code => $libelle): ?>
        <?php if (isset($totaux[$code])): ?>
            <div class="col-auto">
                <span class="badge bg-light text-dark border"><?= esc($libelle) ?> : <?= (int) $totaux[$code]['nb'] ?> - <?= number_format((float) $totaux[$code]['total'], 0, ',', ' ') ?> Ar</span>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="table-responsive">
    <table class="table table-striped align-middle bg-white">
        <thead class="table-light">
            <tr>
                <th>Contrat</th><th>Nature</th><th>Montant</th><th>Fait générateur</th><th>Découverte</th><th>Retard</th><th>Statut</th><th>Motif</th><th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($lignes)): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">Aucune pénalité.</td></tr>
            <?php endif; ?>

            <?php foreach ($lignes as $p): ?>
                <?php $retardP = (int) ($p['retard_jours'] ?? 0); ?>
            <tr>
                <td class="text-nowrap">
                    <a href="<?= site_url('contrats/voir/' . (int) $p['contrat_id']) ?>"><?= esc($p['contrat_reference'] ?? '') ?></a><br>
                    <small class="text-muted"><?= isset($p['fournisseur_nom']) ? esc($p['fournisseur_nom']) : (isset($p['fournisseur']) ? esc($p['fournisseur']) : (isset($p['raison_sociale']) ? esc($p['raison_sociale']) : '')) ?></small>
                </td>
                <td><?= $p['nature'] ?></td>
                <td><?= $p['montant'] ?></td>
                <td class="text-nowrap"><?= date_fr($p['date_fait_generateur']) ?></td>
                <td class="text-nowrap"><?= date_fr($p['date_decouverte']) ?></td>
                <td class="<?= $retardP > 30 ? 'text-danger fw-bold' : '' ?>"><?= $retardP ?> jour(s)</td>
                <td><?= isset($statuts[$p['statut']]) ? esc($statuts[$p['statut']]) : esc($p['statut']) ?></td>
                <td><?= $p['motif'] ? $p['motif'] : '<span class="text-muted">—</span>' ?></td>
                <td class="text-end text-nowrap">
                    <a class="btn btn-sm btn-outline-primary" href="<?= site_url('penalites/modifier/' . (int) $p['id']) ?>">Modifier</a>
                    <button type="button" class="btn btn-sm btn-outline-danger"
                            data-confirm-url="<?= site_url('penalites/supprimer/' . (int) $p['id']) ?>"
                            data-confirm-text="<?= esc('Supprimer la pénalité ?', 'attr') ?>">
                        Supprimer
                    </button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?= $this->endSection() ?>
