<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryNavigation\services;

use humhub\modules\thiscoveryNavigation\models\NavItem;
use Yii;
use yii\helpers\Url;

class CatalogService
{
    /** @var array<string, array>|null */
    private ?array $index = null;

    /**
     * @return array<int, array{key:string,type:string,label:string,icon:string,url:?string,sort:int}>
     */
    public function all(): array
    {
        $items = array_merge(
            $this->coreModules(),
            $this->pages(),
            $this->forms(),
            $this->maps(),
            $this->classified()
        );
        usort($items, static fn($a, $b) => ($a['sort'] <=> $b['sort']) ?: strcasecmp($a['label'], $b['label']));
        return $items;
    }

    public function byKey(string $key): ?array
    {
        if ($key === '') {
            return null;
        }
        if ($this->index === null) {
            $this->index = [];
            foreach ($this->all() as $item) {
                $this->index[$item['key']] = $item;
            }
        }
        return $this->index[$key] ?? null;
    }

    public function urlFor(NavItem $item): ?string
    {
        $catalog = $this->byKey((string)$item->source_key);
        return $catalog['url'] ?? null;
    }

    /**
     * Modal URL for destinations that open in a HumHub modal (classified-space browser).
     */
    public function modalUrlFor(NavItem $item): ?string
    {
        $key = (string)$item->source_key;
        if ($key === '' || !Yii::$app->hasModule('classified-space')) {
            return null;
        }
        $module = Yii::$app->getModule('classified-space');
        $configuration = $module->configuration ?? null;
        if (!$configuration || empty($configuration->openClassifiedSpaceBrowserInModal)) {
            return null;
        }
        if ($key === 'classified-space-browser') {
            return Url::to(['/classified-space/browse/index', 'modal' => 1]);
        }
        if (preg_match('/^classified-space-browser-category-(\d+)$/', $key, $m)
            && class_exists(\humhub\modules\classifiedSpace\models\ClassifiedSpaceCategory::class)) {
            $category = \humhub\modules\classifiedSpace\models\ClassifiedSpaceCategory::findOne((int)$m[1]);
            return $category && method_exists($category, 'getBrowseUrl') ? $category->getBrowseUrl(true) : null;
        }
        return null;
    }

    /**
     * Catalog destinations not yet placed in the site tree.
     * @return array<int, array>
     */
    public function unused(): array
    {
        $used = [];
        foreach (NavItem::find()->where(['scope' => NavItem::SCOPE_SITE])->andWhere(['not', ['source_key' => null]])->all() as $row) {
            $used[(string)$row->source_key] = true;
        }
        return array_values(array_filter($this->all(), static fn($item) => empty($used[$item['key']])));
    }

    private function coreModules(): array
    {
        $items = [
            [
                'key' => 'home',
                'type' => NavItem::TYPE_MODULE,
                'label' => Yii::t('ThiscoveryNavigationModule.base', 'Home'),
                'icon' => 'home',
                'url' => Url::home(),
                'sort' => 50,
            ],
            [
                'key' => 'dashboard',
                'type' => NavItem::TYPE_MODULE,
                'label' => Yii::t('DashboardModule.base', 'Dashboard'),
                'icon' => 'dashboard',
                'url' => Url::to(['/dashboard/dashboard']),
                'sort' => 100,
            ],
            [
                'key' => 'people',
                'type' => NavItem::TYPE_MODULE,
                'label' => Yii::t('UserModule.base', 'People'),
                'icon' => 'users',
                'url' => Url::to(['/user/people']),
                'sort' => 200,
            ],
            [
                'key' => 'spaces',
                'type' => NavItem::TYPE_MODULE,
                'label' => Yii::t('SpaceModule.base', 'Spaces'),
                'icon' => 'dot-circle-o',
                'url' => Url::to(['/space/spaces']),
                'sort' => 250,
            ],
        ];

        if (Yii::$app->hasModule('calendar') && class_exists(\humhub\modules\calendar\helpers\Url::class)) {
            $items[] = [
                'key' => 'calendar',
                'type' => NavItem::TYPE_MODULE,
                'label' => Yii::t('ThiscoveryNavigationModule.base', 'Calendar'),
                'icon' => 'calendar',
                'url' => \humhub\modules\calendar\helpers\Url::toGlobalCalendar(),
                'sort' => 300,
            ];
        }

        return $items;
    }

