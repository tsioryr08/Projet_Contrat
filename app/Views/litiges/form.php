<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<h1 class="h3 mb-3"><?= esc($titre) ?></h1>

<?php if (session()->getFlashdata('erreurs')): ?>
    <div class="alert alert-danger"><ul class="mb-0">
        <?php foreach (session()->getFlashdata('erreurs') as $e): ?><li><?= esc($e) ?></li><?php endforeach; ?>
    </ul></div>
<?php endif; ?>

<form action="<?= base_url('litiges/enregistrer') ?>" method="post" class="card card-body">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="contrat_id">Contrat *</label>
            <select class="form-select" id="contrat_id" name="contrat_id" required>
                <option value="">Choisir…</option>
                <?php foreach ($contrats as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= (string) old('contrat_id', $contratId) === (string) $c['id'] ? 'selected' : '' ?>>
                        <?= esc($c['reference'] . ' — ' . $c['intitule']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="type_litige">Type *</label>
            <select class="form-select" id="type_litige" name="type_litige" required>
                <option value="">Choisir…</option>
                <?php foreach ($types as $c => $l): ?>
                    <option value="<?= $c ?>" <?= old('type_litige') === $c ? 'selected' : '' ?>><?= esc($l) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12">
            <label class="form-label" for="fondement">Fondement juridique ou contractuel</label>
            <textarea class="form-control" id="fondement" name="fondement" rows="2"><?= esc(old('fondement')) ?></textarea>
        </div>
        <div class="col-12">
            <label class="form-label" for="description">Description *</label>
            <textarea class="form-control" id="description" name="description" rows="3" required><?= esc(old('description')) ?></textarea>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="montant_enjeu">Montant en jeu</label>
            <input type="number" step="0.01" min="0" class="form-control" id="montant_enjeu" name="montant_enjeu" value="<?= esc(old('montant_enjeu')) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="responsable_id">Responsable</label>
            <select class="form-select" id="responsable_id" name="responsable_id">
                <option value="">—</option>
                <?php foreach ($responsables as $r): ?>
                    <option value="<?= $r['id'] ?>" <?= (string) old('responsable_id', session()->get('utilisateur_id')) === (string) $r['id'] ? 'selected' : '' ?>><?= esc($r['nom']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="date_echeance_action">Échéance d'action</label>
            <input type="date" class="form-control" id="date_echeance_action" name="date_echeance_action" value="<?= esc(old('date_echeance_action')) ?>">
        </div>
    </div>
    <div class="d-flex gap-2 mt-3">
        <button class="btn btn-primary" type="submit">Enregistrer</button>
        <a href="<?= base_url('litiges') ?>" class="btn btn-outline-secondary">Annuler</a>
    </div>
</form>
<?= $this->endSection() ?>