<?php
use App\Services\AnalyseService;
$d = fn($x) => $x ? date('d/m/Y', strtotime($x)) : '—';
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<style>
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #222; }
    h1 { font-size: 18px; margin-bottom: 2px; } h2 { font-size: 13px; border-bottom: 1px solid #999; padding-bottom: 2px; margin-top: 18px; }
    table { width: 100%; border-collapse: collapse; } th, td { border: 1px solid #ccc; padding: 4px; text-align: left; vertical-align: top; }
    th { background: #eee; } .score { font-size: 28px; font-weight: bold; }
    .danger { color: #b00020; } .warning { color: #a66300; }
</style>
</head>
<body>
<h1>Fiche d'analyse : <?= esc($contrat['reference']) ?></h1>
<div><?= esc($contrat['intitule']) ?> — <?= esc($contrat['raison_sociale']) ?> (<?= esc($contrat['type_libelle']) ?>)</div>
<div>Édité le <?= date('d/m/Y H:i') ?></div>

<h2>Synthèse</h2>
<p>Score de risque : <span class="score"><?= $score ?>/100</span> (<?= esc(AnalyseService::RISQUES[$niveauRisque]) ?>)</p>
<table>
    <tr><th>Période</th><td><?= $d($contrat['date_debut']) ?> au <?= $d($contrat['date_fin']) ?></td>
        <th>Date limite de non-reconduction</th><td><?= $d($contrat['date_limite_non_reconduction']) ?><?= $niveau ? ' (' . esc($niveau['label']) . ')' : '' ?></td></tr>
    <tr><th>Reconduction tacite</th><td><?= $contrat['reconduction_tacite'] ? 'Oui' : 'Non' ?></td>
        <th>Préavis</th><td><?= (int) $contrat['preavis_jours'] ?> jour(s)</td></tr>
    <tr><th>Montant</th><td><?= $contrat['montant'] !== null ? number_format((float) $contrat['montant'], 0, ',', ' ') . ' ' . esc($contrat['devise']) : '—' ?></td>
        <th>Responsable</th><td><?= esc($contrat['responsable_nom'] ?: '—') ?></td></tr>
</table>

<h2>Points d'attention</h2>
<?php if (! $points): ?><p>Aucun point d'attention.</p><?php endif; ?>
<ul><?php foreach ($points as $p): ?><li class="<?= $p['gravite'] ?>"><?= esc($p['texte']) ?></li><?php endforeach; ?></ul>

<h2>Clauses détectées</h2>
<table>
    <tr><th>Type</th><th>Texte</th><th>Page</th><th>Origine</th><th>Confiance</th><th>Validée</th></tr>
    <?php foreach ($clauses as $c): ?>
        <tr>
            <td><?= esc($typesClause[$c['type_clause']] ?? $c['type_clause']) ?></td>
            <td><?= esc($c['texte']) ?></td>
            <td><?= $c['page'] ?? '—' ?></td>
            <td><?= esc($c['origine']) ?></td>
            <td><?= $c['confiance'] !== null ? round($c['confiance'] * 100) . ' %' : '—' ?></td>
            <td><?= $c['validee'] ? 'Oui' : 'Non' ?></td>
        </tr>
    <?php endforeach; ?>
</table>

<h2>Recommandations et décisions</h2>
<?php if (! $recos): ?><p>Aucune recommandation enregistrée.</p><?php endif; ?>
<?php foreach ($recos as $r): ?>
    <p>
        <strong><?= esc(AnalyseService::RECOMMANDATIONS[$r['type']] ?? $r['type']) ?></strong>
        (risque <?= esc(AnalyseService::RISQUES[$r['niveau']] ?? $r['niveau']) ?>) :
        <?= esc($r['justification'] ?? '') ?><br>
        Décision humaine :
        <?= isset(AnalyseService::DECISIONS[$r['decision']]) ? esc(AnalyseService::DECISIONS[$r['decision']]) . ' le ' . $d($r['date_decision']) : 'en attente' ?>
        <?= $r['commentaire'] ? ' — ' . esc($r['commentaire']) : '' ?>
    </p>
<?php endforeach; ?>
</body>
</html>