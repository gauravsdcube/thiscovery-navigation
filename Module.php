<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryNavigation;

use humhub\components\console\Application as ConsoleApplication;
use humhub\components\Module as BaseModule;
use humhub\modules\thiscoveryNavigation\services\ImportService;
use Yii;
use yii\helpers\Url;

class Module extends BaseModule
{
    public $resourcesPath = 'resources';
    public $icon = 'bars';

    public function init()
    {
        parent::init();
        Yii::setAlias('@thiscovery-navigation', $this->getBasePath());
        if (Yii::$app instanceof ConsoleApplication) {
            $this->controllerNamespace = 'humhub\modules\thiscoveryNavigation\commands';
        }
    }

    public function getName()
    {
        return Yii::t('ThiscoveryNavigationModule.base', 'Thiscovery Navigation');
    }

    public function getDescription()
    {
        return Yii::t(
            'ThiscoveryNavigationModule.base',
            'Arrange the site top bar as a three-level menu. Pages, forms, maps and custom links sit in one tree. On mobile, each top-level item can go in the hamburger, the floating bar, or be hidden.'
        );
    }

    public function getConfigUrl()
    {
        return Url::to(['/thiscovery-navigation/admin/index']);
    }

    public function enable()
    {
        $result = parent::enable();
        if ($result) {
            try {
                (new ImportService())->importIfEmpty();
                (new \humhub\modules\thiscoveryNavigation\services\MobilePlacementService())->migrateFromThemeIfNeeded();
            } catch (\Throwable $e) {
                Yii::error($e, 'thiscovery-navigation');
            }
        }
        return $result;
    }
}
