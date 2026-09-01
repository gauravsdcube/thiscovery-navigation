<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryNavigation\assets;

use yii\web\AssetBundle;

class Assets extends AssetBundle
{
    public $sourcePath = '@thiscovery-navigation/resources';

    public $js = [
        'js/humhub.thiscoveryNavigation.js',
    ];

    public $css = [
        'css/thiscovery-navigation.css',
    ];

    public $jsOptions = [
        'appendTimestamp' => true,
    ];

    public $cssOptions = [
        'appendTimestamp' => true,
    ];

    public $depends = [
        'humhub\assets\CoreApiAsset',
    ];

    public $publishOptions = [
        'forceCopy' => true,
    ];
}
