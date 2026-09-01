<?php

use humhub\helpers\Html;
use humhub\modules\thiscoveryNavigation\models\NavItem;
use yii\helpers\Json;
use yii\helpers\Url;

/**
 * @var array $tree
 * @var array $unused
 * @var string $themeStyle
 * @var string $themeConfigUrl
 */

$visibilityOptions = [
    NavItem::VIS_ALL => Yii::t('ThiscoveryNavigationModule.base', 'Everyone'),
    NavItem::VIS_GUESTS => Yii::t('ThiscoveryNavigationModule.base', 'Guests only'),
    NavItem::VIS_USERS => Yii::t('ThiscoveryNavigationModule.base', 'Logged-in users'),
    NavItem::VIS_ADMIN => Yii::t('ThiscoveryNavigationModule.base', 'Administrators only'),
    NavItem::VIS_HIDDEN => Yii::t('ThiscoveryNavigationModule.base', 'Hidden'),
];
$themeStyle = $themeStyle ?? 'hamburger';
$themeConfigUrl = $themeConfigUrl ?? Url::to(['/thiscovery-theme/config/index']);
$placementOptions = [
    NavItem::PLACE_HAMBURGER => Yii::t('ThiscoveryNavigationModule.base', 'Hamburger menu'),
    NavItem::PLACE_FLOATING => Yii::t('ThiscoveryNavigationModule.base', 'Floating bottom bar'),
    NavItem::PLACE_NONE => Yii::t('ThiscoveryNavigationModule.base', 'Hide on mobile'),
];
$view = $this;
$guide = static function (string $text) use ($view) {
    return $view->render('_setting_guide', ['text' => $text]);
};
$mobileIntro = $themeStyle === 'floating-bar'
    ? Yii::t('ThiscoveryNavigationModule.base', 'This site currently uses a floating bottom bar. Items set to Hamburger menu appear in the slide-down panel. Nested items follow their top-level parent.')
    : Yii::t('ThiscoveryNavigationModule.base', 'This site currently uses the hamburger menu. Items set to Floating bottom bar still appear in the hamburger until you switch the theme to floating-bar style. Nested items follow their top-level parent.');
