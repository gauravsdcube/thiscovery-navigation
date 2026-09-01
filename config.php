<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

use humhub\modules\admin\widgets\AdminMenu;
use humhub\modules\thiscoveryNavigation\Events;
use humhub\modules\thiscoveryNavigation\Module;
use humhub\widgets\TopMenu;

return [
    'id' => 'thiscovery-navigation',
    'class' => Module::class,
    'namespace' => 'humhub\modules\thiscoveryNavigation',
    'events' => [
        ['class' => AdminMenu::class, 'event' => AdminMenu::EVENT_INIT, 'callback' => [Events::class, 'onAdminMenuInit']],
        ['class' => TopMenu::class, 'event' => TopMenu::EVENT_INIT, 'callback' => [Events::class, 'onTopMenuInit']],
    ],
];
