<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php helper('form'); ?>

<div class="card" style="max-width: 860px;">
    <div class="card-body">
        <form method="post" action="<?= esc($action, 'attr') ?>" novalidate>
            <?= csrf_field() ?>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="famille">Famille <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="famille" name="famille" maxlength="60" required
                           value="<?= esc(old('famille', $type['famille'] ?? ''), 'attr') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="libelle">Libellé <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="libelle" name="libelle" maxlength="100" required
                           value="<?= esc(old('libelle', $type['libelle'] ?? ''), 'attr') ?>">
                </div>

                <div class="col-12">
                    <label class="form-label" for="description">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="3"><?= esc(old('description', $type['description'] ?? ''), 'attr') ?></textarea>
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="preavis_defaut_jours">Préavis par défaut (jours) <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="preavis_defaut_jours" name="preavis_defaut_jours" min="0" step="1" required
                           value="<?= esc(old('preavis_defaut_jours', $type['preavis_defaut_jours'] ?? 30), 'attr') ?>">
                    <div class="form-text">Positif ou nul.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="duree_defaut_mois">Durée par défaut (mois)</label>
                    <input type="number" class="form-control" id="duree_defaut_mois" name="duree_defaut_mois" min="0" step="1"
                           value="<?= esc(old('duree_defaut_mois', $type['duree_defaut_mois'] ?? ''), 'attr') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="modele_template">Modèle / Template</label>
                    <input type="text" class="form-control" id="modele_template" name="modele_template" maxlength="255"
                           value="<?= esc(old('modele_template', $type['modele_template'] ?? ''), 'attr') ?>">
                </div>

                <div class="col-md-6">
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="reconduction_tacite_defaut" name="reconduction_tacite_defaut" value="1"
                               <?= old('reconduction_tacite_defaut', $type['reconduction_tacite_defaut'] ?? 0) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="reconduction_tacite_defaut">Reconduction tacite par défaut</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="actif" name="actif" value="1"
                               <?= old('actif', $type['actif'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="actif">Actif</label>
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary">Enregistrer</button>
                <a class="btn btn-outline-secondary" href="<?= site_url('types-contrat') ?>">Annuler</a>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
