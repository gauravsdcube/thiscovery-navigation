<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryNavigation\services;

use humhub\modules\thiscoveryNavigation\models\NavItem;
use Yii;

/**
 * Maps top-level nav items onto the theme's hamburger / floating-bar ID lists.
 */
class MobilePlacementService
{
    private const SETTING_MIGRATED = 'mobile_placement_migrated';

    /**
     * @return array{
     *     hamburger: array<int, string>,
     *     floating: array<int, string>,
     *     hamburgerSort: array<string, int>,
     *     floatingSort: array<string, int>
     * }
     */
    public function themeMenuIds(): array
    {
        $this->migrateFromThemeIfNeeded();

        $hamburger = [];
        $floating = [];
        $hamburgerSort = [];
        $floatingSort = [];

        $schema = Yii::$app->db->schema->getTableSchema(NavItem::tableName());
        $hasPlacement = $schema && $schema->getColumn('mobile_placement') !== null;

        $style = 'hamburger';
        $theme = Yii::$app->getModule('thiscovery-theme');
        if ($theme) {
            $style = (string)$theme->settings->get('mobileMenuStyle', 'hamburger');
            if ($style === 'bottom-bar') {
                $style = 'floating-bar';
            }
        }

        foreach (NavItem::siteRoots() as $item) {
            if (!$item->enabled) {
                continue;
            }
            $id = $item->menuId();
            $sort = (int)$item->sort_order;
            $placement = $hasPlacement ? $item->mobilePlacement() : NavItem::PLACE_HAMBURGER;
            if ($placement === NavItem::PLACE_NONE) {
                continue;
            }
            // Hamburger style has no bottom bar: floating items stay in the panel.
            if ($style === 'floating-bar' && $placement === NavItem::PLACE_FLOATING) {
                $floating[] = $id;
                $floatingSort[$id] = $sort;
                continue;
            }
            $hamburger[] = $id;
            $hamburgerSort[$id] = $sort;
        }

        return [
            'hamburger' => $hamburger,
            'floating' => $floating,
            'hamburgerSort' => $hamburgerSort,
            'floatingSort' => $floatingSort,
        ];
    }

    public function migrateFromThemeIfNeeded(): void
    {
        $module = Yii::$app->getModule('thiscovery-navigation');
        if (!$module) {
            return;
        }
        $schema = Yii::$app->db->schema->getTableSchema(NavItem::tableName(), true);
        if ($schema === null || $schema->getColumn('mobile_placement') === null) {
            return;
        }
        if ($module->settings->get(self::SETTING_MIGRATED)) {
            return;
        }
        try {
            $this->migrateFromTheme();
            $module->settings->set(self::SETTING_MIGRATED, 1);
        } catch (\Throwable $e) {
            Yii::error($e, 'thiscovery-navigation');
        }
    }

    private function migrateFromTheme(): void
    {
        $theme = Yii::$app->getModule('thiscovery-theme');
        if (!$theme) {
            return;
        }

        $style = (string)$theme->settings->get('mobileMenuStyle', 'hamburger');
        if ($style === 'bottom-bar') {
            $style = 'floating-bar';
        }
        $rawHamburger = $this->stringIdList($theme->settings->getSerialized('hamburgerNavMenuItemIds', []));
        $rawFloating = $this->stringIdList($theme->settings->getSerialized('floatingNavMenuItemIds', []));
        $hamburgerConfigured = $rawHamburger !== [];
        $floatingConfigured = $rawFloating !== [];

        if ($style === 'floating-bar' && !$floatingConfigured) {
            $rawFloating = ['home', 'dashboard', 'spaces', 'space-chooser'];
            $floatingConfigured = true;
        }

        foreach (NavItem::siteRoots() as $item) {
            $candidates = $this->idCandidates($item);
            $inHamburger = $this->anyInList($candidates, $rawHamburger);
            $inFloating = $this->anyInList($candidates, $rawFloating);

            if ($inFloating) {
                $item->mobile_placement = NavItem::PLACE_FLOATING;
            } elseif ($inHamburger) {
                $item->mobile_placement = NavItem::PLACE_HAMBURGER;
            } elseif (!$hamburgerConfigured && $style === 'hamburger') {
                $item->mobile_placement = NavItem::PLACE_HAMBURGER;
            } elseif ($style === 'floating-bar' && !$inFloating) {
                $item->mobile_placement = NavItem::PLACE_HAMBURGER;
            } elseif ($hamburgerConfigured) {
                $item->mobile_placement = NavItem::PLACE_NONE;
            } else {
                $item->mobile_placement = NavItem::PLACE_HAMBURGER;
            }
            NavItem::updateAll(
                ['mobile_placement' => $item->mobile_placement],
                ['id' => $item->id]
            );
        }

        $this->importCustomHamburgerLinks($theme);
    }

    private function importCustomHamburgerLinks($theme): void
    {
        $links = $theme->settings->getSerialized('hamburgerCustomLinks', []);
        if (!is_array($links) || $links === []) {
            return;
        }

        $existingUrls = [];
        foreach (NavItem::find()->where(['scope' => NavItem::SCOPE_SITE, 'type' => NavItem::TYPE_URL])->all() as $row) {
            $existingUrls[$this->normalizeUrl((string)$row->url)] = true;
        }

        $sort = ((int)NavItem::find()->where(['scope' => NavItem::SCOPE_SITE, 'parent_id' => null])->max('sort_order')) + 10;
        foreach ($links as $link) {
            if (!is_array($link)) {
                continue;
            }
            $label = trim((string)($link['label'] ?? ''));
            $url = trim((string)($link['url'] ?? ''));
            if ($label === '' && $url === '') {
                continue;
            }
            if ($url === '') {
                $url = '/';
            }
            $normalized = $this->normalizeUrl($url);
            if (isset($existingUrls[$normalized])) {
                continue;
            }
            $item = new NavItem();
            $item->scope = NavItem::SCOPE_SITE;
            $item->parent_id = null;
            $item->depth = 1;
            $item->type = NavItem::TYPE_URL;
            $item->label = $label !== '' ? $label : $url;
            $item->url = $url;
            $item->visibility = NavItem::VIS_ALL;
            $item->enabled = 1;
            $item->mobile_placement = NavItem::PLACE_HAMBURGER;
            $item->sort_order = $sort;
            $item->save(false);
            $existingUrls[$normalized] = true;
            $sort += 10;
        }
    }

    /**
     * @return array<int, string>
     */
    private function idCandidates(NavItem $item): array
    {
        $ids = [$item->menuId()];
        $key = trim((string)$item->source_key);
        if ($key !== '' && !in_array($key, $ids, true)) {
            $ids[] = $key;
        }
        $ids[] = 'tn-' . (int)$item->id;
        return array_values(array_unique($ids));
    }

    /**
     * @param array<int, string> $candidates
     * @param array<int, string> $list
     */
    private function anyInList(array $candidates, array $list): bool
    {
        foreach ($candidates as $id) {
            if (in_array($id, $list, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param mixed $value
     * @return array<int, string>
     */
    private function stringIdList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $id) {
            $id = trim((string)$id);
            if ($id !== '') {
                $out[] = $id;
            }
        }
        return $out;
    }

    private function normalizeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = $url;
        }
        return rtrim($path, '/') ?: '/';
    }
}
