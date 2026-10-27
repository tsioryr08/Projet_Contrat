<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
use App\Services\ContratService;
use App\Models\ClauseModel;

$montant = fn($m) => $m !== null ? number_format((float) $m, 0, ',', ' ') : '—';
$date    = fn($d) => $d ? date_fr($d) : '—';
?>

<?php if (session()->getFlashdata('erreurs')): ?>
    <div class="alert alert-danger">
        <?php foreach (session()->getFlashdata('erreurs') as $e): ?><div><?= esc($e) ?></div><?php endforeach; ?>
    </div>
<?php endif; ?>
<?php if ($peutEcrire): ?>
    <a href="<?= base_url('litiges/nouveau?contrat_id=' . $contrat['id']) ?>" class="btn btn-outline-danger">Ouvrir un litige</a>
<?php endif; ?>
<a href="<?= base_url('analyse/fiche/' . $contrat['id']) ?>" class="btn btn-outline-info">Fiche d'analyse</a>

<div class="d-flex flex-wrap justify-content-between align-items-start mb-3 gap-2">
    <div>
        <h1 class="h3 mb-1"><?= esc($contrat['reference']) ?> — <?= esc($contrat['intitule']) ?></h1>
        <span class="badge <?= ContratService::STATUT_CLASSES[$contrat['statut']] ?? 'bg-secondary' ?>"><?= esc(ContratService::STATUTS[$contrat['statut']] ?? $contrat['statut']) ?></span>
        <?php if ($contrat['motif_cloture']): ?>
            <span class="badge bg-light text-dark border"><?= esc(ContratService::MOTIFS[$contrat['motif_cloture']] ?? $contrat['motif_cloture']) ?></span>
        <?php endif; ?>
        <?php if ($contrat['en_litige']): ?><span class="badge bg-danger">En litige</span><?php endif; ?>
        <?php if ($contrat['confidentiel']): ?><span class="badge bg-dark">Confidentiel</span><?php endif; ?>
        <?php if ($parent): ?>
            <small class="ms-2">Renouvelle <a href="<?= base_url('contrats/voir/' . $parent['id']) ?>"><?= esc($parent['reference']) ?></a></small>
        <?php endif; ?>
        <?php foreach ($enfants as $e): ?>
            <small class="ms-2">Renouvelé par <a href="<?= base_url('contrats/voir/' . $e['id']) ?>"><?= esc($e['reference']) ?></a></small>
        <?php endforeach; ?>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('contrats') ?>" class="btn btn-outline-secondary">Retour à la liste</a>
        <?php if ($peutEcrire && $contrat['statut'] !== 'cloture'): ?>
            <a href="<?= base_url('contrats/modifier/' . $contrat['id']) ?>" class="btn btn-outline-primary">Modifier</a>
        <?php endif; ?>
        <?php if ($peutRenouveler): ?>
            <form action="<?= base_url('contrats/renouveler/' . $contrat['id']) ?>" method="post"
                  onsubmit="return confirm('Créer le contrat de renouvellement et clôturer celui-ci ?');">
                <?= csrf_field() ?>
                <button class="btn btn-warning" type="submit">Renouveler</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="alert <?= $niveau ? 'alert-light border' : 'alert-light border' ?> d-flex align-items-center gap-2">
    <span>Date limite de non-reconduction :</span>
    <strong><?= $date($contrat['date_limite_non_reconduction']) ?></strong>
    <?php if ($niveau): ?>
        <span class="badge <?= $niveau['classe'] ?>"><?= esc($niveau['label']) ?></span>
        <small class="text-muted">(<?= $niveau['jours'] < 0 ? 'dépassée de ' . abs($niveau['jours']) : 'dans ' . $niveau['jours'] ?> jour(s))</small>
    <?php endif; ?>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">Informations générales</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-5">Fournisseur</dt>
                    <dd class="col-7"><?= esc($contrat['raison_sociale']) ?>
                        <?php if ($contrat['fournisseur_statut'] !== 'actif'): ?><span class="badge bg-warning text-dark"><?= esc($contrat['fournisseur_statut']) ?></span><?php endif; ?></dd>
                    <dt class="col-5">Service</dt><dd class="col-7"><?= esc($contrat['service_nom'] ?? '—') ?></dd>
                    <dt class="col-5">Responsable</dt><dd class="col-7"><?= esc($contrat['responsable_nom'] ?: '—') ?></dd>
                    <dt class="col-5">Type</dt><dd class="col-7"><?= esc($contrat['famille']) ?> / <?= esc($contrat['type_libelle']) ?></dd>
                    <dt class="col-5">Objet</dt><dd class="col-7"><?= nl2br(esc($contrat['objet'] ?? '—')) ?></dd>
                    <dt class="col-5">Signature</dt><dd class="col-7"><?= $date($contrat['date_signature']) ?></dd>
                    <dt class="col-5">Début</dt><dd class="col-7"><?= $date($contrat['date_debut']) ?></dd>
                    <dt class="col-5">Fin</dt><dd class="col-7"><?= $date($contrat['date_fin']) ?></dd>
                    <dt class="col-5">Durée</dt><dd class="col-7"><?= $contrat['duree_mois'] ? esc($contrat['duree_mois']) . ' mois' : '—' ?></dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">Reconduction, finances et pénalités</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-5">Reconduction tacite</dt><dd class="col-7"><?= $contrat['reconduction_tacite'] ? 'Oui' : 'Non' ?></dd>
                    <dt class="col-5">Préavis</dt><dd class="col-7"><?= (int) $contrat['preavis_jours'] ?> jour(s)</dd>
                    <dt class="col-5">Reconductions</dt>
                    <dd class="col-7"><?= (int) $contrat['nb_reconductions_effectuees'] ?> / <?= $contrat['nb_reconductions_max'] ?? '∞' ?></dd>
                    <dt class="col-5">Montant</dt><dd class="col-7"><?= $montant($contrat['montant']) ?> <?= esc($contrat['devise']) ?></dd>
                    <dt class="col-5">Périodicité</dt><dd class="col-7"><?= esc($contrat['periodicite_paiement'] ?? '—') ?></dd>
                    <dt class="col-5">Indexation</dt><dd class="col-7"><?= esc($contrat['indexation'] ?? '—') ?></dd>
                    <dt class="col-5">Pénalités</dt><dd class="col-7"><?= nl2br(esc($contrat['penalite_description'] ?? '—')) ?></dd>
                    <dt class="col-5">Taux / plafond</dt>
                    <dd class="col-7"><?= $contrat['penalite_taux'] !== null ? esc($contrat['penalite_taux']) . ' %' : '—' ?> / <?= $montant($contrat['penalite_plafond']) ?></dd>
                    <dt class="col-5">Résiliation</dt><dd class="col-7"><?= nl2br(esc($contrat['clause_resiliation'] ?? '—')) ?></dd>
                    <dt class="col-5">Juridiction</dt><dd class="col-7"><?= esc($contrat['juridiction'] ?? '—') ?> (<?= esc($contrat['loi_applicable'] ?? '—') ?>)</dd>
                </dl>
            </div>
        </div>
    </div>
