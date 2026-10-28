<?php
$d = fn($x) => $x ? date('d/m/Y', strtotime($x)) : '—';
$m = fn($x) => $x !== null && $x !== '' ? number_format((float) $x, 2, ',', ' ') : '—';
$projet = in_array($contrat['statut'], ['en_preparation', 'en_validation', 'a_signer'], true);
$tacite = (int) $contrat['reconduction_tacite'] === 1;
$typesClause = \App\Models\ClauseModel::TYPES;
$statuts = \App\Services\ContratService::STATUTS;
$art = 0;
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 70px 55px 70px 55px; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10.5px; color: #222; line-height: 1.45; }
    h1 { font-size: 17px; text-align: center; margin: 0 0 4px 0; text-transform: uppercase; }
    .sous-titre { text-align: center; color: #555; margin-bottom: 4px; }
    .bandeau { text-align: center; font-weight: bold; letter-spacing: 1px; margin: 6px 0; }
    .projet { color: #b00020; border: 1px solid #b00020; padding: 3px; }
    .confidentiel { color: #fff; background: #222; padding: 3px; }
    h2 { font-size: 12px; margin: 16px 0 4px 0; padding-bottom: 2px; border-bottom: 1px solid #888; }
    p { margin: 4px 0; text-align: justify; }
    table.grille { width: 100%; border-collapse: collapse; margin: 4px 0; }
    table.grille td, table.grille th { border: 1px solid #ccc; padding: 4px 6px; vertical-align: top; text-align: left; }
    table.grille th { background: #f0f0f0; width: 32%; font-weight: bold; }
    .partie { border: 1px solid #bbb; padding: 6px 8px; margin: 6px 0; }
    .signatures { width: 100%; margin-top: 30px; border-collapse: collapse; }
    .signatures td { width: 50%; height: 110px; vertical-align: top; border: 1px solid #bbb; padding: 6px; }
    .clause { margin: 6px 0; padding-left: 8px; border-left: 3px solid #ccc; }
    .petit { color: #666; font-size: 9px; }
</style>
</head>
<body>

<h1>Contrat <?= esc($contrat['type_libelle']) ?></h1>
<div class="sous-titre">Référence <?= esc($contrat['reference']) ?> — <?= esc($contrat['intitule']) ?></div>
<?php if ($projet): ?><div class="bandeau projet">PROJET — DOCUMENT NON SIGNÉ (statut : <?= esc($statuts[$contrat['statut']] ?? $contrat['statut']) ?>)</div><?php endif; ?>
<?php if ($contrat['confidentiel']): ?><div class="bandeau confidentiel">CONFIDENTIEL</div><?php endif; ?>

<h2>Entre les soussignés</h2>
<div class="partie">
    <strong>L'entreprise</strong>, représentée par son service <strong><?= esc($contrat['service_nom'] ?? 'non précisé') ?></strong>
    <?php if (! empty($contrat['responsable_nom'])): ?>, responsable du suivi : <?= esc($contrat['responsable_nom']) ?><?php endif; ?>.
    <br><em>Ci-après « le Client ».</em>
</div>
<p style="text-align:center">et</p>
<div class="partie">
    <strong><?= esc($contrat['raison_sociale']) ?></strong>
    <?php if ($contrat['adresse'] || $contrat['ville']): ?>, <?= esc(trim(($contrat['adresse'] ?? '') . ' ' . ($contrat['ville'] ?? ''))) ?><?php endif; ?>
    <?php if ($contrat['pays']): ?> (<?= esc($contrat['pays']) ?>)<?php endif; ?>.
    <br>
    <?php
    $ident = [];
    if ($contrat['nif']) $ident[] = 'NIF : ' . $contrat['nif'];
    if ($contrat['stat']) $ident[] = 'STAT : ' . $contrat['stat'];
    if ($contrat['rcs']) $ident[] = 'RCS : ' . $contrat['rcs'];
    echo esc(implode(' — ', $ident));
    ?>
    <?php if ($contrat['representant_legal']): ?><br>Représentée par : <?= esc($contrat['representant_legal']) ?><?php endif; ?>
    <br><em>Ci-après « le Fournisseur ».</em>
</div>

<h2>Article <?= ++$art ?> — Objet</h2>
<p><?= nl2br(esc($contrat['objet'] ?: 'Objet non renseigné.')) ?></p>
<table class="grille">
    <tr><th>Type de contrat</th><td><?= esc($contrat['famille']) ?> / <?= esc($contrat['type_libelle']) ?></td></tr>
    <tr><th>Date de signature</th><td><?= $d($contrat['date_signature']) ?></td></tr>
</table>

<h2>Article <?= ++$art ?> — Durée et reconduction</h2>
<table class="grille">
    <tr><th>Date de début</th><td><?= $d($contrat['date_debut']) ?></td></tr>
    <tr><th>Date de fin</th><td><?= $d($contrat['date_fin']) ?></td></tr>
    <tr><th>Durée</th><td><?= $contrat['duree_mois'] ? (int) $contrat['duree_mois'] . ' mois' : '—' ?></td></tr>
    <tr><th>Préavis</th><td><?= (int) $contrat['preavis_jours'] ?> jour(s)</td></tr>
    <tr><th>Date limite de non-reconduction</th><td><?= $d($contrat['date_limite_non_reconduction']) ?></td></tr>
</table>
<p>
<?php if ($tacite): ?>
    Le contrat est <strong>reconduit tacitement</strong>
    <?= $contrat['duree_reconduction_mois'] ? 'pour des périodes de ' . (int) $contrat['duree_reconduction_mois'] . ' mois' : 'à son terme' ?>,
    <?= $contrat['nb_reconductions_max'] !== null ? 'dans la limite de ' . (int) $contrat['nb_reconductions_max'] . ' reconduction(s)' : 'sans limitation du nombre de reconductions' ?>,
    sauf dénonciation notifiée par l'une des parties au plus tard le <strong><?= $d($contrat['date_limite_non_reconduction']) ?></strong>
    (préavis de <?= (int) $contrat['preavis_jours'] ?> jour(s) avant le terme).
    Reconductions déjà effectuées : <?= (int) $contrat['nb_reconductions_effectuees'] ?>.
<?php else: ?>
    Le contrat <strong>n'est pas reconductible tacitement</strong> : il prend fin à son terme.
<?php endif; ?>
</p>

<h2>Article <?= ++$art ?> — Prix et modalités de paiement</h2>
<table class="grille">
    <tr><th>Montant</th><td><?= $m($contrat['montant']) ?> <?= esc($contrat['devise']) ?></td></tr>
    <tr><th>Périodicité de paiement</th><td><?= esc($contrat['periodicite_paiement'] ? ucfirst($contrat['periodicite_paiement']) : '—') ?></td></tr>
    <tr><th>Indexation</th><td><?= esc($contrat['indexation'] ?: '—') ?></td></tr>
    <tr><th>Garantie</th><td><?= nl2br(esc($contrat['garantie'] ?: '—')) ?></td></tr>
</table>

<h2>Article <?= ++$art ?> — Pénalités</h2>
<p><?= nl2br(esc($contrat['penalite_description'] ?: 'Aucune pénalité stipulée.')) ?></p>
<table class="grille">
    <tr><th>Taux de pénalité</th><td><?= $contrat['penalite_taux'] !== null ? esc($contrat['penalite_taux']) . ' %' : '—' ?></td></tr>
    <tr><th>Plafond</th><td><?= $contrat['penalite_plafond'] !== null ? $m($contrat['penalite_plafond']) . ' ' . esc($contrat['devise']) : '—' ?></td></tr>
</table>

<h2>Article <?= ++$art ?> — Résiliation</h2>
<p><?= nl2br(esc($contrat['clause_resiliation'] ?: 'Aucune clause de résiliation spécifique.')) ?></p>

<?php if ($clauses): ?>
<h2>Article <?= ++$art ?> — Clauses particulières</h2>
<?php foreach ($clauses as $i => $cl): ?>
    <div class="clause">
        <strong><?= $art ?>.<?= $i + 1 ?> <?= esc($typesClause[$cl['type_clause']] ?? $cl['type_clause']) ?></strong>
        <?= $cl['page'] ? '<span class="petit">(p. ' . (int) $cl['page'] . ')</span>' : '' ?><br>
        <?= nl2br(esc($cl['texte'])) ?>
    </div>
<?php endforeach; ?>
<?php endif; ?>

<h2>Article <?= ++$art ?> — Loi applicable et juridiction</h2>
<p>Le présent contrat est soumis au <strong><?= esc($contrat['loi_applicable'] ?: 'droit applicable non précisé') ?></strong>.
   Tout litige relève de la juridiction suivante : <strong><?= esc($contrat['juridiction'] ?: 'non précisée') ?></strong>.</p>

<p style="margin-top:14px">Fait à ______________________, le ____ / ____ / ________, en deux exemplaires originaux.</p>
<table class="signatures">
    <tr>
        <td><strong>Pour le Client</strong><br><span class="petit">Nom, qualité, signature et cachet</span></td>
        <td><strong>Pour le Fournisseur</strong><br><span class="petit">Nom, qualité, signature et cachet</span></td>
    </tr>
</table>

</body>
</html>
