<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace WelfordMedia\CraftTikTok\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Table as CraftTable;
use craft\helpers\Json;
use craft\helpers\MigrationHelper;
use craft\records\FieldLayout;
use Exception;
use ReflectionClass;
use yii\base\NotSupportedException;

/**
 * Installation Migration
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Install extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->createTables();
        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->dropTables();
        return true;
    }

    public function createTables()
    {
        $this->archiveTableIfExists("{{%tiktok_variant_mapping}}");
        $this->createTable("{{%tiktok_variant_mapping}}", [
            "variantId" => $this->integer()->notNull(),
            "tiktokProductId" => $this->integer()->notNull(),
        ]);
    }

    public function dropTables()
    {
        $this->dropTableIfExists("{{%tiktok_variant_mapping}}");
    }
}
