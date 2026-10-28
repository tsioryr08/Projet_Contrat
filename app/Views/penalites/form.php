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
                        <?php $choisiContrat = old('contrat_id', $penalite['contrat_id'] ?? ''); ?>
                        <?php foreach ($contrats as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" <?= (string) $choisiContrat === (string) $c['id'] ? 'selected' : '' ?>>
                                <?= esc($c['reference']) ?> - <?= isset($c['fournisseur_nom']) ? esc($c['fournisseur_nom']) : '' ?> - <?= esc($c['intitule']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="litige_id">Litige (facultatif)</label>
                    <select class="form-select" id="litige_id" name="litige_id">
                        <option value="">— Aucun —</option>
                        <?php $choisiLitige = old('litige_id', $penalite['litige_id'] ?? ''); ?>
                        <?php foreach ($litiges as $l): ?>
                            <option value="<?= (int) $l['id'] ?>" <?= (string) $choisiLitige === (string) $l['id'] ? 'selected' : '' ?>>
                                Litige #<?= (int) $l['id'] ?> - <?= esc($l['contrat_reference']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="nature">Nature <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="nature" name="nature" maxlength="150" required
                           value="<?= esc(old('nature', $penalite['nature'] ?? ''), 'attr') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="montant">Montant (Ar) <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="montant" name="montant" step="0.01" min="0" required
                           value="<?= esc(old('montant', $penalite['montant'] ?? ''), 'attr') ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="date_fait_generateur">Date du fait générateur <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="date_fait_generateur" name="date_fait_generateur" required
                           value="<?= esc(old('date_fait_generateur', $penalite['date_fait_generateur'] ?? ''), 'attr') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="date_decouverte">Date de découverte <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="date_decouverte" name="date_decouverte" required
                           value="<?= esc(old('date_decouverte', $penalite['date_decouverte'] ?? ''), 'attr') ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="statut">Statut <span class="text-danger">*</span></label>
                    <select class="form-select" id="statut" name="statut" required>
                        <?php $choisiStatut = old('statut', $penalite['statut'] ?? 'estimee'); ?>
                        <?php foreach ($statuts as $code => $libelle): ?>
                            <option value="<?= esc($code, 'attr') ?>" <?= $choisiStatut === $code ? 'selected' : '' ?>><?= esc($libelle) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label" for="motif">Motif</label>
                    <textarea class="form-control" id="motif" name="motif" rows="3"><?= esc(old('motif', $penalite['motif'] ?? ''), 'attr') ?></textarea>
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary">Enregistrer</button>
                <a class="btn btn-outline-secondary" href="<?= site_url('penalites') ?>">Annuler</a>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
