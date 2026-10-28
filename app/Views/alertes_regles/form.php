<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php helper('form'); ?>

<div class="card" style="max-width: 640px;">
    <div class="card-body">
        <form method="post" action="<?= esc($action, 'attr') ?>" novalidate>
            <?= csrf_field() ?>

            <div class="mb-3">
                <label class="form-label">Code</label>
                <input type="text" class="form-control" value="<?= esc($regle['code']) ?>" disabled>
                <div class="form-text">Le code ne peut pas être modifié.</div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="jours_avant">Jours avant <span class="text-danger">*</span></label>
                <input type="number" class="form-control" id="jours_avant" name="jours_avant" min="0" step="1" required
                       value="<?= esc(old('jours_avant', $regle['jours_avant']), 'attr') ?>">
                <div class="form-text">Jours positifs ou nuls.</div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="gravite">Gravité <span class="text-danger">*</span></label>
                <select class="form-select" id="gravite" name="gravite" required>
                    <?php $choisie = old('gravite', $regle['gravite']); ?>
                    <?php foreach ($gravites as $code => $libelle): ?>
                        <option value="<?= esc($code, 'attr') ?>" <?= $choisie === $code ? 'selected' : '' ?>><?= esc($libelle) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="notifier_superieur" name="notifier_superieur" value="1"
                           <?= old('notifier_superieur', $regle['notifier_superieur']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="notifier_superieur">Notifier le supérieur hiérarchique</label>
                </div>
            </div>

            <div class="mb-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="actif" name="actif" value="1"
                           <?= old('actif', $regle['actif']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="actif">Actif</label>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Enregistrer</button>
            <a class="btn btn-outline-secondary" href="<?= site_url('alertes-regles') ?>">Annuler</a>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
