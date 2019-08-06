<?php
namespace verbb\supertable\services;

// use verbb\supertable\SuperTable;
// use verbb\supertable\elements\db\SuperTableBlockQuery;
// use verbb\supertable\elements\SuperTableBlockElement;
// use verbb\supertable\models\SuperTableBlockTypeModel;
use verbb\supertable\assetbundles\SuperTableAsset;

use Craft;
use craft\base\EagerLoadingFieldInterface;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\base\FieldInterface;
use craft\db\Query;
use craft\elements\db\ElementQuery;
use craft\elements\db\ElementQueryInterface;
use craft\elements\db\MatrixBlockQuery;
use craft\elements\MatrixBlock;
use craft\helpers\ArrayHelper;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use craft\models\MatrixBlockType;
use craft\validators\ArrayValidator;
use craft\web\assets\matrix\MatrixAsset;
use craft\web\assets\matrixsettings\MatrixSettingsAsset;

use yii\base\Component;
use yii\base\Exception;

class SuperTableMatrixService extends Component
{
    // Public Methods
    // =========================================================================


    //
    // Extracted from MatrixFieldType - must be modified otherwise will create infinite loop
    //

    public function getMatrixSettingsHtml($matrixField)
    {
        // Get the available field types data
        $fieldTypeInfo = $this->_getFieldOptionsForConfigurator();

        $view = Craft::$app->getView();

        $view->registerAssetBundle(SuperTableAsset::class);

        $view->registerJs('new Craft.SuperTable.MatrixConfiguratorAlt(' .
            Json::encode($fieldTypeInfo, JSON_UNESCAPED_UNICODE) . ', ' .
            Json::encode(Craft::$app->getView()->getNamespace(), JSON_UNESCAPED_UNICODE) .
        ');');

        // Look for any missing fields and convert to Plain Text
        foreach ($matrixField->getBlockTypes() as $blockType) {
            /** @var Field[] $blockTypeFields */
            $blockTypeFields = $blockType->getFields();

            foreach ($blockTypeFields as $i => $field) {
                if ($field instanceof MissingField) {
                    $blockTypeFields[$i] = $field->createFallback(PlainText::class);
                    $blockTypeFields[$i]->addError('type', Craft::t('app', 'The field type “{type}” could not be found.', [
                        'type' => $field->expectedType
                    ]));
                    $blockType->hasFieldErrors = true;
                }
            }

            $blockType->setFields($blockTypeFields);
        }

        $fieldsService = Craft::$app->getFields();
        /** @var string[]|FieldInterface[] $allFieldTypes */
        $allFieldTypes = $fieldsService->getAllFieldTypes();
        $fieldTypeOptions = [];

        foreach ($allFieldTypes as $class) {
            // No Matrix-Inception, sorry buddy.
            if ($class === 'craft\fields\Matrix' || $class === 'verbb\supertable\fields\SuperTableField') {
                $enabled = false;
            } else {
                $enabled = true;
            }

            $fieldTypeOptions['new'][] = [
                'value' => $class,
                'label' => $class::displayName(),
                'disabled' => !$enabled,
            ];
        }

        // Sort them by name
        ArrayHelper::multisort($fieldTypeOptions['new'], 'label');

        if (!$matrixField->getIsNew()) {
            foreach ($matrixField->getBlockTypes() as $blockType) {
                foreach ($blockType->getFields() as $field) {
                    /** @var Field $field */
                    if (!$field->getIsNew()) {
                        $fieldTypeOptions[$field->id] = [];
                        $compatibleFieldTypes = $fieldsService->getCompatibleFieldTypes($field, true);
                        foreach ($allFieldTypes as $class) {
                            // No Matrix-Inception, sorry buddy.
                            if ($class !== 'craft\fields\Matrix' && $class !== 'verbb\supertable\fields\SuperTableField') {
                                $compatible = in_array($class, $compatibleFieldTypes, true);
                                $fieldTypeOptions[$field->id][] = [
                                    'value' => $class,
                                    'label' => $class::displayName().($compatible ? '' : ' ⚠️'),
                                ];
                            }
                        }

                        // Sort them by name
                        ArrayHelper::multisort($fieldTypeOptions[$field->id], 'label');
                    }
                }
            }
        }

        $blockTypes = [];
        $blockTypeFields = [];
        $totalNewBlockTypes = 0;

        foreach ($matrixField->getBlockTypes() as $blockType) {
            $blockTypeId = (string)($blockType->id ?? 'new' . ++$totalNewBlockTypes);
            $blockTypes[$blockTypeId] = $blockType;

            $blockTypeFields[$blockTypeId] = [];
            $totalNewFields = 0;
            foreach ($blockType->getFields() as $field) {
                $fieldId = (string)($field->id ?? 'new' . ++$totalNewFields);
                $blockTypeFields[$blockTypeId][$fieldId] = $field;
            }
        }

        return Craft::$app->getView()->renderTemplate('_components/fieldtypes/Matrix/settings',
            [
                'matrixField' => $matrixField,
                'fieldTypes' => $fieldTypeOptions,
                'blockTypes' => $blockTypes,
                'blockTypeFields' => $blockTypeFields,
            ]);
    }

