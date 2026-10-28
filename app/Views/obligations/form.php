<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php helper('form'); ?>

<div class="card" style="max-width: 860px;">
    <div class="card-body">
        <form method="post" action="<?= esc($action, 'attr') ?>" novalidate>
            <?= csrf_field() ?>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="contrat_id">Contrat <span class="text-danger">*</span></label>
                    <select class="form-select" id="contrat_id" name="contrat_id" required>
                        <option value="">— Sélectionner —</option>
                        <?php $choisiContrat = old('contrat_id', $contratId ?: ($obligation['contrat_id'] ?? '')); ?>
                        <?php foreach ($contrats as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" <?= (string) $choisiContrat === (string) $c['id'] ? 'selected' : '' ?>>
                                <?= esc($c['reference']) ?> - <?= isset($c['fournisseur_nom']) ? esc($c['fournisseur_nom']) : '' ?> - <?= esc($c['intitule']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="type_obligation">Type d'obligation <span class="text-danger">*</span></label>
                    <select class="form-select" id="type_obligation" name="type_obligation" required>
                        <option value="">— Sélectionner —</option>
                        <?php $choisiType = old('type_obligation', $obligation['type_obligation'] ?? ''); ?>
                        <?php foreach ($types as $code => $libelle): ?>
                            <option value="<?= esc($code, 'attr') ?>" <?= $choisiType === $code ? 'selected' : '' ?>><?= esc($libelle) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="debiteur">Débiteur <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="debiteur" name="debiteur" maxlength="150" required
                           value="<?= esc(old('debiteur', $obligation['debiteur'] ?? ''), 'attr') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="creancier">Créancier <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="creancier" name="creancier" maxlength="150" required
                           value="<?= esc(old('creancier', $obligation['creancier'] ?? ''), 'attr') ?>">
                </div>

                <div class="col-12">
                    <label class="form-label" for="description">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="3"><?= esc(old('description', $obligation['description'] ?? ''), 'attr') ?></textarea>
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="montant">Montant (Ar)</label>
                    <input type="number" class="form-control" id="montant" name="montant" step="0.01" min="0"
                           value="<?= esc(old('montant', $obligation['montant'] ?? ''), 'attr') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="echeance">Échéance <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="echeance" name="echeance" required
                           value="<?= esc(old('echeance', $obligation['echeance'] ?? ''), 'attr') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="statut">Statut <span class="text-danger">*</span></label>
                    <select class="form-select" id="statut" name="statut" required>
                        <?php $choisiStatut = old('statut', $obligation['statut'] ?? 'a_venir'); ?>
                        <?php 
                        $statutsForm = $statuts;
                        unset($statutsForm['en_retard']);
                        ?>
                        <?php foreach ($statutsForm as $code => $libelle): ?>
                            <option value="<?= esc($code, 'attr') ?>" <?= $choisiStatut === $code ? 'selected' : '' ?>><?= esc($libelle) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Le statut « En retard » est attribué automatiquement.</div>
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="executee_le">Exécutée le</label>
                    <input type="date" class="form-control" id="executee_le" name="executee_le"
                           value="<?= esc(old('executee_le', $obligation['executee_le'] ?? ''), 'attr') ?>">
                    <div class="form-text">Rempli automatiquement si statut « Exécutée ».</div>
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary">Enregistrer</button>
                <a class="btn btn-outline-secondary" href="<?= site_url('obligations') ?>">Annuler</a>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
