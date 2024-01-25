<?php
namespace verbb\supertable\fields;

use Craft;
use craft\fields\Matrix;
use craft\helpers\ArrayHelper;

class SuperTableField extends Matrix
{
    // Static Methods
    // =========================================================================

    public static function displayName(): string
    {
        return Craft::t('super-table', 'Super Table');
    }


    // Public Methods
    // =========================================================================

    public function __construct($config = [])
    {
        // Config normalization
        unset($config['blockTypeFields']);
        unset($config['changedFieldIndicator']);
        unset($config['columns']);
        unset($config['fieldLayout']);
        unset($config['selectionLabel']);
        unset($config['staticField']);
        unset($config['placeholderKey']);

        if (array_key_exists('minRows', $config)) {
            $config['minEntries'] = ArrayHelper::remove($config, 'minRows');
        }

        if (array_key_exists('maxRows', $config)) {
            $config['maxEntries'] = ArrayHelper::remove($config, 'maxRows');
        }

        parent::__construct($config);
    }
}