    public function getMatrixInputHtml($matrixField, $value, ElementInterface $element = null): string
    {
        $id = Craft::$app->getView()->formatInputId($matrixField->handle);

        // Get the block types data
        $blockTypeInfo = $this->_getBlockTypeInfoForInput($matrixField, $element);

        $createDefaultBlocks = $matrixField->minBlocks != 0 && count($blockTypeInfo) === 1;
        $staticBlocks = $createDefaultBlocks && $matrixField->minBlocks == $matrixField->maxBlocks;

        Craft::$app->getView()->registerAssetBundle(SuperTableAsset::class);

        Craft::$app->getView()->registerJs('new Craft.SuperTable.MatrixInputAlt('.
            '"'.Craft::$app->getView()->namespaceInputId($id).'", '.
            Json::encode($blockTypeInfo, JSON_UNESCAPED_UNICODE).', '.
            '"'.Craft::$app->getView()->namespaceInputName($matrixField->handle).'", '.
            ($matrixField->maxBlocks ?: 'null').
            ');');

        /** @var Element $element */
        if ($element !== null && $element->hasEagerLoadedElements($matrixField->handle)) {
            $value = $element->getEagerLoadedElements($matrixField->handle);
        }

        if ($value instanceof MatrixBlockQuery) {
            $value = $value->anyStatus()->all();
        }

        // Safe to set the default blocks?
        if ($createDefaultBlocks) {
            $blockType = $matrixField->getBlockTypes()[0];

            for ($i = count($value); $i < $matrixField->minBlocks; $i++) {
                $block = new MatrixBlock();
                $block->fieldId = $matrixField->id;
                $block->typeId = $blockType->id;
                $block->siteId = $element->siteId ?? Craft::$app->getSites()->getCurrentSite()->id;
                $value[] = $block;
            }
        }

        return Craft::$app->getView()->renderTemplate('_components/fieldtypes/Matrix/input',
            [
                'id' => $id,
                'name' => $matrixField->handle,
                'blockTypes' => $matrixField->getBlockTypes(),
                'blocks' => $value,
                'static' => false,
                'staticBlocks' => $staticBlocks,
            ]);
    }



    // Private Methods
    // =========================================================================

    /**
     * Returns info about each field type for the configurator.
     *
     * @return array
     */
    private function _getFieldOptionsForConfigurator(): array
    {
        $fieldTypes = [];

        // Set a temporary namespace for these
        $originalNamespace = Craft::$app->getView()->getNamespace();
        $namespace = Craft::$app->getView()->namespaceInputName('blockTypes[__BLOCK_TYPE_NESTED__][fields][__FIELD_NESTED__][typesettings]', $originalNamespace);
        Craft::$app->getView()->setNamespace($namespace);

        foreach (Craft::$app->getFields()->getAllFieldTypes() as $class) {
            /** @var Field|string $class */
            // No Matrix-Inception, sorry buddy.
            if ($class === 'craft\fields\Matrix' || $class === 'verbb\supertable\fields\SuperTableField') {
                continue;
            }

            Craft::$app->getView()->startJsBuffer();
            /** @var FieldInterface $field */
            $field = new $class();
            $settingsBodyHtml = Craft::$app->getView()->namespaceInputs((string)$field->getSettingsHtml());
            $settingsFootHtml = Craft::$app->getView()->clearJsBuffer();

            $fieldTypes[] = [
                'type' => $class,
                'name' => $class::displayName(),
                'settingsBodyHtml' => $settingsBodyHtml,
                'settingsFootHtml' => $settingsFootHtml,
            ];
        }

        // Sort them by name
        ArrayHelper::multisort($fieldTypes, 'name');

        Craft::$app->getView()->setNamespace($originalNamespace);

        return $fieldTypes;
    }

    private function _getBlockTypeInfoForInput($matrixField, ElementInterface $element = null): array
    {
        /** @var Element $element */
        $blockTypes = [];

        // Set a temporary namespace for these
        $originalNamespace = Craft::$app->getView()->getNamespace();
        $namespace = Craft::$app->getView()->namespaceInputName($matrixField->handle.'[__BLOCK2__][fields]', $originalNamespace);
        Craft::$app->getView()->setNamespace($namespace);

        foreach ($matrixField->getBlockTypes() as $blockType) {
            // Create a fake MatrixBlock so the field types have a way to get at the owner element, if there is one
            $block = new MatrixBlock();
            $block->fieldId = $matrixField->id;
            $block->typeId = $blockType->id;

            if ($element) {
                $block->setOwner($element);
                $block->siteId = $element->siteId;
            }

            $fieldLayoutFields = $blockType->getFieldLayout()->getFields();

            foreach ($fieldLayoutFields as $field) {
                $field->setIsFresh(true);
            }

            Craft::$app->getView()->startJsBuffer();

            $bodyHtml = Craft::$app->getView()->namespaceInputs(Craft::$app->getView()->renderTemplate('_includes/fields',
                [
                    'namespace' => null,
                    'fields' => $fieldLayoutFields,
                    'element' => $block,
                ]));

            // Reset $_isFresh's
            foreach ($fieldLayoutFields as $field) {
                $field->setIsFresh(null);
            }

            $footHtml = Craft::$app->getView()->clearJsBuffer();

            $blockTypes[] = [
                'handle' => $blockType->handle,
                'name' => Craft::t('site', $blockType->name),
                'bodyHtml' => $bodyHtml,
                'footHtml' => $footHtml,
            ];
        }

        Craft::$app->getView()->setNamespace($originalNamespace);

        return $blockTypes;
    }

}