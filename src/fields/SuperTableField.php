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

    public static function icon(): string
    {
        return '@verbb/supertable/icon-mask.svg';
    }

    /**
     * @var string Content table name
     * @deprecated in 4.0.0
     */
    public string $contentTable = '';

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
        unset($config['placeholderKey']);

        if (array_key_exists('minRows', $config)) {
            $config['minEntries'] = ArrayHelper::remove($config, 'minRows');
        }

        if (array_key_exists('maxRows', $config)) {
            $config['maxEntries'] = ArrayHelper::remove($config, 'maxRows');
        }

        // We need to keep the contentTable value around, as it's needed for the v4 (Craft 5) upgrade.
        // (Explicitly set it here because Matrix::__construct() just unsets it.)
        $this->contentTable = ArrayHelper::remove($config, 'contentTable') ?? '';

        parent::__construct($config);
    }

    public function setStaticField(): void
    {
        Craft::$app->getDeprecator()->log(__METHOD__, 'The `' . $this->handle . '` static Super Table field is no longer supported. Update any references from `block.myField` to `block.one().myField`.');
    }
}
