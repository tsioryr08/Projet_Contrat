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
    .partie { margin: 8px 0; }
    .signature { display: inline-block; width: 47%; min-height: 110px; vertical-align: top; margin-top: 24px; }
    .clause { margin: 6px 0; padding-left: 8px; border-left: 3px solid #ccc; }
    .petit { color: #666; font-size: 9px; }
</style>
</head>
<body>

<h1><?= esc($contrat['intitule'] ?: 'Contrat de ' . $contrat['type_libelle']) ?></h1>
<div class="sous-titre">Référence <?= esc($contrat['reference']) ?> — <?= esc($contrat['intitule']) ?></div>
<?php if ($projet): ?><div class="bandeau projet">PROJET — DOCUMENT NON SIGNÉ (statut : <?= esc($statuts[$contrat['statut']] ?? $contrat['statut']) ?>)</div><?php endif; ?>
<?php if ($contrat['confidentiel']): ?><div class="bandeau confidentiel">CONFIDENTIEL</div><?php endif; ?>

<h2>Entre les soussignés</h2>
<p class="partie">
    L'entreprise, représentée par son service <strong><?= esc($contrat['service_nom'] ?? 'non précisé') ?></strong>
    <?php if (! empty($contrat['responsable_nom'])): ?>, responsable du suivi : <?= esc($contrat['responsable_nom']) ?><?php endif; ?>,
    ci-après désignée « le Client »,
</p>
<p style="text-align:center"><strong>D'UNE PART,</strong></p>
<p class="partie">
    <strong><?= esc($contrat['raison_sociale']) ?></strong>
    <?php if ($contrat['adresse'] || $contrat['ville']): ?>, dont le siège est situé <?= esc(trim(($contrat['adresse'] ?? '') . ' ' . ($contrat['ville'] ?? ''))) ?><?php endif; ?>
    <?php if ($contrat['pays']): ?> (<?= esc($contrat['pays']) ?>)<?php endif; ?>,
    <?php
    $ident = [];
    if ($contrat['nif']) $ident[] = 'NIF : ' . $contrat['nif'];
    if ($contrat['stat']) $ident[] = 'STAT : ' . $contrat['stat'];
    if ($contrat['rcs']) $ident[] = 'RCS : ' . $contrat['rcs'];
    ?>immatriculée sous <?= esc(implode(' — ', $ident) ?: 'un numéro non précisé') ?>,
    <?php if ($contrat['representant_legal']): ?>représentée par <?= esc($contrat['representant_legal']) ?>,<?php endif; ?>
    ci-après désignée « le Fournisseur ».
</p>
<p style="text-align:center"><strong>D'AUTRE PART.</strong></p>
<p>Ensemble désignés « les Parties ».</p>

<h2>Préambule</h2>
<p>Le Client souhaite bénéficier des services décrits au présent contrat. Le Fournisseur, spécialisé dans le domaine concerné, a proposé ses services. Les Parties se sont rapprochées et ont convenu de ce qui suit.</p>
<p style="text-align:center"><strong>IL A ÉTÉ CONVENU CE QUI SUIT :</strong></p>

<h2>Article <?= ++$art ?> — Objet</h2>
<p>Le présent contrat a pour objet de définir les conditions dans lesquelles le Fournisseur réalisera pour le Client la mission suivante : <?= nl2br(esc($contrat['objet'] ?: 'objet non renseigné')) ?>.</p>
<p>Type de contrat : <strong><?= esc($contrat['famille']) ?> / <?= esc($contrat['type_libelle']) ?></strong>. Le contrat est signé le <strong><?= $d($contrat['date_signature']) ?></strong>.</p>

<h2>Article <?= ++$art ?> — Durée et reconduction</h2>
<p>Le contrat prend effet le <strong><?= $d($contrat['date_debut']) ?></strong> et prend fin le <strong><?= $d($contrat['date_fin']) ?></strong>. Sa durée est de <strong><?= $contrat['duree_mois'] ? (int) $contrat['duree_mois'] . ' mois' : 'durée non précisée' ?></strong>.</p>
<p>Le préavis est fixé à <strong><?= (int) $contrat['preavis_jours'] ?> jour(s)</strong>. La date limite de non-reconduction est le <strong><?= $d($contrat['date_limite_non_reconduction']) ?></strong>.</p>
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
<p>Le prix de la mission est fixé à <strong><?= $m($contrat['montant']) ?> <?= esc($contrat['devise']) ?></strong>.</p>
<p>Le paiement s'effectue selon la périodicité suivante : <strong><?= esc($contrat['periodicite_paiement'] ? ucfirst($contrat['periodicite_paiement']) : 'non précisée') ?></strong>. L'indexation est <?= esc($contrat['indexation'] ?: 'non précisée') ?>.</p>
<p>Garantie : <?= nl2br(esc($contrat['garantie'] ?: 'aucune garantie précisée')) ?>.</p>

<h2>Article <?= ++$art ?> — Pénalités</h2>
<p><?= nl2br(esc($contrat['penalite_description'] ?: 'Aucune pénalité stipulée.')) ?></p>
<p>Taux de pénalité : <strong><?= $contrat['penalite_taux'] !== null ? esc($contrat['penalite_taux']) . ' %' : 'non précisé' ?></strong>. Plafond : <strong><?= $contrat['penalite_plafond'] !== null ? $m($contrat['penalite_plafond']) . ' ' . esc($contrat['devise']) : 'non précisé' ?></strong>.</p>

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
<div class="signature"><strong>Pour le Client :</strong><br><br>Nom : ______________________<br><br>Signature précédée de la mention « Lu et approuvé » :</div>
<div class="signature"><strong>Pour le Fournisseur :</strong><br><br>Nom : <?= esc($contrat['representant_legal'] ?: '______________________') ?><br><br>Signature précédée de la mention « Lu et approuvé » :</div>

</body>
</html>
