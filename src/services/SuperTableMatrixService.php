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
use craft\events\BlockTypesEvent;
use craft\helpers\ArrayHelper;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use craft\models\MatrixBlockType;
use craft\validators\ArrayValidator;
use craft\web\assets\matrix\MatrixAsset;
use craft\web\assets\matrixsettings\MatrixSettingsAsset;

use yii\base\Component;
use yii\base\Exception;
use yii\base\InvalidConfigException;

class SuperTableMatrixService extends Component
{
    const EVENT_SET_FIELD_BLOCK_TYPES = 'setFieldBlockTypes';

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
            Json::encode($view->getNamespace(), JSON_UNESCAPED_UNICODE) . ', ' .
            Json::encode($view->namespaceInputName('blockTypes[__BLOCK_TYPE_NESTED__][fields][__FIELD_NESTED__][typesettings]')) .
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

        return $view->renderTemplate('_components/fieldtypes/Matrix/settings',
            [
                'matrixField' => $matrixField,
                'fieldTypes' => $fieldTypeOptions,
                'blockTypes' => $blockTypes,
                'blockTypeFields' => $blockTypeFields,
            ]);
    }

    public function getMatrixInputHtml($matrixField, $value, ElementInterface $element = null): string
    {
        if ($element !== null && $element->hasEagerLoadedElements($matrixField->handle)) {
            $value = $element->getEagerLoadedElements($matrixField->handle);
        }

        if ($value instanceof MatrixBlockQuery) {
            $value = $value->getCachedResult() ?? $value->limit(null)->anyStatus()->all();
        }

        $view = Craft::$app->getView();
        $id = $view->formatInputId($matrixField->handle);

        // Let plugins/modules override which block types should be available for this field
        $event = new BlockTypesEvent([
            'blockTypes' => $matrixField->getBlockTypes(),
            'element' => $element,
            'value' => $value,
        ]);
        $this->trigger(self::EVENT_SET_FIELD_BLOCK_TYPES, $event);
        $blockTypes = array_values($event->blockTypes);

        if (empty($blockTypes)) {
            throw new InvalidConfigException('At least one block type is required.');
        }

        // Get the block types data
        $blockTypeInfo = $this->_getBlockTypeInfoForInput($matrixField, $element, $blockTypes);
        $createDefaultBlocks = $matrixField->minBlocks != 0 && count($blockTypeInfo) === 1;
        $staticBlocks = (
            $createDefaultBlocks &&
            $matrixField->minBlocks == $matrixField->maxBlocks &&
            $matrixField->maxBlocks >= count($value)
        );

        $view->registerAssetBundle(SuperTableAsset::class);

        $js = 'var matrixInputAlt = new Craft.SuperTable.MatrixInputAlt(' . 
            '"' . $view->namespaceInputId($id) . '", ' .
            Json::encode($blockTypeInfo, JSON_UNESCAPED_UNICODE) . ', ' .
            '"' . $view->namespaceInputName($matrixField->handle) . '", ' .
            ($matrixField->maxBlocks ?: 'null') .
            ');';

        // Safe to create the default blocks?
        if ($createDefaultBlocks) {
            $blockTypeJs = Json::encode($blockTypes[0]->handle);
            for ($i = count($value); $i < $matrixField->minBlocks; $i++) {
                $js .= "\nmatrixInputAlt.addBlock({$blockTypeJs});";
            }
        }

        $view->registerJs($js);

        return $view->renderTemplate('_components/fieldtypes/Matrix/input',
            [
                'id' => $id,
                'name' => $matrixField->handle,
                'blockTypes' => $blockTypes,
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

        foreach (Craft::$app->getFields()->getAllFieldTypes() as $class) {
            /** @var Field|string $class */
            // No Matrix-Inception, sorry buddy.
            if ($class === 'craft\fields\Matrix' || $class === 'verbb\supertable\fields\SuperTableField') {
                continue;
            }

            $fieldTypes[] = [
                'type' => $class,
                'name' => $class::displayName(),
            ];
        }

        // Sort them by name
        ArrayHelper::multisort($fieldTypes, 'name');

        return $fieldTypes;
    }

    private function _getBlockTypeInfoForInput($matrixField, ElementInterface $element = null, array $blockTypes): array
    {
        /** @var Element $element */
        $blockTypeInfo = [];

        $view = Craft::$app->getView();

        // Set a temporary namespace for these
        $originalNamespace = $view->getNamespace();
        $namespace = $view->namespaceInputName($matrixField->handle . '[blocks][__BLOCK2__][fields]', $originalNamespace);
        $view->setNamespace($namespace);

        foreach ($blockTypes as $blockType) {
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

            $view->startJsBuffer();

            $bodyHtml = $view->namespaceInputs($view->renderTemplate('_includes/fields',
                [
                    'namespace' => null,
                    'fields' => $fieldLayoutFields,
                    'element' => $block,
                ]));

            // Reset $_isFresh's
            foreach ($fieldLayoutFields as $field) {
                $field->setIsFresh(null);
            }

            $footHtml = $view->clearJsBuffer();

            $blockTypeInfo[] = [
                'handle' => $blockType->handle,
                'name' => Craft::t('site', $blockType->name),
                'bodyHtml' => $bodyHtml,
                'footHtml' => $footHtml,
            ];
        }

        $view->setNamespace($originalNamespace);

        return $blockTypeInfo;
    }

}