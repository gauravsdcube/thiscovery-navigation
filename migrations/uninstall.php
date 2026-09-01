<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

use humhub\components\Migration;

class uninstall extends Migration
{
    public function up()
    {
        $this->safeDropTable('thiscovery_nav_item');
    }

    public function down()
    {
        echo "uninstall does not support migration down.\n";
        return false;
    }
}
