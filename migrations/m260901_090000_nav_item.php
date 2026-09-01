<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

use humhub\components\Migration;

class m260901_090000_nav_item extends Migration
{
    public function safeUp()
    {
        $this->safeCreateTable('thiscovery_nav_item', [
            'id' => $this->primaryKey(),
            'scope' => $this->string(16)->notNull()->defaultValue('site'),
            'space_id' => $this->integer()->null(),
            'parent_id' => $this->integer()->null(),
            'sort_order' => $this->integer()->notNull()->defaultValue(100),
            'depth' => $this->smallInteger()->notNull()->defaultValue(1),
            'type' => $this->string(16)->notNull(),
            'source_key' => $this->string(64)->null(),
            'label' => $this->string(128)->notNull()->defaultValue(''),
            'icon' => $this->string(64)->null(),
            'url' => $this->string(512)->null(),
            'visibility' => $this->string(16)->notNull()->defaultValue('all'),
            'new_window' => $this->boolean()->notNull()->defaultValue(0),
            'enabled' => $this->boolean()->notNull()->defaultValue(1),
            'created_at' => $this->dateTime()->null(),
            'created_by' => $this->integer()->null(),
            'updated_at' => $this->dateTime()->null(),
            'updated_by' => $this->integer()->null(),
        ]);
        $this->safeCreateIndex('idx_tnav_scope_parent', 'thiscovery_nav_item', ['scope', 'space_id', 'parent_id', 'sort_order']);
        $this->safeCreateIndex('idx_tnav_source', 'thiscovery_nav_item', ['scope', 'space_id', 'source_key']);
        $this->safeAddForeignKey(
            'fk_tnav_parent',
            'thiscovery_nav_item',
            'parent_id',
            'thiscovery_nav_item',
            'id',
            'CASCADE'
        );
        $this->safeAddForeignKey(
            'fk_tnav_space',
            'thiscovery_nav_item',
            'space_id',
            'space',
            'id',
            'CASCADE'
        );
    }

    public function safeDown()
    {
        $this->safeDropTable('thiscovery_nav_item');
    }
}
