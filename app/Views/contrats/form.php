<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$v      = fn(string $k, $def = '') => old($k, $contrat[$k] ?? $def);
$action = $modeNouveau ? base_url('contrats/enregistrer') : base_url('contrats/mettre-a-jour/' . $existant['id']);
?>
<h1 class="h3 mb-3"><?= esc($titre) ?></h1>

<?php if (session()->getFlashdata('erreurs')): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach (session()->getFlashdata('erreurs') as $e): ?><li><?= esc($e) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form action="<?= $action ?>" method="post">
    <?= csrf_field() ?>

    <div class="card mb-3">
        <div class="card-header">Identification</div>
        <div class="card-body row g-3">
            <div class="col-md-8">
                <label class="form-label" for="intitule">Intitulé *</label>
                <input class="form-control" id="intitule" name="intitule" maxlength="200" value="<?= esc($v('intitule')) ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="type_id">Type de contrat *</label>
                <select class="form-select" id="type_id" name="type_id" required>
                    <option value="">Choisir…</option>
                    <?php $famille = null; foreach ($types as $t): ?>
                        <?php if ($t['famille'] !== $famille): if ($famille !== null) echo '</optgroup>'; $famille = $t['famille']; ?>
                            <optgroup label="<?= esc($famille) ?>">
                        <?php endif; ?>
                        <option value="<?= $t['id'] ?>"
                                data-preavis="<?= (int) $t['preavis_defaut_jours'] ?>"
                                data-reconduction="<?= (int) $t['reconduction_tacite_defaut'] ?>"
                                data-duree="<?= esc((string) $t['duree_defaut_mois']) ?>"
                                <?= (string) $v('type_id') === (string) $t['id'] ? 'selected' : '' ?>>
                            <?= esc($t['libelle']) ?>
                        </option>
                    <?php endforeach; if ($famille !== null) echo '</optgroup>'; ?>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label" for="objet">Objet</label>
                <textarea class="form-control" id="objet" name="objet" rows="2"><?= esc($v('objet')) ?></textarea>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="fournisseur_id">Fournisseur (partie) *</label>
                <select class="form-select" id="fournisseur_id" name="fournisseur_id" required>
                    <option value="">Choisir…</option>
                    <?php foreach ($fournisseurs as $f): ?>
                        <option value="<?= $f['id'] ?>" <?= (string) $v('fournisseur_id') === (string) $f['id'] ? 'selected' : '' ?>><?= esc($f['raison_sociale']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="service_id">Service (partie)</label>
                <select class="form-select" id="service_id" name="service_id">
                    <option value="">—</option>
                    <?php foreach ($services as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= (string) $v('service_id') === (string) $s['id'] ? 'selected' : '' ?>><?= esc($s['nom']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="responsable_id">Responsable</label>
                <select class="form-select" id="responsable_id" name="responsable_id">
                    <option value="">—</option>
                    <?php foreach ($utilisateurs as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= (string) $v('responsable_id') === (string) $u['id'] ? 'selected' : '' ?>>
                            <?= esc(trim(($u['prenom'] ?? '') . ' ' . $u['nom'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="confidentiel" name="confidentiel" value="1" <?= $v('confidentiel', 0) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="confidentiel">Contrat confidentiel (aucun texte envoyé à l'IA)</label>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">Dates, durée et reconduction</div>
        <div class="card-body row g-3">
            <div class="col-md-3">
                <label class="form-label" for="date_signature">Date de signature</label>
                <input type="date" class="form-control" id="date_signature" name="date_signature" value="<?= esc($v('date_signature')) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="date_debut">Date de début *</label>
                <input type="date" class="form-control" id="date_debut" name="date_debut" value="<?= esc($v('date_debut')) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="date_fin">Date de fin *</label>
                <input type="date" class="form-control" id="date_fin" name="date_fin" value="<?= esc($v('date_fin')) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="duree_mois">Durée (mois)</label>
                <input type="number" min="1" class="form-control" id="duree_mois" name="duree_mois" value="<?= esc($v('duree_mois')) ?>" placeholder="Calculée si vide">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="preavis_jours">Préavis (jours) *</label>
                <input type="number" min="0" class="form-control" id="preavis_jours" name="preavis_jours" value="<?= esc($v('preavis_jours', 30)) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="duree_reconduction_mois">Durée de reconduction (mois)</label>
                <input type="number" min="1" class="form-control" id="duree_reconduction_mois" name="duree_reconduction_mois" value="<?= esc($v('duree_reconduction_mois')) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="nb_reconductions_max">Reconductions max.</label>
                <input type="number" min="0" class="form-control" id="nb_reconductions_max" name="nb_reconductions_max" value="<?= esc($v('nb_reconductions_max')) ?>" placeholder="Illimité si vide">
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="reconduction_tacite" name="reconduction_tacite" value="1" <?= $v('reconduction_tacite', 0) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="reconduction_tacite">Reconduction tacite</label>
                </div>
            </div>
            <div class="col-12">
                <div class="alert alert-info mb-0">
                    Date limite de non-reconduction (aperçu) : <strong id="apercu_limite">—</strong>
                    <small class="text-muted">= date de fin − préavis ; la valeur définitive est calculée par la base.</small>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">Finances</div>
        <div class="card-body row g-3">
            <div class="col-md-3">
                <label class="form-label" for="montant">Montant</label>
                <input type="number" step="0.01" min="0" class="form-control" id="montant" name="montant" value="<?= esc($v('montant')) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="devise">Devise *</label>
                <input class="form-control" id="devise" name="devise" maxlength="3" value="<?= esc($v('devise', 'MGA')) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="periodicite_paiement">Périodicité de paiement</label>
                <select class="form-select" id="periodicite_paiement" name="periodicite_paiement">
                    <option value="">—</option>
                    <?php foreach ($periodicites as $p): ?>
                        <option value="<?= $p ?>" <?= $v('periodicite_paiement') === $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="indexation">Indexation</label>
                <input class="form-control" id="indexation" name="indexation" maxlength="150" value="<?= esc($v('indexation')) ?>">
            </div>
            <div class="col-12">
                <label class="form-label" for="garantie">Garantie</label>
                <textarea class="form-control" id="garantie" name="garantie" rows="2"><?= esc($v('garantie')) ?></textarea>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">Pénalités, résiliation et juridiction</div>
        <div class="card-body row g-3">
            <div class="col-12">
                <label class="form-label" for="penalite_description">Pénalités (description)</label>
                <textarea class="form-control" id="penalite_description" name="penalite_description" rows="2"><?= esc($v('penalite_description')) ?></textarea>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="penalite_taux">Taux de pénalité (%)</label>
                <input type="number" step="0.01" min="0" max="100" class="form-control" id="penalite_taux" name="penalite_taux" value="<?= esc($v('penalite_taux')) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="penalite_plafond">Plafond de pénalité</label>
                <input type="number" step="0.01" min="0" class="form-control" id="penalite_plafond" name="penalite_plafond" value="<?= esc($v('penalite_plafond')) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="juridiction">Juridiction</label>
                <input class="form-control" id="juridiction" name="juridiction" maxlength="150" value="<?= esc($v('juridiction')) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="loi_applicable">Loi applicable</label>
                <input class="form-control" id="loi_applicable" name="loi_applicable" maxlength="100" value="<?= esc($v('loi_applicable', 'Droit malgache')) ?>">
            </div>
            <div class="col-12">
                <label class="form-label" for="clause_resiliation">Clause de résiliation</label>
                <textarea class="form-control" id="clause_resiliation" name="clause_resiliation" rows="2"><?= esc($v('clause_resiliation')) ?></textarea>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">Enregistrer</button>
        <a href="<?= base_url($modeNouveau ? 'contrats' : 'contrats/voir/' . $existant['id']) ?>" class="btn btn-outline-secondary">Annuler</a>
    </div>
</form>

<script>
(function () {
    const PREFILL = <?= $modeNouveau ? 'true' : 'false' ?>;
    const type = document.getElementById('type_id');
    const preavis = document.getElementById('preavis_jours');
    const recond = document.getElementById('reconduction_tacite');
    const duree = document.getElementById('duree_mois');
    const fin = document.getElementById('date_fin');
    const apercu = document.getElementById('apercu_limite');

    function majApercu() {
        if (!fin.value || preavis.value === '') { apercu.textContent = '—'; return; }
        const d = new Date(fin.value + 'T00:00:00');
        d.setDate(d.getDate() - parseInt(preavis.value, 10));
        apercu.textContent = d.toLocaleDateString('fr-FR');
    }

    type.addEventListener('change', function () {
        if (!PREFILL) return;
        const o = type.selectedOptions[0];
        if (!o || o.dataset.preavis === undefined) return;
        preavis.value = o.dataset.preavis;
        recond.checked = o.dataset.reconduction === '1';
        if (o.dataset.duree) duree.value = o.dataset.duree;
        majApercu();
    });
    fin.addEventListener('input', majApercu);
    preavis.addEventListener('input', majApercu);
    majApercu();
})();
</script>
<?= $this->endSection() ?>