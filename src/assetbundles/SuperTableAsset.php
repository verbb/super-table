<?php
namespace verbb\supertable\assetbundles;

use Craft;
use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class SuperTableAsset extends AssetBundle
{
    // Public Methods
    // =========================================================================

    public function init()
    {
        $this->sourcePath = "@verbb/supertable/resources/dist";

        $this->depends = [
            CpAsset::class,
        ];

        $this->js = [
            'js/super-table.js',
        ];

        $this->css = [
            'css/super-table.css',
        ];

        parent::init();
    }
}
