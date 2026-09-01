<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryNavigation\controllers;

use humhub\modules\admin\components\Controller;
use humhub\modules\admin\permissions\ManageModules;
use humhub\modules\thiscoveryNavigation\assets\Assets;
use humhub\modules\thiscoveryNavigation\models\NavItem;
use humhub\modules\thiscoveryNavigation\services\CatalogService;
use humhub\modules\thiscoveryNavigation\services\ImportService;
use humhub\modules\thiscoveryNavigation\services\MobilePlacementService;
use humhub\modules\thiscoveryNavigation\services\NavigationService;
use Yii;
use yii\helpers\Url;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class AdminController extends Controller
{
    public $adminOnly = false;

    protected function getAccessRules()
    {
        return [
            ['login'],
        ];
    }

    public function beforeAction($action)
    {
        if (Yii::$app->user->isGuest || (!Yii::$app->user->isAdmin() && !Yii::$app->user->can(ManageModules::class))) {
            throw new ForbiddenHttpException();
        }
        return parent::beforeAction($action);
    }

    public function actionIndex()
    {
        Assets::register($this->view);
        $this->view->registerJs(
            'if (window.humhub && humhub.require) { try { humhub.require("thiscoveryNavigation"); } catch (e) {} }',
            \yii\web\View::POS_READY
        );
        (new ImportService())->importIfEmpty();
        (new MobilePlacementService())->migrateFromThemeIfNeeded();

        $themeStyle = 'hamburger';
        $theme = Yii::$app->getModule('thiscovery-theme');
        if ($theme) {
            $themeStyle = (string)$theme->settings->get('mobileMenuStyle', 'hamburger');
            if ($themeStyle === 'bottom-bar') {
                $themeStyle = 'floating-bar';
            }
        }

        return $this->render('index', [
            'tree' => (new NavigationService())->siteTreeForAdmin(),
            'unused' => (new CatalogService())->unused(),
            'themeStyle' => $themeStyle,
            'themeConfigUrl' => Url::to(['/thiscovery-theme/config/index']),
        ]);
    }

    public function actionSaveTree(): Response
    {
        $this->forcePostRequest();
        $nodes = Yii::$app->request->post('tree', []);
        if (is_string($nodes)) {
            $nodes = json_decode($nodes, true) ?: [];
        }
        if (!is_array($nodes)) {
            throw new BadRequestHttpException();
        }
        (new NavigationService())->saveTree($nodes);
        return $this->okTree();
    }

    public function actionCreate(): Response
    {
        $this->forcePostRequest();
        $type = (string)Yii::$app->request->post('type', NavItem::TYPE_GROUP);
        if (!in_array($type, [NavItem::TYPE_GROUP, NavItem::TYPE_URL], true)) {
            throw new BadRequestHttpException();
        }
        $item = new NavItem();
        $item->scope = NavItem::SCOPE_SITE;
        $item->parent_id = null;
        $item->depth = 1;
        $item->type = $type;
        $item->label = trim((string)Yii::$app->request->post('label', ''))
            ?: Yii::t('ThiscoveryNavigationModule.base', $type === NavItem::TYPE_GROUP ? 'Menu group' : 'Link');
        $item->icon = trim((string)Yii::$app->request->post('icon', ''));
        $item->url = $type === NavItem::TYPE_URL ? trim((string)Yii::$app->request->post('url', '')) : null;
        $item->visibility = (string)Yii::$app->request->post('visibility', NavItem::VIS_ALL);
        $item->mobile_placement = NavItem::PLACE_HAMBURGER;
        $item->new_window = ((string)Yii::$app->request->post('new_window', '0') === '1') ? 1 : 0;
        $item->enabled = 1;
        $item->sort_order = ((int)NavItem::find()->where(['scope' => NavItem::SCOPE_SITE, 'parent_id' => null])->max('sort_order')) + 10;
        if (!$item->save()) {
            return $this->asJson(['ok' => false, 'errors' => $item->getFirstErrors()]);
        }
        return $this->okTree(['item' => $this->serialize($item)]);
    }

    public function actionAddFromCatalog(): Response
    {
        $this->forcePostRequest();
        $key = trim((string)Yii::$app->request->post('source_key', ''));
        $catalog = (new CatalogService())->byKey($key);
        if (!$catalog) {
            throw new NotFoundHttpException();
        }
        $existing = NavItem::find()->where(['scope' => NavItem::SCOPE_SITE, 'source_key' => $key])->one();
        if ($existing) {
            return $this->okTree(['unused' => (new CatalogService())->unused()]);
        }
        $item = new NavItem();
        $item->scope = NavItem::SCOPE_SITE;
        $item->type = $catalog['type'];
        $item->source_key = $key;
        $item->label = $catalog['label'];
        $item->icon = $catalog['icon'];
        $item->visibility = NavItem::VIS_ALL;
        $item->mobile_placement = NavItem::PLACE_HAMBURGER;
        $item->enabled = 1;
        $item->depth = 1;
        $item->sort_order = ((int)NavItem::find()->where(['scope' => NavItem::SCOPE_SITE, 'parent_id' => null])->max('sort_order')) + 10;
        $item->save(false);
        return $this->okTree(['unused' => (new CatalogService())->unused()]);
    }

    public function actionUpdate(): Response
    {
        $this->forcePostRequest();
        $item = $this->findItem((int)Yii::$app->request->post('id'));
        $item->label = trim((string)Yii::$app->request->post('label', $item->label));
        $item->icon = trim((string)Yii::$app->request->post('icon', (string)$item->icon));
        $item->visibility = (string)Yii::$app->request->post('visibility', $item->visibility);
        if (!$item->parent_id) {
            $placement = (string)Yii::$app->request->post('mobile_placement', $item->mobile_placement);
            if (in_array($placement, [NavItem::PLACE_HAMBURGER, NavItem::PLACE_FLOATING, NavItem::PLACE_NONE], true)) {
                $item->mobile_placement = $placement;
            }
        }
        $item->new_window = ((string)Yii::$app->request->post('new_window', '0') === '1') ? 1 : 0;
        $item->enabled = ((string)Yii::$app->request->post('enabled', '1') === '1') ? 1 : 0;
        if ($item->type === NavItem::TYPE_URL) {
            $item->url = trim((string)Yii::$app->request->post('url', (string)$item->url));
        }
        if (!$item->save()) {
            return $this->asJson(['ok' => false, 'errors' => $item->getFirstErrors()]);
        }
        return $this->okTree();
    }

    public function actionDelete(): Response
    {
        $this->forcePostRequest();
        $item = $this->findItem((int)Yii::$app->request->post('id'));
        $item->delete();
        return $this->okTree(['unused' => (new CatalogService())->unused()]);
    }

    public function actionSavePlacement(): Response
    {
        $this->forcePostRequest();
        $item = $this->findItem((int)Yii::$app->request->post('id'));
        if ($item->parent_id) {
            throw new BadRequestHttpException();
        }
        $placement = (string)Yii::$app->request->post('mobile_placement', $item->mobile_placement);
        if (!in_array($placement, [NavItem::PLACE_HAMBURGER, NavItem::PLACE_FLOATING, NavItem::PLACE_NONE], true)) {
            throw new BadRequestHttpException();
        }
        $item->mobile_placement = $placement;
        if (!$item->save(false)) {
            return $this->asJson(['ok' => false, 'errors' => $item->getFirstErrors()]);
        }
        return $this->okTree();
    }

    private function okTree(array $extra = []): Response
    {
        return $this->asJson(array_merge([
            'ok' => true,
            'message' => Yii::t('base', 'Saved'),
            'tree' => (new NavigationService())->siteTreeForAdmin(),
        ], $extra));
    }

    private function findItem(int $id): NavItem
    {
        $item = NavItem::findOne(['id' => $id, 'scope' => NavItem::SCOPE_SITE]);
        if (!$item) {
            throw new NotFoundHttpException();
        }
        return $item;
    }

    private function serialize(NavItem $item): array
    {
        return [
            'id' => (int)$item->id,
            'type' => $item->type,
            'source_key' => (string)$item->source_key,
            'label' => $item->displayLabel(),
            'icon' => $item->displayIcon(),
            'url' => (string)$item->url,
            'visibility' => $item->visibility,
            'mobile_placement' => $item->mobilePlacement(),
            'new_window' => (bool)$item->new_window,
            'enabled' => (bool)$item->enabled,
            'children' => [],
        ];
    }
}
