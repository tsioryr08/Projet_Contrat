<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
helper('ui');
$classesStatut = ['actif' => 'bg-success', 'suspendu' => 'bg-warning text-dark', 'blackliste' => 'bg-dark'];
$statutsContrat = [
    'en_preparation' => 'En préparation', 'en_validation' => 'En validation', 'a_signer' => 'À signer',
    'en_execution' => 'En exécution', 'en_renouvellement' => 'En renouvellement', 'cloture' => 'Clôturé',
];
$f = $fournisseur;
$ligne = static fn (string $libelle, ?string $valeur) =>
    '<dt class="col-sm-4">' . esc($libelle) . '</dt><dd class="col-sm-8">'
    . (($valeur !== null && $valeur !== '') ? esc($valeur) : '<span class="text-muted">—</span>') . '</dd>';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <a href="<?= site_url('fournisseurs') ?>" class="btn btn-link px-0">← Retour à la liste</a>
    <a class="btn btn-outline-primary" href="<?= site_url('fournisseurs/modifier/' . (int) $f['id']) ?>">Modifier</a>
</div>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between">
        <span>Informations</span>
        <span class="badge <?= $classesStatut[$f['statut']] ?? 'bg-secondary' ?>"><?= esc($statuts[$f['statut']] ?? $f['statut']) ?></span>
    </div>
    <div class="card-body">
        <div class="row">
            <dl class="col-md-6 row mb-0">
                <?= $ligne('Raison sociale', $f['raison_sociale']) ?>
                <?= $ligne('NIF', $f['nif']) ?>
                <?= $ligne('STAT', $f['stat']) ?>
                <?= $ligne('RCS', $f['rcs']) ?>
                <?= $ligne('Secteur', $f['secteur']) ?>
                <?= $ligne('Représentant légal', $f['representant_legal']) ?>
            </dl>
            <dl class="col-md-6 row mb-0">
                <?= $ligne('Adresse', $f['adresse']) ?>
                <?= $ligne('Ville', $f['ville']) ?>
                <?= $ligne('Pays', $f['pays']) ?>
                <?= $ligne('E-mail', $f['email']) ?>
                <?= $ligne('Téléphone', $f['telephone']) ?>
            </dl>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-sm btn-link text-decoration-none p-0 text-dark" type="button" onclick="toggleFournisseurBlock('bloc-contacts', this)">
                <i class="bi bi-chevron-down toggle-icone"></i>
            </button>
            <span>Contacts</span>
        </div>
        <a class="btn btn-sm btn-outline-primary" href="<?= site_url('fournisseurs/contacts/' . (int) $f['id']) ?>">Gérer les contacts</a>
    </div>
    <div id="bloc-contacts">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Nom</th><th>Fonction</th><th>E-mail</th><th>Téléphone</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $contactModel = model('App\Models\FournisseurContactModel');
                $contactsList = $contactModel->pourFournisseur((int) $f['id']);
                ?>
                <?php if (empty($contactsList)): ?>
                    <tr><td colspan="4" class="text-center text-muted py-3">Aucun contact pour ce fournisseur.</td></tr>
                <?php endif; ?>
                <?php foreach ($contactsList as $ct): ?>
                    <tr>
                        <td><?= esc($ct['nom']) ?></td>
                        <td><?= $ct['fonction'] ? esc($ct['fonction']) : '<span class="text-muted">—</span>' ?></td>
                        <td><?= $ct['email'] ? esc($ct['email']) : '<span class="text-muted">—</span>' ?></td>
                        <td><?= $ct['telephone'] ? esc($ct['telephone']) : '<span class="text-muted">—</span>' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-sm btn-link text-decoration-none p-0 text-dark" type="button" onclick="toggleFournisseurBlock('bloc-evaluations', this)">
                <i class="bi bi-chevron-down toggle-icone"></i>
            </button>
            <span>Évaluations</span>
        </div>
        <a class="btn btn-sm btn-outline-primary" href="<?= site_url('fournisseurs/evaluations/' . (int) $f['id']) ?>">Gérer les évaluations</a>
    </div>
    <div id="bloc-evaluations">
        <div class="card-body">
            <?php
            $evalModel = model('App\Models\FournisseurEvaluationModel');
            $statsEval = $evalModel->moyennePourFournisseur((int) $f['id']);
            $derniereList = $evalModel->pourFournisseur((int) $f['id']);
            $derniere  = $derniereList[0] ?? null;
            ?>
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <strong>Moyenne :</strong> <?= $statsEval['moyenne'] !== null ? esc((string) $statsEval['moyenne']) . ' / 5' : 'Aucune évaluation' ?>
                    (<?= (int) $statsEval['nb'] ?> évaluation(s))
                </div>
                <?php if ($derniere): ?>
                    <div class="text-muted">Dernière évaluation le <?= esc(date('d/m/Y', strtotime($derniere['date_evaluation']))) ?> - <?= (int) $derniere['note'] ?>/5</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-sm btn-link text-decoration-none p-0 text-dark" type="button" onclick="toggleFournisseurBlock('bloc-contrats', this)">
                <i class="bi bi-chevron-down toggle-icone"></i>
            </button>
            <span>Contrats (<?= count($contrats) ?>)</span>
        </div>
    </div>
    <div id="bloc-contrats">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Référence</th><th>Intitulé</th><th>Type</th><th>Statut</th>
                        <th>Date de fin</th><th>Date limite de non-reconduction</th><th>Niveau</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($contrats)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-3">Aucun contrat pour ce fournisseur.</td></tr>
                <?php endif; ?>
                <?php foreach ($contrats as $c): ?>
                    <tr>
                        <td class="text-nowrap"><?= esc($c['reference']) ?></td>
                        <td><?= esc($c['intitule']) ?></td>
                        <td><?= esc($c['type_libelle']) ?></td>
                        <td><?= esc($statutsContrat[$c['statut']] ?? $c['statut']) ?></td>
                        <td><?= date_fr($c['date_fin']) ?></td>
                        <td><?= date_fr($c['date_limite_non_reconduction']) ?></td>
                        <td><?= $c['niveau'] ? badge_niveau($c['niveau']) : '<span class="text-muted">—</span>' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.toggle-icone {
    display: inline-block;
    transition: transform 0.2s ease-in-out;
}
.toggle-icone.replie {
    transform: rotate(-90deg);
}
</style>

<script>
function toggleFournisseurBlock(id, btn) {
    const bloc = document.getElementById(id);
    if (!bloc) return;
    if (bloc.style.display === 'none') {
        bloc.style.display = '';
        btn.querySelector('.toggle-icone').classList.remove('replie');
    } else {
        bloc.style.display = 'none';
        btn.querySelector('.toggle-icone').classList.add('replie');
    }
}
</script>

<?= $this->endSection() ?>
