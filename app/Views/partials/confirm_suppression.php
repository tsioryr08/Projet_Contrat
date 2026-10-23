<?php
/**
 * Boîte de confirmation de suppression, incluse une fois dans le gabarit.
 * Utilisation sur un bouton :
 *   <button type="button" class="btn btn-sm btn-outline-danger"
 *           data-confirm-url="<?= site_url('fournisseurs/supprimer/3') ?>"
 *           data-confirm-text="Supprimer ce fournisseur ?">Supprimer</button>
 */
?>
<div class="modal fade" id="modalConfirmation" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" id="formConfirmation" class="modal-content">
            <?= csrf_field() ?>
            <div class="modal-header">
                <h5 class="modal-title">Confirmation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body" id="texteConfirmation">Confirmer cette action ?</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-danger">Confirmer</button>
            </div>
        </form>
    </div>
</div>
