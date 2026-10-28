<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php helper('form', 'ui'); ?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <form method="get" action="<?= site_url('obligations') ?>" class="d-flex flex-wrap gap-2">
        <input type="search" name="q" class="form-control" style="max-width: 260px;" placeholder="Rechercher..."
               value="<?= esc($q, 'attr') ?>">
        <select name="statut" class="form-select" style="max-width: 200px;">
            <option value="">Tous les statuts</option>
            <?php foreach ($statuts as $code => $libelle): ?>
                <option value="<?= esc($code, 'attr') ?>" <?= $statut === $code ? 'selected' : '' ?>><?= esc($libelle) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="contrat_id" class="form-select" style="max-width: 260px;">
            <option value="">Tous les contrats</option>
            <?php foreach ($contrats as $c): ?>
                <option value="<?= (int) $c['id'] ?>" <?= (string) $contratId === (string) $c['id'] ? 'selected' : '' ?>>
                    <?= esc($c['reference']) ?> - <?= isset($c['fournisseur_nom']) ? esc($c['fournisseur_nom']) : '' ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-outline-secondary" type="submit">Filtrer</button>
        <a class="btn btn-outline-secondary" href="<?= site_url('obligations') ?>">Réinitialiser</a>
    </form>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-success" href="<?= site_url('exports/obligations?' . http_build_query(['q' => $q, 'statut' => $statut, 'contrat_id' => $contratId])) ?>">Exporter obligations CSV</a>
        <a class="btn btn-primary" href="<?= site_url('obligations/nouveau') ?>">Nouvelle obligation</a>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-striped align-middle bg-white">
        <thead class="table-light">
            <tr>
                <th>Contrat</th><th>Fournisseur</th><th>Débiteur / Créancier</th><th>Type</th><th>Description</th><th>Montant</th><th>Échéance</th><th>Statut</th><th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($lignes)): ?>
            <tr><td colspan="9" class="text-center text-muted py-4">Aucune obligation.</td></tr>
        <?php endif; ?>

        <?php foreach ($lignes as $o): ?>
            <?php $enRetard = $o['statut'] !== 'executee' && $o['statut'] !== 'annulee' && strtotime($o['echeance']) < strtotime(date('Y-m-d')); ?>
            <tr class="<?= $enRetard ? 'table-warning' : '' ?>">
                <td class="text-nowrap">
                    <a href="<?= site_url('contrats/voir/' . (int) $o['contrat_id']) ?>"><?= esc($o['contrat_reference']) ?></a><br>
                    <small class="text-muted"><?= esc($o['contrat_intitule']) ?></small>
                </td>
                <td><?= isset($o['fournisseur_nom']) ? esc($o['fournisseur_nom']) : (isset($o['raison_sociale']) ? esc($o['raison_sociale']) : '') ?></td>
                <td>
                    <div><strong>D:</strong> <?= esc($o['debiteur']) ?></div>
                    <div><strong>C:</strong> <?= esc($o['creancier']) ?></div>
                </td>
                <td><?= esc($types[$o['type_obligation']] ?? $o['type_obligation']) ?></td>
                <td><?= $o['description'] ? esc($o['description']) : '<span class="text-muted">—</span>' ?></td>
                <td class="text-nowrap"><?= $o['montant'] !== null ? number_format((float) $o['montant'], 0, ',', ' ') . ' Ar' : '<span class="text-muted">—</span>' ?></td>
                <td class="text-nowrap"><?= date_fr($o['echeance']) ?><?= $enRetard ? ' <span class="text-danger">*</span>' : '' ?></td>
                <td>
                    <?php if ($o['statut'] === 'en_retard'): ?>
                        <span class="badge bg-danger">En retard</span>
                    <?php else: ?>
                        <form method="post" action="<?= site_url('obligations/changer-statut/' . (int) $o['id']) ?>" class="d-flex">
                            <?= csrf_field() ?>
                            <select name="statut" class="form-select form-select-sm" onchange="this.form.submit()">
                                <?php 
                                $statutsList = $statuts;
                                unset($statutsList['en_retard']);
                                ?>
                                <?php foreach ($statutsList as $code => $libelle): ?>
                                    <option value="<?= esc($code, 'attr') ?>" <?= $o['statut'] === $code ? 'selected' : '' ?>><?= esc($libelle) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                        <?php if ($o['statut'] === 'executee' && $o['executee_le']): ?>
                            <small class="text-muted">Le <?= date_fr($o['executee_le']) ?></small>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
                <td class="text-end text-nowrap">
                    <a class="btn btn-sm btn-outline-primary" href="<?= site_url('obligations/modifier/' . (int) $o['id']) ?>">Modifier</a>
                    <button type="button" class="btn btn-sm btn-outline-danger"
                            data-confirm-url="<?= site_url('obligations/supprimer/' . (int) $o['id']) ?>"
                            data-confirm-text="<?= esc('Supprimer l\'obligation ?', 'attr') ?>">
                        Supprimer
                    </button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<div class="text-muted small">* Échéance dépassée et obligation non exécutée</div>

<?= $this->endSection() ?>
