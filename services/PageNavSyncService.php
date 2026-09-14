<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryNavigation\services;

use humhub\modules\thiscoveryNavigation\models\NavItem;
use Yii;

/**
 * Keep collection pages (and their children) in the site navigation tree.
 */
class PageNavSyncService
{
    public static function catalogKey(int $pageId): string
    {
        return 'thiscovery-page-' . $pageId;
    }

    public function ensureAll(): void
    {
        if (!Yii::$app->hasModule('thiscovery-page-builder')
            || !class_exists(\humhub\modules\thiscoveryPageBuilder\models\EngagementPage::class)) {
            return;
        }
        CatalogService::flush();
        $class = \humhub\modules\thiscoveryPageBuilder\models\EngagementPage::class;
        $pages = $class::find()
            ->where(['is_template' => 0])
            ->joinWith('content')
            ->andWhere(['content.contentcontainer_id' => null])
            ->with(['content', 'parent'])
            ->orderBy(['id' => SORT_ASC])
            ->all();
        foreach ($pages as $page) {
            $this->syncPage($page, [], false, false);
        }
    }

    /**
     * @param \humhub\modules\thiscoveryPageBuilder\models\EngagementPage $page
     * @param array $changedAttributes
     */
    public function syncPage($page, array $changedAttributes = [], bool $flushCatalog = true, bool $updateExisting = true): ?NavItem
    {
        if ($flushCatalog) {
            CatalogService::flush();
        }
        if (!is_object($page) || !method_exists($page, 'isTemplate')) {
            return null;
        }
        if ($page->isTemplate() || (method_exists($page, 'isGlobal') && !$page->isGlobal())) {
            $this->removePage((int) $page->id);
            return null;
        }
        if (!(int) $page->id) {
            return null;
        }

        $isCollection = method_exists($page, 'isCollection') && $page->isCollection();
        $parentId = (int) ($page->parent_id ?? 0);
        $isChild = !$isCollection && $parentId > 0;

        if (!$isCollection && !$isChild) {
            $existing = $this->findItem((int) $page->id);
            if (!$existing && empty($page->show_in_top_menu)) {
                return null;
            }
            return $this->upsert($page, null, $changedAttributes, $updateExisting);
        }

        $parentNavId = null;
        if ($isChild) {
            $parent = $page->parent ?? null;
            if (!$parent && $parentId) {
                $class = get_class($page);
                $parent = $class::findOne($parentId);
            }
            if ($parent && method_exists($parent, 'isCollection') && $parent->isCollection()) {
                $parentItem = $this->upsert($parent, null, [], $updateExisting);
                $parentNavId = $parentItem ? (int) $parentItem->id : null;
            }
        }

        return $this->upsert($page, $parentNavId, $changedAttributes, $updateExisting);
    }

    public function removePage(int $pageId): void
    {
        if ($pageId < 1) {
            return;
        }
        CatalogService::flush();
        $item = $this->findItem($pageId);
        if ($item) {
            $item->delete();
        }
    }

    /**
     * @param \humhub\modules\thiscoveryPageBuilder\models\EngagementPage $page
     */
    private function upsert($page, ?int $parentNavId, array $changedAttributes, bool $updateExisting = true): ?NavItem
    {
        $key = self::catalogKey((int) $page->id);
        $item = NavItem::find()->where([
            'scope' => NavItem::SCOPE_SITE,
            'source_key' => $key,
        ])->one();
        $isNew = $item === null;
        if (!$isNew && !$updateExisting) {
            return $item;
        }
        if ($isNew) {
            $item = new NavItem();
            $item->scope = NavItem::SCOPE_SITE;
            $item->type = NavItem::TYPE_PAGE;
            $item->source_key = $key;
            $item->icon = $page->isCollection() ? 'folder-o' : 'file-text-o';
            $item->show_icon = 1;
            $item->mobile_placement = NavItem::PLACE_HAMBURGER;
            $item->sort_order = $this->nextSort($parentNavId, (int) ($page->top_menu_sort_order ?: 0));
        }

        $forceParent = $isNew || array_key_exists('parent_id', $changedAttributes);
        if ($forceParent) {
            $item->parent_id = $parentNavId ?: null;
        }

        $label = trim((string) ($page->top_menu_label ?: $page->title));
        $item->label = $label;
        $visibility = (string) ($page->top_menu_visibility ?? NavItem::VIS_ALL);
        if (!in_array($visibility, [NavItem::VIS_ALL, NavItem::VIS_GUESTS, NavItem::VIS_USERS], true)) {
            $visibility = NavItem::VIS_ALL;
        }
        $item->visibility = $visibility;
        $item->enabled = !empty($page->show_in_top_menu) && method_exists($page, 'isPublished') && $page->isPublished() ? 1 : 0;
        if ($isNew || array_key_exists('top_menu_sort_order', $changedAttributes)) {
            $sort = (int) ($page->top_menu_sort_order ?: 0);
            if ($sort > 0) {
                $item->sort_order = $sort;
            }
        }

        if (!$item->save()) {
            Yii::warning(
                'Page nav sync failed for ' . $key . ': ' . json_encode($item->getErrors()),
                'thiscovery-navigation'
            );
            return null;
        }
        return $item;
    }

    private function findItem(int $pageId): ?NavItem
    {
        return NavItem::find()->where([
            'scope' => NavItem::SCOPE_SITE,
            'source_key' => self::catalogKey($pageId),
        ])->one();
    }

    private function nextSort(?int $parentNavId, int $preferred): int
    {
        if ($preferred > 0) {
            return $preferred;
        }
        $max = (int) NavItem::find()
            ->where(['scope' => NavItem::SCOPE_SITE, 'parent_id' => $parentNavId])
            ->max('sort_order');
        return $max + 10;
    }
}
