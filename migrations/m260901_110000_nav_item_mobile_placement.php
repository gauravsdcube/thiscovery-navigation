<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

use humhub\components\Migration;

class m260901_110000_nav_item_mobile_placement extends Migration
{
    public function safeUp()
    {
        $this->safeAddColumn('thiscovery_nav_item', 'mobile_placement', $this->string(16)->notNull()->defaultValue('hamburger'));
    }

    public function safeDown()
    {
        $this->safeDropColumn('thiscovery_nav_item', 'mobile_placement');
    }
}
