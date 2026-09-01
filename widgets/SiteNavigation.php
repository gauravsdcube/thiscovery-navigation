<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryNavigation\widgets;

use humhub\components\Widget;
use humhub\modules\thiscoveryNavigation\assets\Assets;
use humhub\modules\thiscoveryNavigation\services\NavigationService;
use Yii;

class SiteNavigation extends Widget
{
    public function run()
    {
        Assets::register($this->view);
        return $this->render('siteNavigation', [
            'items' => (new NavigationService())->siteTreeForCurrentUser(),
        ]);
    }
}
