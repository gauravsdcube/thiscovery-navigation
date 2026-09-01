<?php

use yii\helpers\Html;

/** @var string|null $text */
/** @var string|null $html */

$text = trim((string) ($text ?? ''));
$html = $html ?? null;
if ($html === null && $text === '') {
    return;
}
$id = 'tn-guide-' . str_replace('.', '', uniqid('', true));
$label = Yii::t('ThiscoveryNavigationModule.base', 'Guidance');
?>
<button type="button" class="tn-guide__toggle" data-tn-guide-toggle
        aria-expanded="false" aria-controls="<?= Html::encode($id) ?>"
        title="<?= Html::encode($label) ?>" aria-label="<?= Html::encode($label) ?>">
    <i class="fa fa-question-circle" aria-hidden="true"></i>
</button>
<div id="<?= Html::encode($id) ?>" class="tn-guide__panel" hidden>
    <?php if ($html !== null): ?>
        <?= $html ?>
    <?php else: ?>
        <p><?= Html::encode($text) ?></p>
    <?php endif; ?>
</div>
