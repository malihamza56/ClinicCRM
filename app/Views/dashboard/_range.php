<?php /** Date-range chooser. Expects $range (App\Services\DateRange). */
$presets = ['today' => 'Today', '7d' => '7 days', '30d' => '30 days', 'month' => 'This month', '90d' => '90 days'];
?>
<div class="row" style="gap:var(--space-3)">
    <div class="preset-tabs" role="group" aria-label="Date range">
        <?php foreach ($presets as $key => $label): ?>
            <a href="<?= e(url($GLOBALS['current_path'], ['preset' => $key])) ?>" class="<?= $range->preset === $key ? 'is-active' : '' ?>"<?= $range->preset === $key ? ' aria-current="true"' : '' ?>><?= e($label) ?></a>
        <?php endforeach; ?>
    </div>
    <form method="get" class="row" style="gap:6px" data-no-loading>
        <input type="hidden" name="preset" value="custom">
        <label class="sr-only" for="from">From</label><input class="input" style="min-height:34px;padding:4px 8px;width:auto" type="date" id="from" name="from" value="<?= e($range->from) ?>" max="<?= e(date('Y-m-d')) ?>">
        <label class="sr-only" for="to">To</label><input class="input" style="min-height:34px;padding:4px 8px;width:auto" type="date" id="to" name="to" value="<?= e($range->to) ?>">
        <button class="btn btn-secondary btn-sm" type="submit">Apply</button>
    </form>
</div>
