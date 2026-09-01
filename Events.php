<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryNavigation;

use humhub\helpers\ControllerHelper;
use humhub\modules\admin\permissions\ManageModules;
use humhub\modules\admin\widgets\AdminMenu;
use humhub\modules\ui\menu\MenuLink;
use humhub\widgets\TopMenu;
use Yii;

class Events
{
    public static function onTopMenuInit($event): void
    {
        /** @var TopMenu $menu */
        $menu = $event->sender;
        $menu->addEntry(new MenuLink([
            'id' => 'thiscovery-navigation-slot',
            'label' => ' ',
            'url' => '#',
            'sortOrder' => 1,
            'isVisible' => false,
        ]));
    }

    public static function onAdminMenuInit($event): void
    {
        if (Yii::$app->user->isGuest) {
            return;
        }
        if (!Yii::$app->user->isAdmin() && !Yii::$app->user->can(ManageModules::class)) {
            return;
        }
        if (!Yii::$app->getModule('thiscovery-navigation')) {
            return;
        }

        /** @var AdminMenu $menu */
        $menu = $event->sender;
        $menu->addEntry(new MenuLink([
            'label' => Yii::t('ThiscoveryNavigationModule.base', 'Thiscovery Navigation'),
            'id' => 'thiscovery-navigation-admin',
            'icon' => 'bars',
            'url' => ['/thiscovery-navigation/admin/index'],
            'sortOrder' => 554,
            'isActive' => ControllerHelper::isActivePath('thiscovery-navigation', 'admin'),
            'isVisible' => true,
        ]));
    }
}
