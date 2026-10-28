<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php helper('form'); ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <a href="<?= site_url('fournisseurs/voir/' . (int) $fournisseur['id']) ?>" class="btn btn-link px-0">← Retour à la fiche fournisseur</a>
    <span class="text-muted"><?= esc($fournisseur['raison_sociale']) ?></span>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><?= $contact ? 'Modifier le contact' : 'Nouveau contact' ?></div>
            <div class="card-body">
                <form method="post" action="<?= esc($action, 'attr') ?>" novalidate>
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label" for="nom">Nom et prénom <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nom" name="nom" maxlength="120" required
                               value="<?= esc(old('nom', $contact['nom'] ?? ''), 'attr') ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="fonction">Fonction</label>
                        <input type="text" class="form-control" id="fonction" name="fonction" maxlength="100"
                               value="<?= esc(old('fonction', $contact['fonction'] ?? ''), 'attr') ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="email">E-mail</label>
                        <input type="email" class="form-control" id="email" name="email" maxlength="150"
                               value="<?= esc(old('email', $contact['email'] ?? ''), 'attr') ?>">
                    </div>

                    <div class="mb-4">
                        <label class="form-label" for="telephone">Téléphone</label>
                        <input type="text" class="form-control" id="telephone" name="telephone" maxlength="10" inputmode="numeric" pattern="[0-9]{10}"
                               value="<?= esc(old('telephone', $contact['telephone'] ?? ''), 'attr') ?>">
                        <div class="form-text">10 chiffres si renseigné (sans indicatif).</div>
                    </div>

                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                    <?php if ($contact): ?>
                        <a class="btn btn-outline-secondary" href="<?= site_url('fournisseurs/contacts/' . (int) $fournisseur['id']) ?>">Annuler</a>
                    <?php else: ?>
                        <a class="btn btn-outline-secondary" href="<?= site_url('fournisseurs/voir/' . (int) $fournisseur['id']) ?>">Annuler</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Contacts existants (<?= count($contacts) ?>)</span>
                <?php if (! $contact): ?>
                    <a class="btn btn-sm btn-outline-primary" href="<?= site_url('fournisseurs/contacts/nouveau/' . (int) $fournisseur['id']) ?>">Nouveau contact</a>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nom</th><th>Fonction</th><th>E-mail</th><th>Téléphone</th><th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($contacts)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-3">Aucun contact pour ce fournisseur.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($contacts as $c): ?>
                        <tr>
                            <td><?= esc($c['nom']) ?></td>
                            <td><?= $c['fonction'] ? esc($c['fonction']) : '<span class="text-muted">—</span>' ?></td>
                            <td><?= $c['email'] ? esc($c['email']) : '<span class="text-muted">—</span>' ?></td>
                            <td><?= $c['telephone'] ? esc($c['telephone']) : '<span class="text-muted">—</span>' ?></td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-outline-primary" href="<?= site_url('fournisseurs/contacts/modifier/' . (int) $c['id']) ?>">Modifier</a>
                                <button type="button" class="btn btn-sm btn-outline-danger"
                                        data-confirm-url="<?= site_url('fournisseurs/contacts/supprimer/' . (int) $c['id']) ?>"
                                        data-confirm-text="<?= esc('Supprimer le contact « ' . $c['nom'] . ' » ?', 'attr') ?>">
                                    Supprimer
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