</div>

<?php if ($peutEcrire && $transitions): ?>
<div class="card mb-3">
    <div class="card-header">Changer le statut</div>
    <div class="card-body">
        <form action="<?= base_url('contrats/transition/' . $contrat['id']) ?>" method="post" class="row g-2 align-items-end">
            <?= csrf_field() ?>
            <div class="col-md-3">
                <label class="form-label" for="statut">Nouveau statut</label>
                <select class="form-select" id="statut" name="statut" required>
                    <?php foreach ($transitions as $t): ?>
                        <option value="<?= $t ?>"><?= esc(ContratService::STATUTS[$t]) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3" id="bloc_motif" style="display:none">
                <label class="form-label" for="motif_cloture">Motif de clôture *</label>
                <select class="form-select" id="motif_cloture" name="motif_cloture">
                    <option value="">Choisir…</option>
                    <?php foreach (ContratService::MOTIFS as $code => $lib): ?>
                        <option value="<?= $code ?>"><?= esc($lib) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="commentaire">Commentaire</label>
                <input class="form-control" id="commentaire" name="commentaire">
            </div>
            <div class="col-md-2"><button class="btn btn-primary w-100" type="submit">Appliquer</button></div>
        </form>
    </div>
</div>
<script>
(function () {
    const s = document.getElementById('statut'), b = document.getElementById('bloc_motif'), m = document.getElementById('motif_cloture');
    function maj() { const c = s.value === 'cloture'; b.style.display = c ? '' : 'none'; m.required = c; }
    s.addEventListener('change', maj); maj();
})();
</script>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-header">Clauses (<?= count($clauses) ?>)</div>
    <div class="card-body">
        <?php if (! $clauses): ?>
            <p class="text-muted">Aucune clause enregistrée.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead><tr><th>Type</th><th>Texte</th><th>Page</th><th>Origine</th><th>Validée</th><?php if ($peutEcrire): ?><th></th><?php endif; ?></tr></thead>
                    <tbody>
                    <?php foreach ($clauses as $cl): ?>
                        <tr>
                            <td><?= esc($typesClause[$cl['type_clause']] ?? $cl['type_clause']) ?></td>
                            <td><?= nl2br(esc($cl['texte'])) ?></td>
                            <td><?= $cl['page'] ?? '—' ?></td>
                            <td><?= esc($cl['origine']) ?><?= $cl['confiance'] !== null ? ' (' . round($cl['confiance'] * 100) . ' %)' : '' ?></td>
                            <td><?= $cl['validee'] ? 'Oui' : 'Non' ?></td>
                            <?php if ($peutEcrire): ?>
                            <td>
                                <form action="<?= base_url('contrats/clauses/supprimer/' . $cl['id']) ?>" method="post"
                                      onsubmit="return confirm('Supprimer cette clause ?');">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Supprimer</button>
                                </form>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($peutEcrire): ?>
        <form action="<?= base_url('contrats/clauses/ajouter/' . $contrat['id']) ?>" method="post" class="row g-2 border-top pt-3">
            <?= csrf_field() ?>
            <div class="col-md-3">
                <select class="form-select" name="type_clause" required>
                    <option value="">Type de clause…</option>
                    <?php foreach ($typesClause as $code => $lib): ?><option value="<?= $code ?>"><?= esc($lib) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5"><textarea class="form-control" name="texte" rows="1" placeholder="Texte de la clause" required></textarea></div>
            <div class="col-md-2"><input type="number" min="1" class="form-control" name="page" placeholder="Page"></div>
            <div class="col-md-2"><button class="btn btn-outline-primary w-100" type="submit">Ajouter</button></div>
        </form>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">Obligations (<?= count($obligations) ?>)</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Type</th><th>Débiteur</th><th>Échéance</th><th>Statut</th></tr></thead>
                    <tbody>
                    <?php if (! $obligations): ?><tr><td colspan="4" class="text-muted text-center py-3">Aucune obligation.</td></tr><?php endif; ?>
                    <?php foreach ($obligations as $o): ?>
                        <tr>
                            <td><?= esc($o['type_obligation']) ?></td>
                            <td><?= esc($o['debiteur']) ?></td>
                            <td><?= $date($o['echeance']) ?></td>
                            <td><?= esc($o['statut']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">Litiges (<?= count($litiges) ?>)</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Référence</th><th>Type</th><th>État</th><th>Ouvert le</th></tr></thead>
                    <tbody>
                    <?php if (! $litiges): ?><tr><td colspan="4" class="text-muted text-center py-3">Aucun litige.</td></tr><?php endif; ?>
                    <?php foreach ($litiges as $l): ?>
                        <tr>
                            <td><?= esc($l['reference']) ?></td>
                            <td><?= esc($l['type_litige']) ?></td>
                            <td><?= esc($l['etat_code']) ?></td>
                            <td><?= $date($l['date_ouverture']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">Historique des statuts</div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0">
            <thead><tr><th>Date</th><th>De</th><th>Vers</th><th>Utilisateur</th><th>Commentaire</th></tr></thead>
            <tbody>
            <?php foreach ($historique as $h): ?>
                <tr>
                    <td><?= esc(date('d/m/Y H:i', strtotime($h['date_changement']))) ?></td>
                    <td><?= $h['statut_avant'] ? esc(ContratService::STATUTS[$h['statut_avant']] ?? $h['statut_avant']) : '—' ?></td>
                    <td><?= esc(ContratService::STATUTS[$h['statut_apres']] ?? $h['statut_apres']) ?></td>
                    <td><?= esc($h['utilisateur_nom'] ?: $h['acteur']) ?></td>
                    <td><?= esc($h['commentaire'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>