<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryNavigation\commands;

use humhub\modules\thiscoveryNavigation\models\NavItem;
use humhub\modules\thiscoveryNavigation\services\CatalogService;
use humhub\modules\thiscoveryNavigation\services\ImportService;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

class NavController extends Controller
{
    public function actionStatus()
    {
        $count = NavItem::siteCount();
        $this->stdout("site items: {$count}\n");
        foreach (NavItem::find()->where(['scope' => NavItem::SCOPE_SITE])->orderBy(['sort_order' => SORT_ASC])->all() as $item) {
            $this->stdout(sprintf(
                "#%d d%d %s %s vis=%s mobile=%s on=%d %s\n",
                $item->id,
                $item->depth,
                $item->type,
                $item->source_key ?: '-',
                $item->visibility,
                $item->mobile_placement ?? 'hamburger',
                $item->enabled,
                $item->displayLabel()
            ));
        }
        $this->stdout('unused: ' . count((new CatalogService())->unused()) . "\n");
        return ExitCode::OK;
    }

    /**
     * @param string $as guest|user|admin
     */
    public function actionPreview($as = 'guest')
    {
        if ($as !== 'guest') {
            $user = $this->findPreviewUser($as === 'admin');
            if (!$user) {
                $this->stderr("No {$as} user found\n");
                return ExitCode::UNSPECIFIED_ERROR;
            }
            Yii::$app->user->setIdentity($user);
            $this->stdout('# as ' . $user->username . " ({$as})\n");
        } else {
            $this->stdout("# as guest\n");
        }
        $tree = (new \humhub\modules\thiscoveryNavigation\services\NavigationService())->siteTreeForCurrentUser();
        $this->stdout(json_encode($this->summarizeTree($tree), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        return ExitCode::OK;
    }

    private function findPreviewUser(bool $admin): ?\humhub\modules\user\models\User
    {
        if ($admin) {
            $group = \humhub\modules\user\models\Group::getAdminGroup();
            if ($group) {
                $user = $group->getUsers()->andWhere(['user.status' => \humhub\modules\user\models\User::STATUS_ENABLED])->one();
                if ($user) {
                    return $user;
                }
            }
        }
        return \humhub\modules\user\models\User::find()
            ->andWhere(['status' => \humhub\modules\user\models\User::STATUS_ENABLED])
            ->one();
    }

    private function summarizeTree(array $tree): array
    {
        return array_map(function ($node) {
            return [
                'id' => $node['id'] ?? null,
                'menu_id' => $node['menu_id'] ?? null,
                'type' => $node['type'] ?? null,
                'label' => $node['label'] ?? null,
                'url' => $node['url'] ?? null,
                'children' => $this->summarizeTree($node['children'] ?? []),
            ];
        }, $tree);
    }

    public function actionImport()
    {
        $n = (new ImportService())->importIfEmpty();
        $this->stdout("imported {$n} items\n");
        return ExitCode::OK;
    }
}