?>
<div class="panel panel-default tn-admin" data-tn-admin
     data-save-url="<?= Html::encode(Url::to(['/thiscovery-navigation/admin/save-tree'])) ?>"
     data-create-url="<?= Html::encode(Url::to(['/thiscovery-navigation/admin/create'])) ?>"
     data-update-url="<?= Html::encode(Url::to(['/thiscovery-navigation/admin/update'])) ?>"
     data-delete-url="<?= Html::encode(Url::to(['/thiscovery-navigation/admin/delete'])) ?>"
     data-add-url="<?= Html::encode(Url::to(['/thiscovery-navigation/admin/add-from-catalog'])) ?>"
     data-placement-url="<?= Html::encode(Url::to(['/thiscovery-navigation/admin/save-placement'])) ?>"
     data-tree="<?= Html::encode(Json::encode($tree)) ?>"
     data-unused="<?= Html::encode(Json::encode($unused)) ?>"
     data-placement-options="<?= Html::encode(Json::encode($placementOptions)) ?>">
    <div class="panel-heading">
        <?= Yii::t('ThiscoveryNavigationModule.base', '<strong>Site</strong> navigation') ?>
    </div>
    <div class="panel-body">
        <p class="tn-studio__hint">
            <?= Yii::t('ThiscoveryNavigationModule.base', 'Settings are grouped into collapsible sections. Menu tree opens first; use Expand all / Collapse all as needed. Click ? next to a label for a short explanation.') ?>
        </p>
        <div class="tn-set-toolbar">
            <button type="button" class="btn btn-sm btn-light" data-tn-acc-all="open"><?= Yii::t('ThiscoveryNavigationModule.base', 'Expand all') ?></button>
            <button type="button" class="btn btn-sm btn-light" data-tn-acc-all="close"><?= Yii::t('ThiscoveryNavigationModule.base', 'Collapse all') ?></button>
        </div>

        <details class="tn-set-acc" open>
            <summary>
                <span class="tn-set-acc__title"><?= Yii::t('ThiscoveryNavigationModule.base', 'Menu tree') ?></span>
                <span class="tn-set-acc__summary"><?= Yii::t('ThiscoveryNavigationModule.base', 'Arrange items, nest up to three levels') ?></span>
            </summary>
            <div class="tn-set-acc__body">
                <p class="tn-set-acc__intro">
                    <?= Yii::t('ThiscoveryNavigationModule.base', 'This tree is what appears in the site top bar. Unused destinations stay in the tray until you add them.') ?>
                </p>
                <div class="tn-admin__toolbar">
                    <button type="button" class="btn btn-primary btn-sm" data-tn-add="group">
                        <?= Yii::t('ThiscoveryNavigationModule.base', 'Add group') ?>
                    </button>
                    <button type="button" class="btn btn-outline-primary btn-sm" data-tn-add="url">
                        <?= Yii::t('ThiscoveryNavigationModule.base', 'Add custom URL') ?>
                    </button>
                    <button type="button" class="btn btn-accent btn-sm" data-tn-save>
                        <?= Yii::t('ThiscoveryNavigationModule.base', 'Save order') ?>
                    </button>
                </div>
                <div class="tn-admin__layout">
                    <div class="tn-admin__tree-col">
                        <div class="tn-field">
                            <span class="tn-label"><?= Yii::t('ThiscoveryNavigationModule.base', 'Menu tree') ?></span>
                            <?= $guide(Yii::t('ThiscoveryNavigationModule.base', 'Drag onto the middle of an item to nest it. Drop on the top or bottom of a top-level item, or onto the empty space under the tree, to move it back to the top bar. Then click Save order.')) ?>
                        </div>
                        <ol class="tn-tree" data-tn-tree></ol>
                    </div>
                    <div class="tn-admin__unused-col">
                        <div class="tn-field">
                            <span class="tn-label"><?= Yii::t('ThiscoveryNavigationModule.base', 'Unused items') ?></span>
                            <?= $guide(Yii::t('ThiscoveryNavigationModule.base', 'Pages, forms, maps and core links that are not in the tree yet. Add one to place it in the top bar.')) ?>
                        </div>
                        <ul class="tn-unused" data-tn-unused></ul>
                    </div>
                    <div class="tn-admin__edit-col" data-tn-editor hidden>
                        <h4><?= Yii::t('ThiscoveryNavigationModule.base', 'Edit item') ?></h4>
                        <form data-tn-edit-form>
                            <input type="hidden" name="id" value="">
                            <div class="tn-field">
                                <label class="tn-label"><?= Yii::t('ThiscoveryNavigationModule.base', 'Label') ?></label>
                                <?= $guide(Yii::t('ThiscoveryNavigationModule.base', 'Text shown in the top bar and hamburger. Keep it short.')) ?>
                                <input type="text" class="form-control" name="label" maxlength="128">
                            </div>
                            <div class="tn-field">
                                <label class="tn-label"><?= Yii::t('ThiscoveryNavigationModule.base', 'Icon') ?></label>
                                <?= $guide(Yii::t('ThiscoveryNavigationModule.base', 'Optional Font Awesome icon name, for example home or file-text-o. Leave empty to use the source default. Untick Show icon to hide it in the menu.')) ?>
                                <input type="text" class="form-control" name="icon" maxlength="64" placeholder="home">
                            </div>
                            <div class="tn-check-setting">
                                <label class="form-check-label">
                                    <input type="checkbox" class="form-check-input" name="show_icon" value="1" checked>
                                    <?= Yii::t('ThiscoveryNavigationModule.base', 'Show icon') ?>
                                </label>
                                <?= $guide(Yii::t('ThiscoveryNavigationModule.base', 'When off, the label is shown without an icon, even if an icon name is set or the source has a default.')) ?>
                            </div>
                            <div class="tn-field" data-tn-url-field>
                                <label class="tn-label"><?= Yii::t('ThiscoveryNavigationModule.base', 'URL') ?></label>
                                <?= $guide(Yii::t('ThiscoveryNavigationModule.base', 'Used for custom links. Start with / for a path on this site, or https:// for an external page.')) ?>
                                <input type="text" class="form-control" name="url" maxlength="512">
                            </div>
                            <div class="tn-field">
                                <label class="tn-label"><?= Yii::t('ThiscoveryNavigationModule.base', 'Visibility') ?></label>
                                <?= $guide(Yii::t('ThiscoveryNavigationModule.base', 'Who can see this item. Hidden keeps it in the tree without showing it on the site.')) ?>
                                <select class="form-control" name="visibility">
                                    <?php foreach ($visibilityOptions as $value => $label): ?>
                                        <option value="<?= Html::encode($value) ?>"><?= Html::encode($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="tn-field" data-tn-placement-field>
                                <label class="tn-label"><?= Yii::t('ThiscoveryNavigationModule.base', 'On mobile') ?></label>
                                <?= $guide(Yii::t('ThiscoveryNavigationModule.base', 'Nested items follow this top-level item. The theme still chooses hamburger or floating-bar style and colours.')) ?>
                                <select class="form-control" name="mobile_placement">
                                    <?php foreach ($placementOptions as $value => $label): ?>
                                        <option value="<?= Html::encode($value) ?>"><?= Html::encode($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="tn-check-setting">
                                <label class="form-check-label">
                                    <input type="checkbox" class="form-check-input" name="new_window" value="1">
                                    <?= Yii::t('ThiscoveryNavigationModule.base', 'Open in a new tab') ?>
                                </label>
                                <?= $guide(Yii::t('ThiscoveryNavigationModule.base', 'Opens this link in a new browser tab. Use for external sites.')) ?>
                            </div>
                            <div class="tn-check-setting">
                                <label class="form-check-label">
                                    <input type="checkbox" class="form-check-input" name="enabled" value="1" checked>
                                    <?= Yii::t('ThiscoveryNavigationModule.base', 'Enabled') ?>
                                </label>
                                <?= $guide(Yii::t('ThiscoveryNavigationModule.base', 'Turn off to keep the item in the tree without showing it, including nested children.')) ?>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm"><?= Yii::t('ThiscoveryNavigationModule.base', 'Save item') ?></button>
                            <button type="button" class="btn btn-outline-primary btn-sm" data-tn-promote hidden>
                                <?= Yii::t('ThiscoveryNavigationModule.base', 'Move to top level') ?>
                            </button>
                            <button type="button" class="btn btn-danger btn-sm" data-tn-delete><?= Yii::t('ThiscoveryNavigationModule.base', 'Remove from menu') ?></button>
                        </form>
                    </div>
                </div>
            </div>
        </details>

        <details class="tn-set-acc" data-tn-mobile>
            <summary>
                <span class="tn-set-acc__title"><?= Yii::t('ThiscoveryNavigationModule.base', 'Mobile') ?></span>
                <span class="tn-set-acc__summary"><?= Yii::t('ThiscoveryNavigationModule.base', 'Hamburger, floating bar, or hide on phones') ?></span>
            </summary>
            <div class="tn-set-acc__body">
                <p class="tn-set-acc__intro">
                    <?= Html::encode($mobileIntro) ?>
                    <a href="<?= Html::encode($themeConfigUrl) ?>">
                        <?= Yii::t('ThiscoveryNavigationModule.base', 'Change hamburger or floating-bar style in the theme.') ?>
                    </a>
                </p>
                <div class="tn-field">
                    <span class="tn-label"><?= Yii::t('ThiscoveryNavigationModule.base', 'On mobile') ?></span>
                    <?= $guide(Yii::t('ThiscoveryNavigationModule.base', 'Each top-level item can go in the hamburger panel, the floating bottom bar, or be hidden on phones. Nested items stay with their parent. Changes save as soon as you pick an option.')) ?>
                </div>
                <ul class="tn-mobile-list" data-tn-mobile-list></ul>
            </div>
        </details>
    </div>
</div>
