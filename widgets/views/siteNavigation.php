<?php

/**
 * @var array<int, array> $items
 */
?>
<?php foreach ($items as $item): ?>
    <?= $this->render('_navItem', ['item' => $item, 'level' => 1]) ?>
<?php endforeach; ?>
