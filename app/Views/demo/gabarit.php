<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php helper('ui'); ?>

<div class="card mb-4">
    <div class="card-header">Badges de niveau d'échéance</div>
    <div class="card-body">
        <?= badge_niveau('ok') ?>
        <?= badge_niveau('a_examiner') ?>
        <?= badge_niveau('urgent') ?>
        <?= badge_niveau('depasse') ?>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">Date et confirmation de suppression</div>
    <div class="card-body">
        <p>Date au format français : <strong><?= date_fr('2026-10-22') ?></strong></p>
        <button type="button" class="btn btn-outline-danger"
                data-confirm-url="<?= site_url('demo/gabarit') ?>"
                data-confirm-text="Voulez-vous vraiment supprimer cet élément ?">
            Tester la confirmation
        </button>
    </div>
</div>
<?= $this->endSection() ?>
