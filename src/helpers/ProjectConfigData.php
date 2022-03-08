<?php
namespace verbb\supertable\helpers;

use verbb\supertable\SuperTable;

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