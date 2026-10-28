<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
use App\Services\ContratService;
$qs = http_build_query(array_filter($filtres));
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Contrats</h1>
    <div class="d-flex gap-2">
        <a href="<?= base_url('exports/contrats') . ($qs ? '?' . $qs : '') ?>" class="btn btn-outline-secondary">Exporter CSV</a>
        <?php if ($peutEcrire): ?>
            <a href="<?= base_url('contrats/nouveau') ?>" class="btn btn-primary">Nouveau contrat</a>
        <?php endif; ?>
    </div>
</div>

<form method="get" action="<?= base_url('contrats') ?>" class="card card-body mb-3">
    <div class="row g-2">
        <div class="col-md-3">
            <input type="text" name="q" class="form-control" placeholder="Référence ou intitulé"
                   value="<?= esc($filtres['q']) ?>">
        </div>
        <div class="col-md-2">
            <select name="statut" class="form-select">
                <option value="">Tous les statuts</option>
                <?php foreach (ContratService::STATUTS as $code => $lib): ?>
                    <option value="<?= $code ?>" <?= $filtres['statut'] === $code ? 'selected' : '' ?>><?= esc($lib) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <select name="fournisseur_id" class="form-select">
                <option value="">Tous les fournisseurs</option>
                <?php foreach ($fournisseurs as $f): ?>
                    <option value="<?= $f['id'] ?>" <?= (string) $filtres['fournisseur_id'] === (string) $f['id'] ? 'selected' : '' ?>>
                        <?= esc($f['raison_sociale']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select name="niveau" class="form-select">
                <option value="">Tous niveaux d'échéance</option>
                <?php foreach (ContratService::NIVEAUX as $code => $n): ?>
                    <option value="<?= $code ?>" <?= $filtres['niveau'] === $code ? 'selected' : '' ?>><?= esc($n['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button class="btn btn-primary flex-fill" type="submit">Filtrer</button>
            <a href="<?= base_url('contrats') ?>" class="btn btn-outline-secondary">Effacer</a>
        </div>
    </div>
</form>

<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead class="table-light">
            <tr>
                <th>Référence</th><th>Intitulé</th><th>Fournisseur</th><th>Type</th>
                <th>Statut</th><th>Date de fin</th><th>Limite de non-reconduction</th><th class="text-end">Montant</th>
            </tr>
        </thead>
        <tbody>
        <?php if (! $contrats): ?>
            <tr><td colspan="8" class="text-center text-muted py-4">Aucun contrat trouvé.</td></tr>
        <?php endif; ?>
        <?php foreach ($contrats as $c): ?>
            <tr>
                <td><a href="<?= base_url('contrats/voir/' . $c['id']) ?>"><?= esc($c['reference']) ?></a></td>
                <td>
                    <?= esc($c['intitule']) ?>
                    <?php if ($c['en_litige']): ?><span class="badge bg-danger">Litige</span><?php endif; ?>
                </td>
                <td><?= esc($c['raison_sociale']) ?></td>
                <td><?= esc($c['type_libelle']) ?></td>
                <td><span class="badge <?= ContratService::STATUT_CLASSES[$c['statut']] ?? 'bg-secondary' ?>"><?= esc(ContratService::STATUTS[$c['statut']] ?? $c['statut']) ?></span></td>
                <td><?= $c['date_fin'] ? date_fr($c['date_fin']) : '—' ?></td>
                <td>
                    <?= $c['date_limite_non_reconduction'] ? date_fr($c['date_limite_non_reconduction']) : '—' ?>
                    <?php if ($c['niveau']): ?>
                        <span class="badge <?= $c['niveau']['classe'] ?>"><?= esc($c['niveau']['label']) ?></span>
                    <?php endif; ?>
                </td>
                <td class="text-end">
                    <?= $c['montant'] !== null ? number_format((float) $c['montant'], 0, ',', ' ') . ' ' . esc($c['devise']) : '—' ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?= $pager->links('default', 'bootstrap_full') ?>
<?= $this->endSection() ?>