<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryNavigation\helpers;

use humhub\modules\thiscoveryNavigation\Module;
use Yii;

class Navigation
{
    public static function isActive(): bool
    {
        $module = Yii::$app->getModule('thiscovery-navigation');
        return $module instanceof Module && $module->getIsEnabled();
    }
}
