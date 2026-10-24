// Boîte de confirmation : tout élément avec data-confirm-url ouvre la modale.
document.addEventListener('click', function (e) {
    var declencheur = e.target.closest('[data-confirm-url]');
    if (!declencheur) { return; }

    document.getElementById('formConfirmation').action = declencheur.dataset.confirmUrl;
    document.getElementById('texteConfirmation').textContent =
        declencheur.dataset.confirmText || 'Confirmer cette action ?';
    new bootstrap.Modal(document.getElementById('modalConfirmation')).show();
});
