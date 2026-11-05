<?php /** One KPI card. Expects $label, $value, $glyph; optional $tone (green|blue|orange|red), $note, $valueTone. */
$tone = $tone ?? 'green';
$tile = ['green' => '', 'blue' => ' icon-tile--blue', 'orange' => ' icon-tile--orange', 'red' => ' icon-tile--red'][$tone];
?>
<div class="kpi">
    <div class="kpi-top"><span class="kpi-label"><?= e($label) ?></span><span class="icon-tile<?= $tile ?>"><?= icon($glyph) ?></span></div>
    <div class="kpi-value<?= !empty($valueTone) ? ' kpi-value--green' : '' ?>"><?= e($value) ?></div>
    <?php if (!empty($note)): ?><div class="kpi-note"><?= e($note) ?></div><?php endif; ?>
</div>
