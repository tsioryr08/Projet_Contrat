<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php $date = fn($d) => $d ? date_fr($d) : '—'; ?>

<?php if (session()->getFlashdata('erreurs')): ?>
    <div class="alert alert-danger"><?php foreach (session()->getFlashdata('erreurs') as $e): ?><div><?= esc($e) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-start mb-3">
    <div>
        <h1 class="h3 mb-1"><?= esc($l['reference']) ?></h1>
        <span class="badge bg-secondary"><?= esc($etats[$l['etat_code']] ?? $l['etat_code']) ?></span>
        <span class="badge bg-light text-dark border"><?= esc($types[$l['type_litige']] ?? $l['type_litige']) ?></span>
        <?php if ($l['issue'] !== 'en_cours'): ?>
            <span class="badge bg-info text-dark"><?= esc($issues[$l['issue']] ?? $l['issue']) ?></span>
        <?php endif; ?>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('litiges') ?>" class="btn btn-outline-secondary">Retour</a>
        <a href="<?= base_url('contrats/voir/' . $l['contrat_id']) ?>" class="btn btn-outline-primary">Contrat <?= esc($l['contrat_reference']) ?></a>
        <a href="<?= base_url('penalites?contrat_id=' . $l['contrat_id']) ?>" class="btn btn-outline-secondary">Pénalités du contrat</a>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">Détail</div>
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-md-3">Contrat</dt><dd class="col-md-9"><?= esc($l['contrat_reference'] . ' — ' . $l['contrat_intitule']) ?></dd>
            <dt class="col-md-3">Fondement</dt><dd class="col-md-9"><?= nl2br(esc($l['fondement'] ?? '—')) ?></dd>
            <dt class="col-md-3">Description</dt><dd class="col-md-9"><?= nl2br(esc($l['description'])) ?></dd>
            <dt class="col-md-3">Montant en jeu</dt><dd class="col-md-9"><?= $l['montant_enjeu'] !== null ? number_format((float) $l['montant_enjeu'], 0, ',', ' ') : '—' ?></dd>
            <dt class="col-md-3">Responsable</dt><dd class="col-md-9"><?= esc($l['responsable_nom'] ?: '—') ?></dd>
            <dt class="col-md-3">Ouvert le</dt><dd class="col-md-9"><?= $date($l['date_ouverture']) ?></dd>
            <dt class="col-md-3">Échéance d'action</dt><dd class="col-md-9"><?= $date($l['date_echeance_action']) ?></dd>
            <dt class="col-md-3">Clôturé le</dt><dd class="col-md-9"><?= $date($l['date_cloture']) ?></dd>
        </dl>
    </div>
</div>

<?php if ($transitions): ?>
<div class="card mb-3">
    <div class="card-header">Faire avancer le litige</div>
    <div class="card-body">
        <form action="<?= base_url('litiges/transition/' . $l['id']) ?>" method="post" class="row g-2 align-items-end">
            <?= csrf_field() ?>
            <div class="col-md-3">
                <label class="form-label" for="etat_cible">Nouvel état</label>
                <select class="form-select" id="etat_cible" name="etat_cible" required>
                    <?php foreach ($transitions as $t): ?><option value="<?= esc($t) ?>"><?= esc($etats[$t] ?? $t) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3" id="bloc_issue" style="display:none">
                <label class="form-label" for="issue">Issue *</label>
                <select class="form-select" id="issue" name="issue">
                    <option value="">Choisir…</option>
                    <?php foreach ($issues as $c => $lib): ?><option value="<?= $c ?>"><?= esc($lib) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="commentaire">Commentaire *</label>
                <input class="form-control" id="commentaire" name="commentaire" required>
            </div>
            <div class="col-md-2"><button class="btn btn-primary w-100" type="submit">Appliquer</button></div>
            <?php if ($l['contrat_statut'] !== 'cloture'): ?>
            <div class="col-12" id="bloc_cloture" style="display:none">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="cloturer_contrat" name="cloturer_contrat" value="1">
                    <label class="form-check-label" for="cloturer_contrat">Clôturer aussi le contrat (motif : résilié)</label>
                </div>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>
<script>
(function () {
    const c = document.getElementById('etat_cible'), bi = document.getElementById('bloc_issue'),
          i = document.getElementById('issue'), bc = document.getElementById('bloc_cloture');
    function maj() {
        const res = c.value === 'resolution';
        bi.style.display = res ? '' : 'none'; i.required = res;
        if (bc) bc.style.display = (res && i.value === 'resolu_resiliation') ? '' : 'none';
    }
    c.addEventListener('change', maj); i.addEventListener('change', maj); maj();
})();
</script>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-header">Historique</div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0">
            <thead><tr><th>Date</th><th>De</th><th>Vers</th><th>Utilisateur</th><th>Commentaire</th></tr></thead>
            <tbody>
            <?php foreach ($historique as $h): ?>
                <tr>
                    <td><?= $h['date'] ? esc(date('d/m/Y H:i', strtotime($h['date']))) : '—' ?></td>
                    <td><?= $h['avant'] ? esc($etats[$h['avant']] ?? $h['avant']) : '—' ?></td>
                    <td><?= esc($etats[$h['apres']] ?? $h['apres']) ?></td>
                    <td><?= esc($h['utilisateur']) ?></td>
                    <td><?= esc($h['commentaire'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>