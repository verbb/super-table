<?php
namespace verbb\supertable\helpers;

use verbb\supertable\SuperTable;
use verbb\supertable\elements\SuperTableBlockElement;

use Craft;
use craft\db\Query;
use craft\db\Table;
use craft\helpers\Json;

class ProjectConfigData
{
    // Project config rebuild methods
    // =========================================================================

    public static function rebuildProjectConfig(): array
    {
        $data = [];

        foreach (SuperTable::$plugin->getService()->getAllBlockTypes() as $blockType) {
            $data[$blockType->uid] = $blockType->getConfig();
        }

        return $data;
    }
}