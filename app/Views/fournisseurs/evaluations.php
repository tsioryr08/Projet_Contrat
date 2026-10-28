<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php helper('form'); ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <a href="<?= site_url('fournisseurs/voir/' . (int) $fournisseur['id']) ?>" class="btn btn-link px-0">← Retour à la fiche fournisseur</a>
    <span class="text-muted"><?= esc($fournisseur['raison_sociale']) ?></span>
</div>

<?php if (! empty($stats)): ?>
<div class="alert alert-info d-flex justify-content-between align-items-center">
    <div>
        <strong>Moyenne :</strong> <?= $stats['moyenne'] !== null ? esc((string) $stats['moyenne']) . ' / 5' : 'Aucune évaluation' ?>
    </div>
    <div>
        <strong>Nombre d\'évaluations :</strong> <?= (int) $stats['nb'] ?>
    </div>
</div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">Nouvelle évaluation</div>
            <div class="card-body">
                <form method="post" action="<?= esc($action, 'attr') ?>" novalidate>
                    <?= csrf_field() ?>

                    <div class="mb-4">
                        <label class="form-label" for="note">Note <span class="text-danger">*</span></label>
                        <select class="form-select" id="note" name="note" required>
                            <option value="">— Sélectionner —</option>
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <option value="<?= $i ?>" <?= old('note') == $i ? 'selected' : '' ?>><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                        <div class="form-text">Note entière entre 1 et 5.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="date_evaluation">Date d\'évaluation <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="date_evaluation" name="date_evaluation" required
                               value="<?= esc(old('date_evaluation', $aujourdhui), 'attr') ?>">
                    </div>

                    <div class="mb-4">
                        <label class="form-label" for="commentaire">Commentaire</label>
                        <textarea class="form-control" id="commentaire" name="commentaire" rows="4" maxlength="1000"><?= esc(old('commentaire'), 'attr') ?></textarea>
                        <div class="form-text">Maximum 1000 caractères.</div>
                    </div>

                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                    <a class="btn btn-outline-secondary" href="<?= site_url('fournisseurs/voir/' . (int) $fournisseur['id']) ?>">Annuler</a>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">Historique des évaluations (<?= count($evaluations) ?>)</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th><th>Note</th><th>Évaluateur</th><th>Commentaire</th><th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($evaluations)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-3">Aucune évaluation pour ce fournisseur.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($evaluations as $e): ?>
                        <tr>
                            <td class="text-nowrap"><?= esc(date('d/m/Y', strtotime($e['date_evaluation']))) ?></td>
                            <td><span class="badge bg-primary"><?= (int) $e['note'] ?>/5</span></td>
                            <td><?= $e['evaluateur_nom'] ? esc($e['evaluateur_nom']) : '<span class="text-muted">—</span>' ?></td>
                            <td><?= $e['commentaire'] ? nl2br(esc($e['commentaire'])) : '<span class="text-muted">—</span>' ?></td>
                            <td class="text-end text-nowrap">
                                <button type="button" class="btn btn-sm btn-outline-danger"
                                        data-confirm-url="<?= site_url('fournisseurs/evaluations/supprimer/' . (int) $e['id']) ?>"
                                        data-confirm-text="<?= esc('Supprimer cette évaluation ?', 'attr') ?>">
                                    Supprimer
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
