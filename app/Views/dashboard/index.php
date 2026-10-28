<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php $qs = http_build_query(array_filter($f)); $nf = fn($n) => number_format((float) $n, 0, ',', ' '); ?>
<h1 class="h3 mb-3">Tableau de bord des échéances</h1>

<form method="get" action="<?= base_url('dashboard') ?>" class="card card-body mb-3">
    <div class="row g-2">
        <div class="col-md-4">
            <select name="service_id" class="form-select">
                <option value="">Tous les services</option>
                <?php foreach ($services as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= (string) $f['service_id'] === (string) $s['id'] ? 'selected' : '' ?>><?= esc($s['nom']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <select name="responsable_id" class="form-select" <?= $verrouille ? 'disabled' : '' ?>>
                <option value="">Tous les responsables</option>
                <?php foreach ($responsables as $r): ?>
                    <option value="<?= $r['id'] ?>" <?= (string) $f['responsable_id'] === (string) $r['id'] ? 'selected' : '' ?>><?= esc($r['nom']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4 d-flex gap-2">
            <button class="btn btn-primary flex-fill" type="submit">Filtrer</button>
            <a href="<?= base_url('dashboard') ?>" class="btn btn-outline-secondary">Effacer</a>
        </div>
    </div>
    <?php if ($verrouille): ?><small class="text-muted mt-2">Vous ne voyez que vos propres contrats.</small><?php endif; ?>
</form>

<div class="row g-3 mb-3">
    <?php foreach (['ok', 'a_examiner', 'urgent', 'depasse'] as $code): ?>
        <div class="col-6 col-lg-3">
            <a class="text-decoration-none" href="<?= base_url('contrats?niveau=' . $code) ?>">
                <div class="card text-center h-100"><div class="card-body">
                    <div class="display-6"><?= $indic['niveaux'][$code] ?></div>
                    <span class="badge <?= $niveauxDef[$code]['classe'] ?>"><?= esc($niveauxDef[$code]['label']) ?></span>
                </div></div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="card h-100"><div class="card-body">
        <div class="text-muted small">Valeur sous contrat</div>
        <?php if (! $indic['valeurs']): ?>—<?php endif; ?>
        <?php foreach ($indic['valeurs'] as $v): ?>
            <div class="fs-5"><?= $nf($v['total']) ?> <?= esc($v['devise']) ?> <small class="text-muted">(<?= $v['n'] ?>)</small></div>
        <?php endforeach; ?>
    </div></div></div>
    <div class="col-md-3"><div class="card h-100"><div class="card-body">
        <div class="text-muted small">Pénalités estimées exposées</div>
        <div class="fs-5"><?= $indic['penalites'] === null ? 'n/d' : $nf($indic['penalites']) ?></div>
        <a href="<?= base_url('penalites') ?>" class="small">Voir les pénalités</a>
    </div></div></div>
    <div class="col-md-3"><div class="card h-100"><div class="card-body">
        <div class="text-muted small">Litiges ouverts</div>
        <div class="fs-3"><?= $indic['litiges'] ?></div>
        <a href="<?= base_url('litiges') ?>" class="small">Voir les litiges</a>
    </div></div></div>
    <div class="col-md-3"><div class="card h-100"><div class="card-body">
        <div class="text-muted small">Obligations en retard</div>
        <div class="fs-3"><?= $indic['obligations'] ?></div>
        <a href="<?= base_url('obligations?statut=en_retard') ?>" class="small">Voir les obligations</a>
    </div></div></div>
</div>

<div class="card mb-3">
    <div class="card-header">Dates limites de non-reconduction sur 12 mois</div>
    <div class="card-body"><canvas id="graphique" height="90"></canvas></div>
</div>

<div class="card mb-3">
    <div class="card-header">10 échéances les plus proches</div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Contrat</th><th>Fournisseur</th><th>Date limite</th><th>Niveau</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php if (! $prochaines): ?><tr><td colspan="5" class="text-center text-muted py-3">Aucune échéance à venir.</td></tr><?php endif; ?>
            <?php foreach ($prochaines as $p): ?>
                <tr>
                    <td><?= esc($p['reference']) ?> — <?= esc($p['intitule']) ?></td>
                    <td><?= esc($p['raison_sociale']) ?></td>
                    <td><?= date_fr($p['date_limite_non_reconduction']) ?></td>
                    <td><?php if ($p['niveau']): ?><span class="badge <?= $p['niveau']['classe'] ?>"><?= esc($p['niveau']['label']) ?></span> <small class="text-muted">J-<?= $p['niveau']['jours'] ?></small><?php endif; ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-primary" href="<?= base_url('contrats/voir/' . $p['id']) ?>">Ouvrir</a>
                        <?php if ($peutLitige): ?>
                            <a class="btn btn-sm btn-outline-danger" href="<?= base_url('litiges/nouveau?contrat_id=' . $p['id']) ?>">Ouvrir un litige</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
fetch('<?= base_url('dashboard/echeances-json') . ($qs ? '?' . $qs : '') ?>')
    .then(r => r.json())
    .then(d => new Chart(document.getElementById('graphique'), {
        type: 'bar',
        data: {
            labels: d.labels,
            datasets: [{
                label: 'Contrats',
                data: d.valeurs,
                backgroundColor: function(context) {
                    const chart = context.chart;
                    const {ctx, chartArea} = chart;
                    if (!chartArea) return '#B9D4EC';
                    const gradient = ctx.createLinearGradient(0, chartArea.bottom, 0, chartArea.top);
                    gradient.addColorStop(0, '#C9E4D0');
                    gradient.addColorStop(1, '#B9D4EC');
                    return gradient;
                },
                borderRadius: 10,
                borderSkipped: false,
                maxBarThickness: 42,
                hoverBackgroundColor: function(context) {
                    const chart = context.chart;
                    const {ctx, chartArea} = chart;
                    if (!chartArea) return '#A8C5E2';
                    const gradient = ctx.createLinearGradient(0, chartArea.bottom, 0, chartArea.top);
                    gradient.addColorStop(0, '#B8D8C4');
                    gradient.addColorStop(1, '#A8C5E2');
                    return gradient;
                }
            }]
        },
        options: {
            responsive: true,
            scales: {
                x: {
                    grid: { color: 'rgba(168, 197, 226, 0.18)' },
                    ticks: { color: '#2E3A46', font: { family: 'Montserrat', size: 11 } }
                },
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0, color: '#2E3A46', font: { family: 'Montserrat', size: 11 } },
                    grid: { color: 'rgba(168, 197, 226, 0.16)' }
                }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(27, 42, 65, 0.9)',
                    titleColor: '#FFFFFF',
                    bodyColor: '#FFFFFF',
                    borderColor: 'rgba(185, 212, 236, 0.6)',
                    borderWidth: 1,
                    cornerRadius: 10
                }
            },
            animation: {
                duration: 700,
                easing: 'easeOutQuart'
            }
        }
    }));
</script>
<?= $this->endSection() ?>