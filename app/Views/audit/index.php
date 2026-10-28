<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php helper(['audit', 'ui']); ?>

<form method="get" action="<?= site_url('audit') ?>" class="card card-body mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label" for="utilisateur_id">Utilisateur</label>
            <select name="utilisateur_id" id="utilisateur_id" class="form-select">
                <option value="">Tous</option>
                <?php foreach ($utilisateurs as $u): ?>
                    <option value="<?= (int) $u['id'] ?>" <?= (string) $filtres['utilisateur_id'] === (string) $u['id'] ? 'selected' : '' ?>>
                        <?= esc($u['nom_complet']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label" for="entite">Entité</label>
            <select name="entite" id="entite" class="form-select">
                <option value="">Toutes</option>
                <?php foreach ($entites as $e): ?>
                    <option value="<?= esc($e, 'attr') ?>" <?= $filtres['entite'] === $e ? 'selected' : '' ?>><?= esc($e) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label" for="action">Action</label>
            <select name="action" id="action" class="form-select">
                <option value="">Toutes</option>
                <?php foreach ($actions as $a): ?>
                    <option value="<?= esc($a, 'attr') ?>" <?= $filtres['action'] === $a ? 'selected' : '' ?>><?= esc($a) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label" for="du">Du</label>
            <input type="date" name="du" id="du" class="form-control" value="<?= esc($filtres['du'] ?? '', 'attr') ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label" for="au">Au</label>
            <input type="date" name="au" id="au" class="form-control" value="<?= esc($filtres['au'] ?? '', 'attr') ?>">
        </div>
        <div class="col-md-1 d-grid gap-1">
            <button class="btn btn-primary" type="submit">Filtrer</button>
            <a class="btn btn-outline-secondary btn-sm" href="<?= site_url('audit') ?>">Réinit.</a>
        </div>
    </div>
</form>

<p class="text-muted small"><?= (int) $pager->getTotal() ?> entrée(s)</p>

<div class="table-responsive">
    <table class="table table-sm table-striped align-middle bg-white">
        <thead class="table-light">
            <tr>
                <th>Date</th><th>Utilisateur</th><th>Acteur</th><th>Action</th>
                <th>Entité</th><th>Modifications</th><th>IP</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($lignes)): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">Aucune entrée.</td></tr>
        <?php endif; ?>

        <?php foreach ($lignes as $l): ?>
            <?php
            $qui = trim((string) ($l['utilisateur_nom'] ?? ''));
            if ($qui === '') {
                $qui = $l['acteur'] === 'ia' ? 'IA' : ($l['acteur'] === 'systeme' ? 'Système' : '—');
            }
            $classeActeur = ['humain' => 'bg-primary', 'systeme' => 'bg-secondary', 'ia' => 'bg-info text-dark'][$l['acteur']] ?? 'bg-secondary';
            $diff = audit_diff($l['avant'], $l['apres']);
            ?>
            <tr>
                <td class="text-nowrap"><?= esc(date('d/m/Y H:i:s', strtotime($l['date_action']))) ?></td>
                <td><?= esc($qui) ?></td>
                <td><span class="badge <?= $classeActeur ?>"><?= esc($l['acteur']) ?></span></td>
                <td><?= esc($l['action']) ?></td>
                <td><?= esc($l['entite']) ?> #<?= (int) $l['entite_id'] ?></td>
                <td>
                    <?php if ($diff): ?>
                        <details>
                            <summary><?= count($diff) ?> champ(s)</summary>
                            <table class="table table-sm table-bordered mt-2 mb-0">
                                <thead><tr><th>Champ</th><th>Avant</th><th>Après</th></tr></thead>
                                <tbody>
                                <?php foreach ($diff as $d): ?>
                                    <tr>
                                        <td><?= esc($d['champ']) ?></td>
                                        <td class="text-danger"><?= esc($d['avant']) ?></td>
                                        <td class="text-success"><?= esc($d['apres']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </details>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td class="small text-muted"><?= esc($l['ip']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?= view('partials/pagination', ['pager' => $pager]) ?>
<?= $this->endSection() ?>
