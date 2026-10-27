<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$conf = function (string $k) use ($d) {
    if (! isset($d['confiance'][$k])) return '';
    $c = (float) $d['confiance'][$k];
    $cl = $c >= 0.8 ? 'bg-success' : ($c >= 0.5 ? 'bg-warning text-dark' : 'bg-danger');
    return '<span class="badge ' . $cl . '">' . round($c * 100) . ' %</span>';
};
$v = fn($k, $def = '') => old($k, $d[$k] ?? $def);
?>
<h1 class="h3 mb-1">Validation de l'extraction</h1>
<p class="text-muted">Document : <?= esc($nomDocument) ?><?= $simulation ? ' (mode simulation)' : '' ?></p>

<?php if (session()->getFlashdata('erreurs')): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach (session()->getFlashdata('erreurs') as $x): ?><li><?= esc($x) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<?php if ($e['statut'] !== 'en_attente'): ?>
    <div class="alert <?= $e['statut'] === 'erreur' ? 'alert-danger' : 'alert-secondary' ?>">
        Statut : <strong><?= esc($e['statut']) ?></strong>
        <?php if ($e['erreur']): ?><div><?= esc($e['erreur']) ?></div><?php endif; ?>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('ia') ?>" class="btn btn-outline-secondary">Retour</a>
        <?php if ($e['statut'] === 'erreur'): ?>
            <form action="<?= base_url('ia/relancer/' . $e['document_id']) ?>" method="post"><?= csrf_field() ?>
                <button class="btn btn-warning" type="submit">Nouvel essai</button></form>
        <?php endif; ?>
        <a href="<?= base_url('contrats/nouveau') ?>" class="btn btn-outline-primary">Saisie manuelle</a>
    </div>
<?php else: ?>
<div class="alert alert-info">Rien n'est enregistré tant que vous n'avez pas validé. Corrigez les champs puis cochez les clauses à conserver.</div>

