<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

use humhub\components\Migration;

class m260901_130000_nav_item_show_icon extends Migration
{
    public function safeUp()
    {
        $this->safeAddColumn('thiscovery_nav_item', 'show_icon', $this->boolean()->notNull()->defaultValue(1));
    }

    public function safeDown()
    {
        $this->safeDropColumn('thiscovery_nav_item', 'show_icon');
    }
}
