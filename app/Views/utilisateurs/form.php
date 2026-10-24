<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php helper('form'); ?>

<div class="card" style="max-width: 860px;">
    <div class="card-body">
        <form method="post" action="<?= esc($action, 'attr') ?>" novalidate>
            <?= csrf_field() ?>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="nom">Nom <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="nom" name="nom" maxlength="100" required
                           value="<?= esc(old('nom', $utilisateur['nom'] ?? ''), 'attr') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="prenom">Prénom</label>
                    <input type="text" class="form-control" id="prenom" name="prenom" maxlength="100"
                           value="<?= esc(old('prenom', $utilisateur['prenom'] ?? ''), 'attr') ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="email">E-mail <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" id="email" name="email" maxlength="150" required
                           value="<?= esc(old('email', $utilisateur['email'] ?? ''), 'attr') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="role_id">Rôle <span class="text-danger">*</span></label>
                    <select class="form-select" id="role_id" name="role_id" required>
                        <option value="">— Sélectionner —</option>
                        <?php $choisiRole = old('role_id', isset($utilisateur['role_id']) ? $utilisateur['role_id'] : ''); ?>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= (int) $r['id'] ?>" <?= (string) $choisiRole === (string) $r['id'] ? 'selected' : '' ?>>
                                <?= esc($r['libelle']) ?> (<?= esc($r['code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="service_id">Service</label>
                    <select class="form-select" id="service_id" name="service_id">
                        <option value="">— Aucun —</option>
                        <?php $choisiService = old('service_id', $utilisateur['service_id'] ?? ''); ?>
                        <?php foreach ($services as $s): ?>
                            <option value="<?= (int) $s['id'] ?>" <?= (string) $choisiService === (string) $s['id'] ? 'selected' : '' ?>>
                                <?= esc($s['nom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="superieur_id">Supérieur hiérarchique</label>
                    <select class="form-select" id="superieur_id" name="superieur_id">
                        <option value="">— Aucun —</option>
                        <?php $choisiSup = old('superieur_id', $utilisateur['superieur_id'] ?? ''); ?>
                        <?php foreach ($utilisateurs as $u): ?>
                            <option value="<?= (int) $u['id'] ?>" <?= (string) $choisiSup === (string) $u['id'] ? 'selected' : '' ?>>
                                <?= esc($u['nom_complet'] ?: $u['email']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="mot_de_passe">Mot de passe <?= $utilisateur ? '(laisser vide pour ne pas changer)' : '<span class="text-danger">*</span>' ?></label>
                    <input type="password" class="form-control" id="mot_de_passe" name="mot_de_passe" minlength="6" <?= $utilisateur ? '' : 'required' ?>>
                    <div class="form-text">Minimum 6 caractères.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="actif">État</label>
                    <select class="form-select" id="actif" name="actif">
                        <?php $choisiActif = old('actif', $utilisateur['actif'] ?? 1); ?>
                        <option value="1" <?= (string) $choisiActif === '1' ? 'selected' : '' ?>>Actif</option>
                        <option value="0" <?= (string) $choisiActif === '0' ? 'selected' : '' ?>>Inactif</option>
                    </select>
                </div>
            </div>

            <div class="mt-4 d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary">Enregistrer</button>
                <a class="btn btn-outline-secondary" href="<?= site_url('utilisateurs') ?>">Annuler</a>
                <?php if ($utilisateur): ?>
                    <a class="btn btn-outline-warning" href="<?= site_url('utilisateurs/reinitialiser-mot-de-passe/' . (int) $utilisateur['id']) ?>" onclick="return confirm('Réinitialiser le mot de passe de cet utilisateur ?');">Réinitialiser le mot de passe</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
