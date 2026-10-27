<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Litiges</h1>
    <?php if ($peutCreer): ?><a href="<?= base_url('litiges/nouveau') ?>" class="btn btn-primary">Nouveau litige</a><?php endif; ?>
</div>

<form method="get" action="<?= base_url('litiges') ?>" class="card card-body mb-3">
    <div class="row g-2">
        <div class="col-md-3">
            <select name="etat" class="form-select">
                <option value="">Tous les états</option>
                <?php foreach ($etats as $c => $l): ?>
                    <option value="<?= esc($c) ?>" <?= $filtres['etat'] === $c ? 'selected' : '' ?>><?= esc($l) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <select name="type" class="form-select">
                <option value="">Tous les types</option>
                <?php foreach ($types as $c => $l): ?>
                    <option value="<?= $c ?>" <?= $filtres['type'] === $c ? 'selected' : '' ?>><?= esc($l) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <select name="responsable_id" class="form-select">
                <option value="">Tous les responsables</option>
                <?php foreach ($responsables as $r): ?>
                    <option value="<?= $r['id'] ?>" <?= (string) $filtres['responsable_id'] === (string) $r['id'] ? 'selected' : '' ?>><?= esc($r['nom']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button class="btn btn-primary flex-fill" type="submit">Filtrer</button>
            <a href="<?= base_url('litiges') ?>" class="btn btn-outline-secondary">Effacer</a>
        </div>
    </div>
</form>

<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead class="table-light">
            <tr><th>Référence</th><th>Contrat</th><th>Type</th><th>État</th><th>Responsable</th>
                <th class="text-end">Montant en jeu</th><th>Ouvert le</th><th>Échéance d'action</th><th></th></tr>
        </thead>
        <tbody>
        <?php if (! $litiges): ?><tr><td colspan="9" class="text-center text-muted py-4">Aucun litige.</td></tr><?php endif; ?>
        <?php foreach ($litiges as $l): ?>
            <tr>
                <td><a href="<?= base_url('litiges/voir/' . $l['id']) ?>"><?= esc($l['reference']) ?></a></td>
                <td><a href="<?= base_url('contrats/voir/' . $l['contrat_id']) ?>"><?= esc($l['contrat_reference']) ?></a></td>
                <td><?= esc($types[$l['type_litige']] ?? $l['type_litige']) ?></td>
                <td><span class="badge bg-secondary"><?= esc($etats[$l['etat_code']] ?? $l['etat_code']) ?></span></td>
                <td><?= esc($l['responsable_nom'] ?: '—') ?></td>
                <td class="text-end"><?= $l['montant_enjeu'] !== null ? number_format((float) $l['montant_enjeu'], 0, ',', ' ') : '—' ?></td>
                <td><?= date_fr($l['date_ouverture']) ?></td>
                <td><?= $l['date_echeance_action'] ? date_fr($l['date_echeance_action']) : '—' ?></td>
                <td><a class="btn btn-sm btn-outline-secondary" href="<?= base_url('penalites?contrat_id=' . $l['contrat_id']) ?>">Pénalités</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?= $pager->links('default', 'bootstrap_full') ?>
<?= $this->endSection() ?>