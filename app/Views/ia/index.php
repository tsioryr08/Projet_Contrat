<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<h1 class="h3 mb-3">Extraction de clauses par IA</h1>

<?php if ($simulation): ?>
    <div class="alert alert-warning">Mode <strong>simulation</strong> : un JSON fixe est renvoyé, aucun appel API (<code>ia.mode = reel</code> dans <code>.env</code> pour l'activer).</div>
<?php endif; ?>
<?php if (session()->getFlashdata('erreurs')): ?>
    <div class="alert alert-danger"><?php foreach (session()->getFlashdata('erreurs') as $e): ?><div><?= esc($e) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<form action="<?= base_url('ia/televerser') ?>" method="post" enctype="multipart/form-data" class="card card-body mb-4">
    <?= csrf_field() ?>
    <div class="row g-3 align-items-end">
        <div class="col-md-5">
            <label class="form-label" for="fichier">Contrat (PDF ou DOCX, 10 Mo max)</label>
            <input type="file" class="form-control" id="fichier" name="fichier" accept=".pdf,.docx" required>
        </div>
        <div class="col-md-4">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="confidentiel" name="confidentiel" value="1">
                <label class="form-check-label" for="confidentiel">Confidentiel : ne rien envoyer à l'IA</label>
            </div>
        </div>
        <div class="col-md-3"><button class="btn btn-primary w-100" type="submit">Téléverser et analyser</button></div>
    </div>
</form>

<div class="table-responsive">
    <table class="table align-middle">
        <thead class="table-light"><tr><th>Document</th><th>Date</th><th>Confidentiel</th><th>Extraction</th><th></th></tr></thead>
        <tbody>
        <?php if (! $documents): ?><tr><td colspan="5" class="text-center text-muted py-4">Aucun document.</td></tr><?php endif; ?>
        <?php foreach ($documents as $d): ?>
            <tr>
                <td><?= esc($d['nom']) ?></td>
                <td><?= $d['date'] ? esc(date('d/m/Y H:i', strtotime($d['date']))) : '—' ?></td>
                <td><?= $d['confidentiel'] ? '<span class="badge bg-dark">Oui</span>' : 'Non' ?></td>
                <td>
                    <?php if ($d['statut']): ?>
                        <span class="badge <?= ['en_attente' => 'bg-warning text-dark', 'validee' => 'bg-success', 'erreur' => 'bg-danger', 'rejetee' => 'bg-secondary'][$d['statut']] ?? 'bg-light text-dark' ?>"><?= esc($d['statut']) ?></span>
                        <?php if ($d['erreur']): ?><small class="text-danger d-block"><?= esc($d['erreur']) ?></small><?php endif; ?>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td class="text-end">
                    <?php if ($d['statut'] === 'en_attente'): ?>
                        <a class="btn btn-sm btn-primary" href="<?= base_url('ia/valider/' . $d['extraction_id']) ?>">Valider</a>
                    <?php elseif ($d['contrat_id']): ?>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= base_url('contrats/voir/' . $d['contrat_id']) ?>">Contrat</a>
                    <?php endif; ?>
                    <?php if (! $d['confidentiel'] && in_array($d['statut'], ['erreur', 'rejetee', null], true)): ?>
                        <form action="<?= base_url('ia/relancer/' . $d['id']) ?>" method="post" class="d-inline"><?= csrf_field() ?>
                            <button class="btn btn-sm btn-outline-warning" type="submit">Relancer</button></form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?= $this->endSection() ?>