    private function pages(): array
    {
        if (!Yii::$app->hasModule('thiscovery-page-builder')
            || !class_exists(\humhub\modules\thiscoveryPageBuilder\models\EngagementPage::class)) {
            return [];
        }
        $class = \humhub\modules\thiscoveryPageBuilder\models\EngagementPage::class;
        try {
            if (!(new $class())->hasAttribute('show_in_top_menu')) {
                return [];
            }
        } catch (\Throwable $e) {
            return [];
        }

        $out = [];
        $pages = $class::find()
            ->where([
                'status' => $class::STATUS_PUBLISHED,
                'is_template' => 0,
            ])
            ->all();
        foreach ($pages as $page) {
            if (method_exists($page, 'canAccessPublic') && !$page->canAccessPublic()) {
                continue;
            }
            $containerId = $page->content->contentcontainer_id ?? null;
            if ($containerId) {
                continue;
            }
            $label = trim((string)($page->top_menu_label ?: $page->title));
            $out[] = [
                'key' => 'thiscovery-page-' . $page->id,
                'type' => NavItem::TYPE_PAGE,
                'label' => $label !== '' ? $label : (string)$page->title,
                'icon' => 'file-text-o',
                'url' => \humhub\modules\thiscoveryPageBuilder\helpers\Url::toPublic($page),
                'sort' => (int)($page->top_menu_sort_order ?: 400),
                'in_menu' => !empty($page->show_in_top_menu),
                'page' => $page,
            ];
        }
        return $out;
    }

    private function forms(): array
    {
        if (!Yii::$app->hasModule('thiscovery-forms')
            || !class_exists(\humhub\modules\thiscoveryForms\models\CustomForm::class)) {
            return [];
        }
        $out = [];
        try {
            foreach (\humhub\modules\thiscoveryForms\models\CustomForm::findShownInMenu(null) as $form) {
                if (!$form->content->canView()) {
                    continue;
                }
                $out[] = [
                    'key' => 'thiscovery-form-global-' . $form->id,
                    'type' => NavItem::TYPE_FORM,
                    'label' => (string)$form->title,
                    'icon' => 'wpforms',
                    'url' => \humhub\modules\thiscoveryForms\helpers\Url::toView($form),
                    'sort' => 400,
                ];
            }
        } catch (\Throwable $e) {
            return [];
        }
        return $out;
    }

    private function maps(): array
    {
        if (!Yii::$app->hasModule('thiscovery-mapping')
            || !class_exists(\humhub\modules\thiscoveryMapping\models\Map::class)) {
            return [];
        }
        try {
            $count = \humhub\modules\thiscoveryMapping\models\Map::find()
                ->joinWith('content')
                ->andWhere(['content.contentcontainer_id' => null])
                ->count();
            if ((int)$count === 0) {
                return [];
            }
        } catch (\Throwable $e) {
            return [];
        }
        return [[
            'key' => 'thiscovery-mapping-top',
            'type' => NavItem::TYPE_MAP,
            'label' => Yii::t('ThiscoveryMappingModule.base', 'Maps'),
            'icon' => 'map-marker',
            'url' => \humhub\modules\thiscoveryMapping\helpers\Url::toIndex(null),
            'sort' => 410,
        ]];
    }

    private function classified(): array
    {
        if (!Yii::$app->hasModule('classified-space')) {
            return [];
        }
        $out = [];
        try {
            $module = Yii::$app->getModule('classified-space');
            $configuration = $module->configuration ?? null;
            if ($configuration && !empty($configuration->showClassifiedSpaceBrowser)) {
                $url = ['/classified-space/browse/index'];
                $out[] = [
                    'key' => 'classified-space-browser',
                    'type' => NavItem::TYPE_MODULE,
                    'label' => Yii::t('ClassifiedSpaceModule.base', 'Spaces'),
                    'icon' => $configuration->classifiedSpaceBrowserIcon ?: 'th-large',
                    'url' => Url::to($url),
                    'sort' => (int)($configuration->classifiedSpaceBrowserSortOrder ?: 250),
                ];
            }
            if (class_exists(\humhub\modules\classifiedSpace\models\ClassifiedSpaceCategory::class)) {
                foreach (\humhub\modules\classifiedSpace\models\ClassifiedSpaceCategory::findAll(['top_menu_entry' => true]) as $category) {
                    $out[] = [
                        'key' => 'classified-space-browser-category-' . $category->id,
                        'type' => NavItem::TYPE_MODULE,
                        'label' => (string)$category->name,
                        'icon' => (string)($category->top_menu_icon ?: 'th-large'),
                        'url' => method_exists($category, 'getBrowseUrl') ? (string)$category->getBrowseUrl() : Url::to(['/classified-space/browse/index', 'id' => $category->id]),
                        'sort' => (int)($category->top_menu_sort_order ?: 260),
                    ];
                }
            }
        } catch (\Throwable $e) {
            return $out;
        }
        return $out;
    }
}
