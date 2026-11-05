<?php
/** Expects $page (App\Core\Page). Keeps the current filters in every link. */
$pages = $page->pages();
if ($page->total === 0) {
    return;
}
$from = ($page->page - 1) * $page->perPage + 1;
$to = min($page->total, $page->page * $page->perPage);
?>
<div class="pagination">
    <span>Showing <?= e($from) ?>-<?= e($to) ?> of <?= e($page->total) ?></span>
    <?php if ($pages > 1): ?>
        <nav aria-label="Pagination">
            <?php if ($page->page > 1): ?><a href="<?= e(url_with(['page' => $page->page - 1])) ?>" rel="prev">Previous</a><?php else: ?><span class="page disabled">Previous</span><?php endif; ?>
            <?php for ($i = max(1, $page->page - 2); $i <= min($pages, $page->page + 2); $i++): ?>
                <?php if ($i === $page->page): ?><span class="page current" aria-current="page"><?= e($i) ?></span>
                <?php else: ?><a href="<?= e(url_with(['page' => $i])) ?>"><?= e($i) ?></a><?php endif; ?>
            <?php endfor; ?>
            <?php if ($page->page < $pages): ?><a href="<?= e(url_with(['page' => $page->page + 1])) ?>" rel="next">Next</a><?php else: ?><span class="page disabled">Next</span><?php endif; ?>
        </nav>
    <?php endif; ?>
</div>
