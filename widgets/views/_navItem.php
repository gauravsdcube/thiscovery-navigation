<?php

use humhub\helpers\Html;
use humhub\modules\ui\icon\widgets\Icon;

/**
 * @var array $item
 * @var int $level
 */

$children = $item['children'] ?? [];
$hasChildren = $children !== [];
$url = $item['url'] ?: '#';
$opensModal = !empty($item['modal_url']);
// A page/link with children is a dropdown. Keep the original URL as the first item.
if ($hasChildren && $url !== '#' && ($item['type'] ?? '') !== 'group') {
    array_unshift($children, [
        'id' => ($item['id'] ?? 0) . '-self',
        'menu_id' => (($item['menu_id'] ?? ('tn-' . ($item['id'] ?? 0))) . '-self'),
        'type' => $item['type'] ?? 'url',
        'label' => $item['label'],
        'icon' => $item['icon'] ?? '',
        'url' => $url,
        'modal_url' => $item['modal_url'] ?? null,
        'new_window' => !empty($item['new_window']),
        'active' => !empty($item['active']),
        'children' => [],
    ]);
    $url = '#';
    $opensModal = false;
}
$isGroup = (($item['type'] ?? '') === 'group') || ($url === '#' && !$opensModal);
$classes = ['nav-item', 'tn-item'];
if ($level === 1) {
    $classes[] = 'top-menu-item';
}
if ($hasChildren) {
    $classes[] = 'tn-has-children';
}
if (!empty($item['active'])) {
    $classes[] = 'active';
}
$linkClass = $level === 1 ? 'nav-link tn-link' : 'tn-link';
if (!empty($item['active'])) {
    $linkClass .= ' active';
}
$menuId = (string)($item['menu_id'] ?? ('tn-' . (int)$item['id']));
$submenuId = 'tn-sub-' . (int)$item['id'];
$options = [
    'class' => $linkClass,
    'data-menu-id' => $menuId,
];
if (!empty($item['new_window']) && !$isGroup) {
    $options['target'] = '_blank';
    $options['rel'] = 'noopener noreferrer';
}
if ($opensModal) {
    $options['data-action-click'] = 'ui.modal.load';
    $options['data-action-click-url'] = $item['modal_url'];
    $url = '#';
}
if ($hasChildren) {
    $options['aria-expanded'] = 'false';
    $options['aria-haspopup'] = 'true';
    $options['aria-controls'] = $submenuId;
}
if ($isGroup || $opensModal) {
    $options['href'] = '#';
}
if ($isGroup) {
    $options['role'] = 'button';
}
$label = Html::encode($item['label']);
$iconHtml = $item['icon'] !== '' ? Icon::get($item['icon']) . ' ' : '';
$caret = $hasChildren ? '<span class="tn-caret" aria-hidden="true"></span>' : '';
?>
<li class="<?= Html::encode(implode(' ', $classes)) ?>" data-menu-id="<?= Html::encode($menuId) ?>">
    <?= Html::a($iconHtml . '<span class="tn-label">' . $label . '</span>' . $caret, ($isGroup || $opensModal) ? '#' : $url, $options) ?>
    <?php if ($hasChildren): ?>
        <ul class="tn-submenu" id="<?= Html::encode($submenuId) ?>" role="list">
            <?php foreach ($children as $child): ?>
                <?= $this->render('_navItem', ['item' => $child, 'level' => $level + 1]) ?>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</li>
