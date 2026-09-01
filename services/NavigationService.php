<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryNavigation\services;

use humhub\modules\thiscoveryNavigation\models\NavItem;
use Yii;

class NavigationService
{
    /**
     * Nested tree for the current user (visible items only).
     *
     * @return array<int, array>
     */
    public function siteTreeForCurrentUser(): array
    {
        return $this->mapVisible(NavItem::siteRoots());
    }

    /**
     * Full nested tree for admin (all items).
     *
     * @return array<int, array>
     */
    public function siteTreeForAdmin(): array
    {
        return $this->mapAdmin(NavItem::siteRoots());
    }

    /**
     * @param NavItem[] $items
     * @return array<int, array>
     */
    private function mapVisible(array $items): array
    {
        $out = [];
        foreach ($items as $item) {
            if (!$item->isVisibleToCurrentUser()) {
                continue;
            }
            if (!$item->sourceAvailable()) {
                continue;
            }
            $url = $item->resolveUrl();
            $children = $this->mapVisible($item->getChildItems());
            if ($item->type !== NavItem::TYPE_GROUP && $url === null && !$children) {
                continue;
            }
            $node = $this->present($item, $url, $children);
            if ($item->type === NavItem::TYPE_GROUP && !$children) {
                continue;
            }
            $out[] = $node;
        }
        return $out;
    }

    /**
     * @param NavItem[] $items
     * @return array<int, array>
     */
    private function mapAdmin(array $items): array
    {
        $out = [];
        foreach ($items as $item) {
            $children = $this->mapAdmin($item->getChildItems());
            $out[] = [
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
                'sort_order' => (int)$item->sort_order,
                'depth' => (int)$item->depth,
                'children' => $children,
            ];
        }
        return $out;
    }

    private function present(NavItem $item, ?string $url, array $children): array
    {
        $active = $this->isActive($url, $children);
        $sourceKey = trim((string)$item->source_key);
        return [
            'id' => (int)$item->id,
            'type' => $item->type,
            'source_key' => $sourceKey,
            'menu_id' => $item->menuId(),
            'label' => $item->displayLabel(),
            'icon' => $item->displayIcon(),
            'url' => $url,
            'modal_url' => (new CatalogService())->modalUrlFor($item),
            'new_window' => (bool)$item->new_window,
            'active' => $active,
            'children' => $children,
        ];
    }

    private function isActive(?string $url, array $children): bool
    {
        foreach ($children as $child) {
            if (!empty($child['active'])) {
                return true;
            }
        }
        if ($url === null || $url === '' || $url === '#') {
            return false;
        }
        if (Yii::$app instanceof \yii\console\Application) {
            return false;
        }
        $current = Yii::$app->request->url;
        $path = '/' . ltrim(Yii::$app->request->pathInfo, '/');
        $itemPath = parse_url($url, PHP_URL_PATH) ?: $url;
        return $current === $url
            || rtrim($path, '/') === rtrim($itemPath, '/')
            || ($itemPath !== '/' && str_starts_with($path, rtrim($itemPath, '/') . '/'));
    }

    /**
     * Persist a nested id-tree from the admin UI.
     *
     * @param array<int, array{id:int,children?:array}> $nodes
     */
    public function saveTree(array $nodes, ?int $parentId = null, int $depth = 1): void
    {
        $sort = 10;
        foreach ($nodes as $node) {
            $id = (int)($node['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $item = NavItem::findOne(['id' => $id, 'scope' => NavItem::SCOPE_SITE]);
            if (!$item) {
                continue;
            }
            if ($depth > 3) {
                continue;
            }
            NavItem::updateAll(
                [
                    'parent_id' => $parentId,
                    'depth' => $depth,
                    'sort_order' => $sort,
                ],
                ['id' => $item->id, 'scope' => NavItem::SCOPE_SITE]
            );
            $sort += 10;
            $children = $node['children'] ?? [];
            if (is_array($children) && $children) {
                $this->saveTree($children, $item->id, $depth + 1);
            }
        }
    }
}
