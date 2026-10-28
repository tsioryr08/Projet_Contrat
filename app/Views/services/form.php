<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php helper('form'); ?>

<div class="card" style="max-width: 640px;">
    <div class="card-body">
        <form method="post" action="<?= esc($action, 'attr') ?>" novalidate>
            <?= csrf_field() ?>

            <div class="mb-3">
                <label class="form-label" for="nom">Nom du service <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="nom" name="nom" maxlength="100" required
                       value="<?= esc(old('nom', $service['nom'] ?? ''), 'attr') ?>">
            </div>

            <div class="mb-4">
                <label class="form-label" for="responsable_id">Responsable</label>
                <select class="form-select" id="responsable_id" name="responsable_id">
                    <option value="">— Aucun —</option>
                    <?php $choisi = (string) old('responsable_id', $service['responsable_id'] ?? ''); ?>
                    <?php foreach ($responsables as $r): ?>
                        <option value="<?= (int) $r['id'] ?>" <?= $choisi === (string) $r['id'] ? 'selected' : '' ?>>
                            <?= esc($r['nom_complet']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Seuls les utilisateurs actifs sont proposés.</div>
            </div>

            <button type="submit" class="btn btn-primary">Enregistrer</button>
            <a class="btn btn-outline-secondary" href="<?= site_url('services') ?>">Annuler</a>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
