<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryNavigation\models;

use humhub\components\ActiveRecord;
use humhub\modules\thiscoveryNavigation\services\CatalogService;
use Yii;

/**
 * @property int $id
 * @property string $scope
 * @property int|null $space_id
 * @property int|null $parent_id
 * @property int $sort_order
 * @property int $depth
 * @property string $type
 * @property string|null $source_key
 * @property string $label
 * @property string|null $icon
 * @property int $show_icon
 * @property string|null $url
 * @property string $visibility
 * @property string $mobile_placement
 * @property int $new_window
 * @property int $enabled
 */
class NavItem extends ActiveRecord
{
    public const SCOPE_SITE = 'site';
    public const SCOPE_SPACE = 'space';

    public const TYPE_GROUP = 'group';
    public const TYPE_MODULE = 'module';
    public const TYPE_PAGE = 'page';
    public const TYPE_FORM = 'form';
    public const TYPE_MAP = 'map';
    public const TYPE_URL = 'url';

    public const VIS_ALL = 'all';
    public const VIS_GUESTS = 'guests';
    public const VIS_USERS = 'users';
    public const VIS_ADMIN = 'admin';
    public const VIS_HIDDEN = 'hidden';

    public const PLACE_HAMBURGER = 'hamburger';
    public const PLACE_FLOATING = 'floating';
    public const PLACE_NONE = 'none';

    public static function tableName()
    {
        return 'thiscovery_nav_item';
    }

    public function rules()
    {
        return [
            [['type', 'label'], 'required'],
            [['space_id', 'parent_id', 'sort_order', 'depth', 'created_by', 'updated_by'], 'integer'],
            [['new_window', 'enabled', 'show_icon'], 'boolean'],
            [['scope'], 'string', 'max' => 16],
            [['type', 'visibility', 'mobile_placement'], 'string', 'max' => 16],
            [['source_key', 'icon'], 'string', 'max' => 64],
            [['label'], 'string', 'max' => 128],
            [['url'], 'string', 'max' => 512],
            [['scope'], 'in', 'range' => [self::SCOPE_SITE, self::SCOPE_SPACE]],
            [['type'], 'in', 'range' => [
                self::TYPE_GROUP, self::TYPE_MODULE, self::TYPE_PAGE,
                self::TYPE_FORM, self::TYPE_MAP, self::TYPE_URL,
            ]],
            [['visibility'], 'in', 'range' => [
                self::VIS_ALL, self::VIS_GUESTS, self::VIS_USERS, self::VIS_ADMIN, self::VIS_HIDDEN,
            ]],
            [['mobile_placement'], 'in', 'range' => [
                self::PLACE_HAMBURGER, self::PLACE_FLOATING, self::PLACE_NONE,
            ]],
            [['depth'], 'integer', 'min' => 1, 'max' => 3],
        ];
    }

    public function beforeSave($insert)
    {
        if ($this->scope === '') {
            $this->scope = self::SCOPE_SITE;
        }
        if ($this->visibility === '') {
            $this->visibility = self::VIS_ALL;
        }
        if ($this->mobile_placement === '' || $this->mobile_placement === null) {
            $this->mobile_placement = self::PLACE_HAMBURGER;
        }
        if ($this->show_icon === null || $this->show_icon === '') {
            $this->show_icon = 1;
        }
        if ($this->parent_id) {
            $this->mobile_placement = self::PLACE_HAMBURGER;
        }
        if ($this->sort_order === null) {
            $this->sort_order = 100;
        }
        if ($this->isAttributeChanged('parent_id')) {
            $this->populateRelation('parent', $this->parent_id ? static::findOne((int)$this->parent_id) : null);
        }
        $this->depth = $this->parent_id ? (int)($this->parent->depth ?? 1) + 1 : 1;
        if ($this->depth > 3) {
            $this->addError('parent_id', Yii::t('ThiscoveryNavigationModule.base', 'Menus can only nest three levels.'));
            return false;
        }
        return parent::beforeSave($insert);
    }

    public function getParent()
    {
        return $this->hasOne(self::class, ['id' => 'parent_id']);
    }

    /**
     * @return self[]
     */
    public function getChildItems(): array
    {
        return self::find()
            ->where(['parent_id' => $this->id])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])
            ->all();
    }

    /**
     * @return self[]
     */
    public static function siteRoots(): array
    {
        return self::find()
            ->where(['scope' => self::SCOPE_SITE, 'parent_id' => null])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])
            ->all();
    }

    public static function siteCount(): int
    {
        return (int)self::find()->where(['scope' => self::SCOPE_SITE])->count();
    }

    public function isVisibleToCurrentUser(): bool
    {
        if (!$this->enabled || $this->visibility === self::VIS_HIDDEN) {
            return false;
        }
        $guest = Yii::$app->user->isGuest;
        if ($this->visibility === self::VIS_GUESTS) {
            return $guest;
        }
        if ($this->visibility === self::VIS_USERS) {
            return !$guest;
        }
        if ($this->visibility === self::VIS_ADMIN) {
            return !$guest && Yii::$app->user->isAdmin();
        }
        return true;
    }

    public function displayLabel(): string
    {
        $custom = trim((string)$this->label);
        if ($custom === '') {
            $catalog = (new CatalogService())->byKey((string)$this->source_key);
            return (string)($catalog['label'] ?? Yii::t('ThiscoveryNavigationModule.base', 'Untitled'));
        }
        // Admin-stored labels are English source text — translate at display time.
        return self::maybeTranslateLabel($this->menuId(), $custom);
    }

    /**
     * Soft-dep on thiscovery-translate for custom (non-empty) nav labels.
     */
    protected static function maybeTranslateLabel(string $menuKey, string $label): string
    {
        try {
            $module = Yii::$app->getModule('thiscovery-translate');
            if ($module === null || !method_exists($module, 'getIsEnabled') || !$module->getIsEnabled()) {
                return $label;
            }
            $hook = \humhub\modules\thiscoveryTranslate\services\NavigationHook::class;
            if (!class_exists($hook)) {
                return $label;
            }
            return $hook::translateLabel($menuKey, $label);
        } catch (\Throwable $e) {
            return $label;
        }
    }

    public function showsIcon(): bool
    {
        return (int)$this->show_icon !== 0;
    }

    public function iconName(): string
    {
        if (trim((string)$this->icon) !== '') {
            return (string)$this->icon;
        }
        $catalog = (new CatalogService())->byKey((string)$this->source_key);
        return (string)($catalog['icon'] ?? '');
    }

    public function displayIcon(): string
    {
        return $this->showsIcon() ? $this->iconName() : '';
    }

    public function resolveUrl(): ?string
    {
        if ($this->type === self::TYPE_GROUP) {
            return null;
        }
        if ($this->type === self::TYPE_URL) {
            return trim((string)$this->url) !== '' ? (string)$this->url : null;
        }
        return (new CatalogService())->urlFor($this);
    }

    public function menuId(): string
    {
        $key = trim((string)$this->source_key);
        return $key !== '' ? $key : ('tn-' . (int)$this->id);
    }

    public function mobilePlacement(): string
    {
        $value = (string)$this->mobile_placement;
        if (!in_array($value, [self::PLACE_HAMBURGER, self::PLACE_FLOATING, self::PLACE_NONE], true)) {
            return self::PLACE_HAMBURGER;
        }
        return $value;
    }

    public function sourceAvailable(): bool
    {
        if (in_array($this->type, [self::TYPE_GROUP, self::TYPE_URL], true)) {
            return true;
        }
        return (new CatalogService())->byKey((string)$this->source_key) !== null;
    }
}
