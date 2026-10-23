<?php
/**
 * Pagination : <?= view('partials/pagination', ['pager' => $pager]) ?>
 * Nécessite le modèle 'bootstrap_full' déclaré dans Config/Pager.php.
 */
?>
<?= $pager->links('default', 'bootstrap_full') ?>
