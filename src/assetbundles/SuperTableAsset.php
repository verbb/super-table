<?php
namespace verbb\supertable\assetbundles;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

use verbb\base\assetbundles\CpAsset as VerbbCpAsset;

class SuperTableAsset extends AssetBundle
{
    // Public Methods
    // =========================================================================

    public function init(): void
    {
        $this->sourcePath = "@verbb/supertable/resources/dist";

        $this->depends = [
            VerbbCpAsset::class,
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
