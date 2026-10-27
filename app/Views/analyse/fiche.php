<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
use App\Services\AnalyseService;
$cl  = ['faible' => 'bg-success', 'moyen' => 'bg-warning text-dark', 'eleve' => 'bg-danger', 'critique' => 'bg-dark'];
$gr  = ['danger' => 'list-group-item-danger', 'warning' => 'list-group-item-warning', 'info' => 'list-group-item-info'];
$decisionsFaites = array_keys(AnalyseService::DECISIONS);
?>
<?php if (session()->getFlashdata('erreurs')): ?>
    <div class="alert alert-danger"><?php foreach (session()->getFlashdata('erreurs') as $e): ?><div><?= esc($e) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-start mb-3">
    <div>
        <h1 class="h3 mb-1">Fiche d'analyse : <?= esc($contrat['reference']) ?></h1>
        <div class="text-muted"><?= esc($contrat['intitule']) ?> — <?= esc($contrat['raison_sociale']) ?></div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('contrats/voir/' . $contrat['id']) ?>" class="btn btn-outline-secondary">Contrat</a>
        <a href="<?= base_url('analyse/pdf/' . $contrat['id']) ?>" target="_blank" class="btn btn-outline-primary">Exporter en PDF</a>
        <?php if ($peutEcrire): ?>
            <form action="<?= base_url('analyse/recalculer/' . $contrat['id']) ?>" method="post"><?= csrf_field() ?>
                <button class="btn btn-outline-secondary" type="submit">Recalculer le score</button></form>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card h-100 text-center"><div class="card-body">
            <div class="text-muted small">Score de risque</div>
            <div class="display-4"><?= $score ?><small class="fs-5">/100</small></div>
            <span class="badge <?= $cl[$niveauRisque] ?>"><?= esc(AnalyseService::RISQUES[$niveauRisque]) ?></span>
            <div class="progress mt-3"><div class="progress-bar <?= $cl[$niveauRisque] ?>" style="width: <?= $score ?>%"></div></div>
            <?php if ($niveau): ?><div class="mt-3"><span class="badge <?= $niveau['classe'] ?>"><?= esc($niveau['label']) ?></span>
                <small class="text-muted">échéance le <?= date_fr($contrat['date_limite_non_reconduction']) ?></small></div><?php endif; ?>
        </div></div>
    </div>
    <div class="col-md-8">
        <div class="card h-100"><div class="card-header">Points d'attention</div>
            <ul class="list-group list-group-flush">
                <?php if (! $points): ?><li class="list-group-item text-muted">Aucun point d'attention.</li><?php endif; ?>
                <?php foreach ($points as $p): ?><li class="list-group-item <?= $gr[$p['gravite']] ?>"><?= esc($p['texte']) ?></li><?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">Clauses détectées (<?= count($clauses) ?>)</div>
    <div class="table-responsive"><table class="table table-sm mb-0">
        <thead><tr><th>Type</th><th>Texte</th><th>Page</th><th>Origine</th><th>Confiance</th><th>Validée</th></tr></thead>
        <tbody>
        <?php if (! $clauses): ?><tr><td colspan="6" class="text-center text-muted py-3">Aucune clause.</td></tr><?php endif; ?>
        <?php foreach ($clauses as $c): ?>
            <tr>
                <td><?= esc($typesClause[$c['type_clause']] ?? $c['type_clause']) ?></td>
                <td><?= nl2br(esc($c['texte'])) ?></td>
                <td><?= $c['page'] ?? '—' ?></td>
                <td><?= esc($c['origine']) ?></td>
                <td><?= $c['confiance'] !== null ? round($c['confiance'] * 100) . ' %' : '—' ?></td>
                <td><?= $c['validee'] ? 'Oui' : 'Non' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</div>

<div class="card mb-3">
    <div class="card-header">Recommandations</div>
    <div class="card-body">
        <?php if (! $recos): ?><p class="text-muted">Aucune recommandation enregistrée.</p><?php endif; ?>
        <?php foreach ($recos as $r): $faite = in_array($r['decision'], $decisionsFaites, true); ?>
            <div class="border rounded p-3 mb-3">
                <div class="d-flex justify-content-between">
                    <div>
                        <strong><?= esc(AnalyseService::RECOMMANDATIONS[$r['type']] ?? $r['type']) ?></strong>
                        <span class="badge <?= $cl[$r['niveau']] ?? 'bg-secondary' ?>"><?= esc(AnalyseService::RISQUES[$r['niveau']] ?? $r['niveau']) ?></span>
                    </div>
                    <span class="badge <?= $faite ? 'bg-success' : 'bg-warning text-dark' ?>">
                        <?= $faite ? esc(AnalyseService::DECISIONS[$r['decision']]) : 'En attente de décision' ?>
                    </span>
                </div>
                <p class="mb-1 mt-2"><?= nl2br(esc($r['justification'] ?? '')) ?></p>
                <?php if ($faite): ?>
                    <small class="text-muted">Décidé le <?= $r['date_decision'] ? esc(date('d/m/Y H:i', strtotime($r['date_decision']))) : '—' ?>
                        <?= $r['commentaire'] ? ' — ' . esc($r['commentaire']) : '' ?></small>
                <?php elseif ($peutDecider): ?>
                    <form action="<?= base_url('analyse/decider/' . $r['id']) ?>" method="post" class="row g-2 mt-2">
                        <?= csrf_field() ?>
                        <div class="col-md-3">
                            <select class="form-select form-select-sm" name="decision" required>
                                <?php foreach (AnalyseService::DECISIONS as $c => $l): ?><option value="<?= $c ?>"><?= esc($l) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select form-select-sm" name="type_recommandation" title="Utilisé si « Modifiée »">
                                <?php foreach (AnalyseService::RECOMMANDATIONS as $c => $l): ?><option value="<?= $c ?>" <?= $r['type'] === $c ? 'selected' : '' ?>><?= esc($l) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6"><input class="form-control form-control-sm" name="justification" placeholder="Nouvelle justification (si « Modifiée »)"></div>
                        <div class="col-md-10"><input class="form-control form-control-sm" name="commentaire" placeholder="Commentaire de décision"></div>
                        <div class="col-md-2"><button class="btn btn-sm btn-primary w-100" type="submit">Décider</button></div>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <?php if ($peutEcrire): ?>
            <?php if ($peutRecommander): ?>
                <hr>
                <h2 class="h6">Nouvelle recommandation (proposition pré-remplie, à valider)</h2>
                <form action="<?= base_url('analyse/recommander/' . $contrat['id']) ?>" method="post" class="row g-2">
                    <?= csrf_field() ?>
                    <div class="col-md-3">
                        <select class="form-select" name="type_recommandation" required>
                            <?php foreach (AnalyseService::RECOMMANDATIONS as $c => $l): ?>
                                <option value="<?= $c ?>" <?= $proposition['type_recommandation'] === $c ? 'selected' : '' ?>><?= esc($l) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="niveau_risque" required>
                            <?php foreach (AnalyseService::RISQUES as $c => $l): ?>
                                <option value="<?= $c ?>" <?= $proposition['niveau_risque'] === $c ? 'selected' : '' ?>><?= esc($l) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12"><textarea class="form-control" name="justification" rows="2" required><?= esc($proposition['justification']) ?></textarea></div>
                    <div class="col-12"><button class="btn btn-outline-primary" type="submit">Enregistrer la recommandation</button></div>
                </form>
            <?php else: ?>
                <small class="text-muted">Une recommandation peut être enregistrée à partir de J-30 avant la date limite de non-reconduction.</small>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>