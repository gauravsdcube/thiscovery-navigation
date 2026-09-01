<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryNavigation\services;

use humhub\modules\thiscoveryNavigation\models\NavItem;
use Yii;

class ImportService
{
    public function importIfEmpty(): int
    {
        if (NavItem::siteCount() > 0) {
            return 0;
        }
        return $this->import();
    }

    public function import(): int
    {
        $created = 0;
        $sort = 0;

        $menuManager = $this->menuManagerEntries();
        foreach ($menuManager as $row) {
            $sort += 10;
            $this->upsert($row, $sort);
            $created++;
        }

        $catalog = new CatalogService();
        $placed = $this->placedKeys();
        foreach ($catalog->all() as $item) {
            if (isset($placed[$item['key']])) {
                continue;
            }
            if ($item['type'] === NavItem::TYPE_PAGE && empty($item['in_menu'])) {
                continue;
            }
            if (in_array($item['type'], [NavItem::TYPE_PAGE, NavItem::TYPE_FORM, NavItem::TYPE_MAP], true)
                || str_starts_with($item['key'], 'classified-space')) {
                $sort += 10;
                $this->upsert([
                    'type' => $item['type'],
                    'source_key' => $item['key'],
                    'label' => $item['label'],
                    'icon' => $item['icon'],
                    'visibility' => NavItem::VIS_ALL,
                    'enabled' => 1,
                ], $item['sort'] ?: $sort);
                $created++;
                $placed[$item['key']] = true;
            }
        }

        return $created;
    }

    /**
     * @return array<int, array>
     */
    private function menuManagerEntries(): array
    {
        $out = [];
        if (!Yii::$app->hasModule('menu-manager')) {
            return $this->defaultCoreEntries();
        }
        try {
            $module = Yii::$app->getModule('menu-manager');
            if (!method_exists($module, 'getConfiguration')) {
                return $this->defaultCoreEntries();
            }
            $configuration = $module->getConfiguration();
            $map = [
                'topMenuHome' => 'home',
                'topMenuDashboard' => 'dashboard',
                'topMenuPeople' => 'people',
                'topMenuSpaces' => 'spaces',
                'topMenuClassifiedSpaceBrowser' => 'classified-space-browser',
                'topMenuCalendar' => 'calendar',
            ];
            foreach ($map as $attr => $key) {
                if (!isset($configuration->$attr) && !method_exists($configuration, 'getMenuEntryConfig')) {
                    continue;
                }
                $entry = $configuration->getMenuEntryConfig($attr);
                if (!$entry) {
                    continue;
                }
                $state = (string)($entry->displayState ?? 'all');
                $visibility = NavItem::VIS_ALL;
                $enabled = 1;
                if ($state === 'none') {
                    $enabled = 0;
                    $visibility = NavItem::VIS_HIDDEN;
                } elseif ($state === 'non_guest') {
                    $visibility = NavItem::VIS_USERS;
                } elseif ($state === 'admin') {
                    $visibility = NavItem::VIS_ADMIN;
                }
                $out[] = [
                    'type' => NavItem::TYPE_MODULE,
                    'source_key' => $key,
                    'label' => (string)($entry->label ?: ''),
                    'icon' => (string)($entry->icon ?: ''),
                    'visibility' => $visibility,
                    'enabled' => $enabled,
                    'sort' => (int)($entry->sortOrder ?: 0),
                ];
            }
        } catch (\Throwable $e) {
            return $this->defaultCoreEntries();
        }
        return $out ?: $this->defaultCoreEntries();
    }

    private function defaultCoreEntries(): array
    {
        return [
            ['type' => NavItem::TYPE_MODULE, 'source_key' => 'dashboard', 'label' => '', 'icon' => 'dashboard', 'visibility' => NavItem::VIS_USERS, 'enabled' => 1, 'sort' => 100],
            ['type' => NavItem::TYPE_MODULE, 'source_key' => 'people', 'label' => '', 'icon' => 'users', 'visibility' => NavItem::VIS_USERS, 'enabled' => 1, 'sort' => 200],
            ['type' => NavItem::TYPE_MODULE, 'source_key' => 'spaces', 'label' => '', 'icon' => 'dot-circle-o', 'visibility' => NavItem::VIS_ALL, 'enabled' => 1, 'sort' => 250],
        ];
    }

    private function placedKeys(): array
    {
        $used = [];
        foreach (NavItem::find()->where(['scope' => NavItem::SCOPE_SITE])->andWhere(['not', ['source_key' => null]])->all() as $row) {
            $used[(string)$row->source_key] = true;
        }
        return $used;
    }

    private function upsert(array $row, int $sort): NavItem
    {
        $existing = NavItem::find()->where([
            'scope' => NavItem::SCOPE_SITE,
            'source_key' => $row['source_key'],
        ])->one();
        $item = $existing ?: new NavItem();
        $item->scope = NavItem::SCOPE_SITE;
        $item->parent_id = null;
        $item->type = $row['type'];
        $item->source_key = $row['source_key'];
        $item->label = $row['label'] ?? '';
        $item->icon = $row['icon'] ?? '';
        $item->visibility = $row['visibility'] ?? NavItem::VIS_ALL;
        $item->enabled = $row['enabled'] ?? 1;
        $item->mobile_placement = $row['mobile_placement'] ?? NavItem::PLACE_HAMBURGER;
        $item->sort_order = (int)($row['sort'] ?? $sort) ?: $sort;
        $item->depth = 1;
        $item->save(false);
        return $item;
    }
}
