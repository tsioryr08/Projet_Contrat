<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
helper('form');
$v = static fn (string $champ, string $defaut = '') => old($champ, $fournisseur[$champ] ?? $defaut);
?>

<div class="card" style="max-width: 860px;">
    <div class="card-body">
        <form method="post" action="<?= esc($action, 'attr') ?>" novalidate>
            <?= csrf_field() ?>

            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label" for="raison_sociale">Raison sociale <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="raison_sociale" name="raison_sociale" maxlength="150" required
                           value="<?= esc($v('raison_sociale'), 'attr') ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="nif">NIF <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="nif" name="nif" maxlength="10" inputmode="numeric" required pattern="[0-9]{10}"
                           value="<?= esc($v('nif'), 'attr') ?>">
                    <div class="form-text">10 chiffres obligatoires.</div>
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="stat">STAT <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="stat" name="stat" maxlength="17" inputmode="numeric" required pattern="[0-9]{17}"
                           value="<?= esc($v('stat'), 'attr') ?>">
                    <div class="form-text">17 chiffres obligatoires. Les 8e à 11e chiffres sont l’année de création (ex. 2025).</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="rcs">RCS <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="rcs" name="rcs" maxlength="30" required
                           value="<?= esc($v('rcs'), 'attr') ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="secteur">Secteur d’activité <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="secteur" name="secteur" maxlength="100" required
                           value="<?= esc($v('secteur'), 'attr') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="representant_legal">Représentant légal <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="representant_legal" name="representant_legal" maxlength="120" required
                           value="<?= esc($v('representant_legal'), 'attr') ?>">
                </div>

                <div class="col-12">
                    <label class="form-label" for="adresse">Adresse <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="adresse" name="adresse" maxlength="255" required
                           value="<?= esc($v('adresse'), 'attr') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="ville">Ville <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="ville" name="ville" maxlength="100" required
                           value="<?= esc($v('ville'), 'attr') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="pays">Pays <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="pays" name="pays" maxlength="60" required
                           value="<?= esc($v('pays', 'Madagascar'), 'attr') ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="email">E-mail <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" id="email" name="email" maxlength="150" required
                           value="<?= esc($v('email'), 'attr') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="telephone">Téléphone <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="telephone" name="telephone" maxlength="10" inputmode="numeric" required pattern="[0-9]{10}"
                           value="<?= esc($v('telephone'), 'attr') ?>">
                    <div class="form-text">10 chiffres obligatoires (sans indicatif).</div>
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="statut">Statut <span class="text-danger">*</span></label>
                    <select class="form-select" id="statut" name="statut">
                        <?php foreach ($statuts as $code => $libelle): ?>
                            <option value="<?= esc($code, 'attr') ?>" <?= $v('statut', 'actif') === $code ? 'selected' : '' ?>>
                                <?= esc($libelle) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">« Suspendu » conserve l’historique sans proposer le fournisseur.</div>
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary">Enregistrer</button>
                <a class="btn btn-outline-secondary"
                   href="<?= site_url($fournisseur ? 'fournisseurs/voir/' . (int) $fournisseur['id'] : 'fournisseurs') ?>">Annuler</a>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
