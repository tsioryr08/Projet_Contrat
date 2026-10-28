<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$grav = ['info' => 'bg-info text-dark', 'important' => 'bg-warning text-dark', 'urgent' => 'bg-danger', 'critique' => 'bg-dark'];
$stat = ['envoyee' => 'Non lue', 'lue' => 'Lue', 'traitee' => 'Traitée'];
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Alertes</h1>
    <form action="<?= base_url('alertes/toutes-lues') ?>" method="post"><?= csrf_field() ?>
        <button class="btn btn-outline-secondary" type="submit">Tout marquer comme lu</button></form>
</div>

<form method="get" action="<?= base_url('alertes') ?>" class="card card-body mb-3">
    <div class="row g-2 align-items-center">
        <div class="col-md-3">
            <select name="etat" class="form-select">
                <?php foreach (['actives' => 'À traiter (non lues et lues)', 'non_lues' => 'Non lues', 'traitees' => 'Traitées', 'toutes' => 'Toutes'] as $c => $l): ?>
                    <option value="<?= $c ?>" <?= $etat === $c ? 'selected' : '' ?>><?= esc($l) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if ($estAdmin): ?>
        <div class="col-md-4">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="tous" name="tous" value="1" <?= $tous ? 'checked' : '' ?>>
                <label class="form-check-label" for="tous">Voir les alertes de tous les utilisateurs</label>
            </div>
        </div>
        <?php endif; ?>
        <div class="col-md-2"><button class="btn btn-primary w-100" type="submit">Filtrer</button></div>
    </div>
</form>

<div class="table-responsive">
    <table class="table align-middle">
        <thead class="table-light">
            <tr><th>Niveau</th><th>Alerte</th><th>Contrat</th><?php if ($tous): ?><th>Destinataire</th><?php endif; ?>
                <th>Échéance</th><th>Statut</th><th class="text-end">Actions</th></tr>
        </thead>
        <tbody>
        <?php if (! $alertes): ?><tr><td colspan="7" class="text-center text-muted py-4">Aucune alerte.</td></tr><?php endif; ?>
        <?php foreach ($alertes as $a): ?>
            <tr class="<?= $a['statut'] === 'envoyee' ? 'fw-semibold' : '' ?>">
                <td><span class="badge <?= $grav[$a['gravite']] ?? 'bg-secondary' ?>"><?= esc($a['regle_code']) ?></span></td>
                <td>
                    <?= esc($a['sujet']) ?>
                    <?php if ((int) $a['obligation_id'] > 0): ?><span class="badge bg-light text-dark border">Obligation</span><?php endif; ?>
                </td>
                <td><a href="<?= base_url('contrats/voir/' . $a['contrat_id']) ?>"><?= esc($a['contrat_reference']) ?></a></td>
                <?php if ($tous): ?><td><?= esc($a['destinataire_nom']) ?></td><?php endif; ?>
                <td><?= date_fr($a['date_reference']) ?></td>
                <td><?= esc($stat[$a['statut']] ?? $a['statut']) ?></td>
                <td class="text-end">
                    <?php if ($a['statut'] === 'envoyee'): ?>
                        <form action="<?= base_url('alertes/lue/' . $a['id']) ?>" method="post" class="d-inline"><?= csrf_field() ?>
                            <button class="btn btn-sm btn-outline-secondary" type="submit">Lue</button></form>
                    <?php endif; ?>
                    <?php if ($a['statut'] !== 'traitee'): ?>
                        <form action="<?= base_url('alertes/traitee/' . $a['id']) ?>" method="post" class="d-inline"><?= csrf_field() ?>
                            <button class="btn btn-sm btn-outline-success" type="submit">Traitée</button></form>
                    <?php endif; ?>
                    <a class="btn btn-sm btn-outline-primary" href="<?= base_url('contrats/voir/' . $a['contrat_id']) ?>">Contrat</a>
                    <a class="btn btn-sm btn-outline-danger" href="<?= base_url('litiges/nouveau?contrat_id=' . $a['contrat_id']) ?>">Litige</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?= $pager->links('default', 'bootstrap_full') ?>
<?= $this->endSection() ?>
