<?php $pager->setSurroundCount(2); ?>
<nav aria-label="Pagination">
    <ul class="pagination justify-content-center">
        <?php if ($pager->hasPrevious()): ?>
            <li class="page-item"><a class="page-link" href="<?= $pager->getFirst() ?>" aria-label="Première">«</a></li>
            <li class="page-item"><a class="page-link" href="<?= $pager->getPreviousPage() ?>" aria-label="Précédente">‹</a></li>
        <?php endif; ?>

        <?php foreach ($pager->links() as $lien): ?>
            <li class="page-item <?= $lien['active'] ? 'active' : '' ?>">
                <a class="page-link" href="<?= $lien['uri'] ?>"><?= $lien['title'] ?></a>
            </li>
        <?php endforeach; ?>

        <?php if ($pager->hasNext()): ?>
            <li class="page-item"><a class="page-link" href="<?= $pager->getNextPage() ?>" aria-label="Suivante">›</a></li>
            <li class="page-item"><a class="page-link" href="<?= $pager->getLast() ?>" aria-label="Dernière">»</a></li>
        <?php endif; ?>
    </ul>
</nav>