<form action="<?= base_url('ia/creer-contrat/' . $e['id']) ?>" method="post">
    <?= csrf_field() ?>
    <div class="card mb-3">
        <div class="card-header">Contrat</div>
        <div class="card-body row g-3">
            <div class="col-md-8">
                <label class="form-label">Intitulé * <?= $conf('intitule') ?></label>
                <input class="form-control" name="intitule" maxlength="200" value="<?= esc($v('intitule')) ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Type * <small class="text-muted">(IA : <?= esc($d['type'] ?? '—') ?>)</small></label>
                <select class="form-select" name="type_id" required>
                    <option value="">Choisir…</option>
                    <?php foreach ($types as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= (string) old('type_id', $tSuggere) === (string) $t['id'] ? 'selected' : '' ?>><?= esc($t['famille'] . ' / ' . $t['libelle']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label">Objet</label>
                <textarea class="form-control" name="objet" rows="2"><?= esc($v('objet')) ?></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label">Fournisseur * <small class="text-muted">(IA : <?= esc($d['parties']['fournisseur'] ?? '—') ?>)</small></label>
                <select class="form-select" name="fournisseur_id" required>
                    <option value="">Choisir…</option>
                    <?php foreach ($fournisseurs as $f): ?>
                        <option value="<?= $f['id'] ?>" <?= (string) old('fournisseur_id', $fSuggere) === (string) $f['id'] ? 'selected' : '' ?>><?= esc($f['raison_sociale']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Service <small class="text-muted">(IA : <?= esc($d['parties']['service'] ?? '—') ?>)</small></label>
                <select class="form-select" name="service_id">
                    <option value="">—</option>
                    <?php foreach ($services as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= (string) old('service_id', $sSuggere) === (string) $s['id'] ? 'selected' : '' ?>><?= esc($s['nom']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3"><label class="form-label">Signature</label>
                <input type="date" class="form-control" name="date_signature" value="<?= esc($v('date_signature')) ?>"></div>
            <div class="col-md-3"><label class="form-label">Début * <?= $conf('date_debut') ?></label>
                <input type="date" class="form-control" name="date_debut" value="<?= esc($v('date_debut')) ?>" required></div>
            <div class="col-md-3"><label class="form-label">Fin * <?= $conf('date_fin') ?></label>
                <input type="date" class="form-control" name="date_fin" value="<?= esc($v('date_fin')) ?>" required></div>
            <div class="col-md-3"><label class="form-label">Durée (mois)</label>
                <input type="number" min="1" class="form-control" name="duree_mois" value="<?= esc($v('duree_mois')) ?>"></div>
            <div class="col-md-3"><label class="form-label">Préavis (jours) * <?= $conf('preavis_jours') ?></label>
                <input type="number" min="0" class="form-control" name="preavis_jours" value="<?= esc($v('preavis_jours', 30)) ?>" required></div>
            <div class="col-md-3 d-flex align-items-end">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="rt" name="reconduction_tacite" value="1" <?= old('reconduction_tacite', ! empty($d['reconduction_tacite']) ? 1 : 0) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="rt">Reconduction tacite <?= $conf('reconduction_tacite') ?></label>
                </div>
            </div>
            <div class="col-md-3"><label class="form-label">Montant <?= $conf('montant') ?></label>
                <input type="number" step="0.01" min="0" class="form-control" name="montant" value="<?= esc($v('montant')) ?>"></div>
            <div class="col-md-3"><label class="form-label">Devise *</label>
                <input class="form-control" name="devise" maxlength="3" value="<?= esc($v('devise', 'MGA')) ?>" required></div>
            <div class="col-12"><label class="form-label">Pénalités</label>
                <textarea class="form-control" name="penalite_description" rows="2"><?= esc(old('penalite_description', $d['penalites'] ?? '')) ?></textarea></div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">Clauses détectées (<?= count($d['clauses'] ?? []) ?>)</div>
        <div class="card-body p-0">
            <table class="table align-middle mb-0">
                <thead><tr><th>Garder</th><th>Type</th><th>Texte</th><th>Page</th><th>Confiance</th></tr></thead>
                <tbody>
                <?php foreach (($d['clauses'] ?? []) as $i => $c): $cf = isset($c['confiance']) ? (float) $c['confiance'] : null; ?>
                    <tr>
                        <td><input class="form-check-input" type="checkbox" name="clauses[<?= $i ?>][retenir]" value="1" <?= ($cf === null || $cf >= 0.5) ? 'checked' : '' ?>></td>
                        <td>
                            <select class="form-select form-select-sm" name="clauses[<?= $i ?>][type_clause]">
                                <?php foreach ($typesClause as $code => $lib): ?>
                                    <option value="<?= $code ?>" <?= ($c['type_clause'] ?? 'autre') === $code ? 'selected' : '' ?>><?= esc($lib) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><textarea class="form-control form-control-sm" rows="2" name="clauses[<?= $i ?>][texte]"><?= esc($c['texte'] ?? '') ?></textarea></td>
                        <td style="width:80px"><input type="number" min="1" class="form-control form-control-sm" name="clauses[<?= $i ?>][page]" value="<?= esc((string) ($c['page'] ?? '')) ?>"></td>
                        <td>
                            <input type="hidden" name="clauses[<?= $i ?>][confiance]" value="<?= esc((string) ($cf ?? '')) ?>">
                            <?php if ($cf !== null): ?><span class="badge <?= $cf >= 0.8 ? 'bg-success' : ($cf >= 0.5 ? 'bg-warning text-dark' : 'bg-danger') ?>"><?= round($cf * 100) ?> %</span><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button class="btn btn-success" type="submit">Valider et créer le contrat</button>
        <a href="<?= base_url('ia') ?>" class="btn btn-outline-secondary">Annuler</a>
    </div>
</form>
<form action="<?= base_url('ia/rejeter/' . $e['id']) ?>" method="post" class="mt-2" onsubmit="return confirm('Rejeter cette extraction ?');">
    <?= csrf_field() ?><button class="btn btn-outline-danger" type="submit">Rejeter l'extraction</button>
</form>
<?php endif; ?>
<?= $this->endSection() ?>