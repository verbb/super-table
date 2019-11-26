<?php
namespace verbb\supertable\fields;

use verbb\supertable\SuperTable;
use verbb\supertable\elements\db\SuperTableBlockQuery;
use verbb\supertable\elements\SuperTableBlockElement;
use verbb\supertable\models\SuperTableBlockTypeModel;
use verbb\supertable\assetbundles\SuperTableAsset;

use verbb\supertable\gql\arguments\elements\SuperTableBlock as SuperTableBlockArguments;
use verbb\supertable\gql\resolvers\elements\SuperTableBlock as SuperTableBlockResolver;
use verbb\supertable\gql\types\generators\SuperTableBlockType as SuperTableBlockTypeGenerator;

use Craft;
use craft\base\EagerLoadingFieldInterface;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\base\FieldInterface;
use craft\base\GqlInlineFragmentFieldInterface;
use craft\base\GqlInlineFragmentInterface;
use craft\db\Query;
use craft\db\Table as TableName;
use craft\elements\db\ElementQuery;
use craft\elements\db\ElementQueryInterface;
use craft\fields\Matrix;
use craft\gql\GqlEntityRegistry;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use craft\helpers\ElementHelper;
use craft\helpers\Gql as GqlHelper;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use craft\queue\jobs\ResaveElements;
use craft\services\Elements;
use craft\services\Fields;
use craft\validators\ArrayValidator;

use GraphQL\Type\Definition\Type;

use yii\base\InvalidConfigException;
use yii\base\UnknownPropertyException;

class SuperTableField extends Field implements EagerLoadingFieldInterface, GqlInlineFragmentFieldInterface
{
    // Constants
    // =========================================================================
        
    const PROPAGATION_METHOD_NONE = 'none';
    const PROPAGATION_METHOD_SITE_GROUP = 'siteGroup';
    const PROPAGATION_METHOD_LANGUAGE = 'language';
    const PROPAGATION_METHOD_ALL = 'all';


    // Static
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('super-table', 'Super Table');
    }

    /**
     * @inheritdoc
     */
    public static function supportedTranslationMethods(): array
    {
        // Don't ever automatically propagate values to other sites.
        return [
            self::TRANSLATION_METHOD_SITE,
        ];
    }

    /**
     * @inheritdoc
     */
    public static function valueType(): string
    {
        return SuperTableBlockQuery::class;
    }

    /**
     * @inheritdoc
     */
    public static function defaultSelectionLabel(): string
    {
        return Craft::t('super-table', 'Add a row');
    }


    // Properties
    // =========================================================================

    /**
     * @var int|null Min rows
     */
    public $minRows;

    /**
     * @var int|null Max rows
     */
    public $maxRows;

    /**
     * @var string Content table name
     */
    public $contentTable;

    /**
     * @var string Propagation method
     *
     * This will be set to one of the following:
     *
     * - `none` – Only save b locks in the site they were created in
     * - `siteGroup` – Save  blocks to other sites in the same site group
     * - `language` – Save blocks to other sites with the same language
     * - `all` – Save blocks to all sites supported by the owner element
     *
     * @since 2.2.0
     */
    public $propagationMethod = self::PROPAGATION_METHOD_ALL;

    /**
     * @var int Whether each site should get its own unique set of blocks
     */
    public $localizeBlocks = false;

    /**
     * @var SuperTableBlockType[]|null The field’s block types
     */
    private $_blockTypes;

    /**
     * @var SuperTableBlockType[]|null The block types' fields
     */
    private $_blockTypeFields;

    /**
     * @var string The old propagation method for this field
     */
    private $_oldPropagationMethod;

    /**
     * @var bool Whether this field is a Static type layout
     */
    public $staticField;

    public $columns = [];

    // Superseeded - but will throw an error when updating from Craft 2. These will exist in the field
    // settings, but not in this class - we just add them as 'dummy' properties for now...
    public $fieldLayout;
    public $selectionLabel;


    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function __construct($config = [])
    {
        if (array_key_exists('localizeBlocks', $config)) {
            $config['propagationMethod'] = $config['localizeBlocks'] ? 'none' : 'all';
            unset($config['localizeBlocks']);
        }

        parent::__construct($config);
    }

    public function init()
    {
        // todo: remove this in 4.0
        // Set localizeBlocks in case anything is still checking it
        $this->localizeBlocks = $this->propagationMethod === self::PROPAGATION_METHOD_NONE;

        parent::init();
    }

    /**
     * @inheritdoc
     */
    public function settingsAttributes(): array
    {
        return ArrayHelper::withoutValue(parent::settingsAttributes(), 'localizeBlocks');
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        $rules = parent::rules();
        $rules[] = [
            ['propagationMethod'], 'in', 'range' => [
                self::PROPAGATION_METHOD_NONE,
                self::PROPAGATION_METHOD_SITE_GROUP,
                self::PROPAGATION_METHOD_LANGUAGE,
                self::PROPAGATION_METHOD_ALL
            ]
        ];
        $rules[] = [['minRows', 'maxRows'], 'integer', 'min' => 0];
        return $rules;
    }

    /**
     * Returns the block types.
     *
     * @return SuperTableBlockType[]
     */
    public function getBlockTypes(): array
    {
        if ($this->_blockTypes !== null) {
            return $this->_blockTypes;
        }

        if ($this->getIsNew()) {
            return [];
        }

        return $this->_blockTypes = SuperTable::$plugin->getService()->getBlockTypesByFieldId($this->id);
    }

    /**
     * Returns all of the block types' fields.
     *
     * @return FieldInterface[]
     */
    public function getBlockTypeFields(): array
    {
        if ($this->_blockTypeFields !== null) {
            return $this->_blockTypeFields;
        }

        if (empty($blockTypes = $this->getBlockTypes())) {
            return $this->_blockTypeFields = [];
        }

        // Get the fields & layout IDs
        $contexts = [];
        $layoutIds = [];
        foreach ($blockTypes as $blockType) {
            $contexts[] = 'superTableBlockType:' . $blockType->uid;
            $layoutIds[] = $blockType->fieldLayoutId;
        }

        /** @var Field[] $fieldsById */
        $fieldsById = ArrayHelper::index(Craft::$app->getFields()->getAllFields($contexts), 'id');

        // Get all the field IDs grouped by layout ID
        $fieldIdsByLayoutId = Craft::$app->getFields()->getFieldIdsByLayoutIds($layoutIds);

        // Assemble the fields
        $this->_blockTypeFields = [];

        foreach ($blockTypes as $blockType) {
            if (isset($fieldIdsByLayoutId[$blockType->fieldLayoutId])) {
                $fieldColumnPrefix = 'field_';

                foreach ($fieldIdsByLayoutId[$blockType->fieldLayoutId] as $fieldId) {
                    if (isset($fieldsById[$fieldId])) {
                        $fieldsById[$fieldId]->columnPrefix = $fieldColumnPrefix;
                        $this->_blockTypeFields[] = $fieldsById[$fieldId];
                    }
                }
            }
        }

        return $this->_blockTypeFields;
    }

    /**
     * Sets the block types.
     *
     * @param SuperTableBlockType|array $blockTypes The block type settings or actual SuperTableBlockType model instances
     */
    public function setBlockTypes($blockTypes)
    {
        $this->_blockTypes = [];
        $defaultFieldConfig = [
            'type' => null,
            'instructions' => null,
            'required' => false,
            'searchable' => true,
            'translationMethod' => Field::TRANSLATION_METHOD_NONE,
            'translationKeyFormat' => null,
            'typesettings' => null,
        ];

        foreach ($blockTypes as $key => $config) {
            if ($config instanceof SuperTableBlockTypeModel) {
                $this->_blockTypes[] = $config;
            } else {
                $blockType = new SuperTableBlockTypeModel();
                $blockType->fieldId = $this->id;

                // Existing block type?
                if (is_numeric($key)) {
                    $info = (new Query())
                        ->select(['uid', 'fieldLayoutId'])
                        ->from(['{{%supertableblocktypes}}'])
                        ->where(['id'=> $key])
                        ->one();

                    if ($info) {
                        $blockType->id = $key;
                        $blockType->uid = $info['uid'];
                        $blockType->fieldLayoutId = $info['fieldLayoutId'];
                    }
                }

                $fields = [];

                if (!empty($config['fields'])) {
                    foreach ($config['fields'] as $fieldId => $fieldConfig) {
                        /** @noinspection SlowArrayOperationsInLoopInspection */
                        $fieldConfig = array_merge($defaultFieldConfig, $fieldConfig);

                        $fields[] = Craft::$app->getFields()->createField([
                            'type' => $fieldConfig['type'],
                            'id' => is_numeric($fieldId) ? $fieldId : null,
                            'name' => $fieldConfig['name'],
                            'handle' => $fieldConfig['handle'],
                            'instructions' => $fieldConfig['instructions'],
                            'required' => (bool)$fieldConfig['required'],
                            'searchable' => (bool)$fieldConfig['searchable'],
                            'translationMethod' => $fieldConfig['translationMethod'],
                            'translationKeyFormat' => $fieldConfig['translationKeyFormat'],
                            'settings' => $fieldConfig['typesettings'],
                        ]);
                    }
                }

                $blockType->setFields($fields);
                $this->_blockTypes[] = $blockType;
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function validate($attributeNames = null, $clearErrors = true): bool
    {
        // Run basic model validation first
        $validates = parent::validate($attributeNames, $clearErrors);

        // Run SuperTable field validation as well
        if (!SuperTable::$plugin->getService()->validateFieldSettings($this)) {
            $validates = false;
        }

        return $validates;
    }

    /**
     * @inheritdoc
     */
    public static function hasContentColumn(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml()
    {
        // Get the available field types data
        $fieldTypeInfo = $this->_getFieldOptionsForConfigurator();

        $blockTypes = $this->getBlockTypes();

        // Look for any missing fields and convert to Plain Text
        foreach ($this->getBlockTypes() as $blockType) {
            /** @var Field[] $blockTypeFields */
            $blockTypeFields = $blockType->getFields();

            foreach ($blockTypeFields as $i => $field) {
                if ($field instanceof MissingField) {
                    $blockTypeFields[$i] = $field->createFallback(PlainText::class);
                    $blockTypeFields[$i]->addError('type', Craft::t('super-table', 'The field type “{type}” could not be found.', [
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
            // No SuperTable-Inception, sorry buddy.
            $enabled = $class !== self::class;

            $fieldTypeOptions['new'][] = [
                'value' => $class,
                'label' => $class::displayName(),
                'disabled' => !$enabled,
            ];
        }

        // Sort them by name
        ArrayHelper::multisort($fieldTypeOptions['new'], 'label');

        if (!$this->getIsNew()) {
            foreach ($blockTypes as $blockType) {
                foreach ($blockType->getFields() as $field) {
                    /** @var Field $field */
                    if (!$field->getIsNew()) {
                        $fieldTypeOptions[$field->id] = [];
                        $compatibleFieldTypes = $fieldsService->getCompatibleFieldTypes($field, true);

                        foreach ($allFieldTypes as $class) {
                            // No SuperTable-Inception, sorry buddy.
                            if ($class !== self::class && ($class === get_class($field) || $class::isSelectable())) {
                                $compatible = in_array($class, $compatibleFieldTypes, true);

                                $fieldTypeOptions[$field->id][] = [
                                    'value' => $class,
                                    'label' => $class::displayName() . ($compatible ? '' : ' ⚠️'),
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

        foreach ($this->getBlockTypes() as $blockType) {
            $blockTypeId = (string)($blockType->id ?? 'new' . ++$totalNewBlockTypes);
            $blockTypes[$blockTypeId] = $blockType;

            $blockTypeFields[$blockTypeId] = [];
            $totalNewFields = 0;
            foreach ($blockType->getFields() as $field) {
                $fieldId = (string)($field->id ?? 'new' . ++$totalNewFields);
                $blockTypeFields[$blockTypeId][$fieldId] = $field;
            }
        }

        $tableId = ArrayHelper::firstKey($blockTypes) ?? 'new';

        $view = Craft::$app->getView();
        $view->registerAssetBundle(SuperTableAsset::class);
        $view->registerJs('new Craft.SuperTable.Configurator(' .
            Json::encode($tableId, JSON_UNESCAPED_UNICODE) . ', ' .
            Json::encode($fieldTypeInfo, JSON_UNESCAPED_UNICODE) . ', ' .
            Json::encode(Craft::$app->getView()->getNamespace(), JSON_UNESCAPED_UNICODE) .
        ');');

        return Craft::$app->getView()->renderTemplate('super-table/settings', [
            'supertableField' => $this,
            'fieldTypes' => $fieldTypeOptions,
            'blockTypes' => $blockTypes,
            'blockTypeFields' => $blockTypeFields,
        ]);
    }

    /**
     * @inheritdoc
     */
    public function normalizeValue($value, ElementInterface $element = null)
    {
        if ($value instanceof ElementQueryInterface) {
            return $value;
        }

        /** @var Element|null $element */
        $query = SuperTableBlockElement::find();

        // Existing element?
        if ($element && $element->id) {
            $query->ownerId($element->id);
        } else {
            $query->id(false);
        }

        $query
            ->fieldId($this->id)
            ->siteId($element->siteId ?? null);

        // Set the initially matched elements if $value is already set, which is the case if there was a validation
        // error or we're loading an entry revision.
        if (is_array($value) || $value === '') {
            $query->setCachedResult($this->_createBlocksFromSerializedData($value, $element));
        }

        return $query;
    }

    /**
     * @inheritdoc
     */
    public function serializeValue($value, ElementInterface $element = null)
    {
        /** @var SuperTableBlockQuery $value */
        $serialized = [];
        $new = 0;

        foreach ($value->all() as $block) {
            $blockId = $block->id ?? 'new' . ++$new;

            $serialized[$blockId] = [
                'type' => $block->getType()->id,
                'fields' => $block->getSerializedFieldValues(),
            ];
        }

        return $serialized;
    }

    /**
     * @inheritdoc
     */
    public function modifyElementsQuery(ElementQueryInterface $query, $value)
    {
        /** @var ElementQuery $query */
        if ($value === 'not :empty:') {
            $value = ':notempty:';
        }

        if ($value === ':notempty:' || $value === ':empty:') {
            $alias = 'supertableblocks_' . $this->handle;
            $operator = ($value === ':notempty:' ? '!=' : '=');

            $query->subQuery->andWhere(
                "(select count([[{$alias}.id]]) from {{%supertableblocks}} {{{$alias}}} where [[{$alias}.ownerId]] = [[elements.id]] and [[{$alias}.fieldId]] = :fieldId) {$operator} 0",
                [':fieldId' => $this->id]
            );
        } else if ($value !== null) {
            return false;
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public function getIsTranslatable(ElementInterface $element = null): bool
    {
        return $this->propagationMethod !== self::PROPAGATION_METHOD_ALL;
    }

    /**
     * @inheritdoc
     */
    public function getInputHtml($value, ElementInterface $element = null): string
    {
        /** @var Element $element */
        if ($element !== null && $element->hasEagerLoadedElements($this->handle)) {
            $value = $element->getEagerLoadedElements($this->handle);
        }

        if ($value instanceof SuperTableBlockQuery) {
            $value = $value->getCachedResult() ?? $value->limit(null)->anyStatus()->all();
        }

        $id = Craft::$app->getView()->formatInputId($this->handle);

        // Get the block types data
        $blockTypeInfo = $this->_getBlockTypeInfoForInput($element);

        $createDefaultBlocks = $this->minRows != 0 && count($blockTypeInfo) === 1;

        $staticBlocks = (
            $createDefaultBlocks &&
            $this->minRows == $this->maxRows &&
            $this->maxRows >= count($value)
        );

        Craft::$app->getView()->registerAssetBundle(SuperTableAsset::class);

        Craft::$app->getView()->registerJs('new Craft.SuperTable.Input('.
            '"'.Craft::$app->getView()->namespaceInputId($id).'", '.
            Json::encode($blockTypeInfo, JSON_UNESCAPED_UNICODE).', '.
            '"'.Craft::$app->getView()->namespaceInputName($this->handle).'", '.
            Json::encode($this, JSON_UNESCAPED_UNICODE).
            ');');

        // Safe to set the default blocks?
        if ($createDefaultBlocks || $this->staticField) {
            $blockType = $this->getBlockTypes()[0];

            $minRows = ($this->staticField) ? 1 : $this->minRows;

            for ($i = count($value); $i < $minRows; $i++) {
                $block = new SuperTableBlockElement();
                $block->fieldId = $this->id;
                $block->typeId = $blockType->id;
                $block->siteId = $element->siteId ?? Craft::$app->getSites()->getCurrentSite()->id;

                // Set each field to be fresh for auto-generated or static rows
                foreach ($blockType->getFieldLayout()->getFields() as $field) {
                    $field->setIsFresh(true);
                }

                $value[] = $block;
            }
        }

        return Craft::$app->getView()->renderTemplate('super-table/input', [
            'id' => $id,
            'name' => $this->handle,
            'blockTypes' => $this->getBlockTypes(),
            'blocks' => $value,
            'static' => false,
            'staticBlocks' => $staticBlocks,
            'staticField' => $this->staticField,
            'supertableField' => $this,
        ]);
    }

    /**
     * @inheritdoc
     */
    public function getElementValidationRules(): array
    {
        return [
            'validateBlocks',
            [
                ArrayValidator::class,
                'min' => $this->minRows ?: null,
                'max' => $this->maxRows ?: null,
                'tooFew' => Craft::t('super-table', '{attribute} should contain at least {min, number} {min, plural, one{block} other{blocks}}.'),
                'tooMany' => Craft::t('super-table', '{attribute} should contain at most {max, number} {max, plural, one{block} other{blocks}}.'),
                'skipOnEmpty' => false,
                'on' => Element::SCENARIO_LIVE,
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function isValueEmpty($value, ElementInterface $element): bool
    {
        /** @var SuperTableBlockQuery $value */
        return $value->count() === 0;
    }

    /**
     * Validates an owner element’s Super Table blocks.
     *
     * @param ElementInterface $element
     */
    public function validateBlocks(ElementInterface $element)
    {
        /** @var Element $element */
        /** @var SuperTableBlockQuery $value */
        $value = $element->getFieldValue($this->handle);

        foreach ($value->all() as $i => $block) {
            /** @var SuperTableBlockElement $block */
            if ($element->getScenario() === Element::SCENARIO_LIVE) {
                $block->setScenario(Element::SCENARIO_LIVE);
            }

            if (!$block->validate()) {
                foreach ($block->getErrors() as $attribute => $errors) {
                    $element->addErrors([
                        "{$this->handle}[{$i}].{$attribute}" => $errors,
                    ]);
                }
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function getSearchKeywords($value, ElementInterface $element): string
    {
        /** @var SuperTableBlockQuery $value */
        /** @var SuperTableBlockElement $block */
        $keywords = [];
        $contentService = Craft::$app->getContent();

        foreach ($value->all() as $block) {
            $originalContentTable = $contentService->contentTable;
            $originalFieldColumnPrefix = $contentService->fieldColumnPrefix;
            $originalFieldContext = $contentService->fieldContext;

            $contentService->contentTable = $block->getContentTable();
            $contentService->fieldColumnPrefix = $block->getFieldColumnPrefix();
            $contentService->fieldContext = $block->getFieldContext();

            foreach (Craft::$app->getFields()->getAllFields() as $field) {
                /** @var Field $field */
                if ($field->searchable) {
                    $fieldValue = $block->getFieldValue($field->handle);
                    $keywords[] = $field->getSearchKeywords($fieldValue, $element);
                }
            }

            $contentService->contentTable = $originalContentTable;
            $contentService->fieldColumnPrefix = $originalFieldColumnPrefix;
            $contentService->fieldContext = $originalFieldContext;
        }

        return parent::getSearchKeywords($keywords, $element);
    }

    /**
     * @inheritdoc
     */
    public function getStaticHtml($value, ElementInterface $element): string
    {
        $value = $value->all();

        if (empty($value)) {
            return '<p class="light">' . Craft::t('super-table', 'No blocks.') . '</p>';
        }

        $id = StringHelper::randomString();

        Craft::$app->getView()->registerAssetBundle(SuperTableAsset::class);

        return Craft::$app->getView()->renderTemplate('super-table/input', [
            'id' => $id,
            'name' => $id,
            'blockTypes' => $this->getBlockTypes(),
            'blocks' => $value,
            'static' => true,
            'staticBlocks' => true,
            'staticField' => $this->staticField,
            'supertableField' => $this,
        ]);
    }

    /**
     * @inheritdoc
     */
    public function getEagerLoadingMap(array $sourceElements)
    {
        // Get the source element IDs
        $sourceElementIds = [];

        foreach ($sourceElements as $sourceElement) {
            $sourceElementIds[] = $sourceElement->id;
        }

        // Return any relation data on these elements, defined with this field
        $map = (new Query())
            ->select(['ownerId as source', 'id as target'])
            ->from(['{{%supertableblocks}}'])
            ->where([
                'fieldId' => $this->id,
                'ownerId' => $sourceElementIds,
            ])
            ->orderBy(['sortOrder' => SORT_ASC])
            ->all();

        return [
            'elementType' => SuperTableBlockElement::class,
            'map' => $map,
            'criteria' => ['fieldId' => $this->id]
        ];
    }

     /**
     * @inheritdoc
     */
    public function getContentGqlType()
    {
        $typeArray = SuperTableBlockTypeGenerator::generateTypes($this);
        $typeName = $this->handle . '_SuperTableField';
        $resolver = function (SuperTableBlockElement $value) {
            return $value->getGqlTypeName();
        };
        
        return [
            'name' => $this->handle,
            'type' => Type::listOf(GqlHelper::getUnionType($typeName, $typeArray, $resolver)),
            'args' => SuperTableBlockArguments::getArguments(),
            'resolve' => SuperTableBlockResolver::class . '::resolve',
        ];
    }

    /**
     * @inheritdoc
     * @throws InvalidArgumentException
     */
    public function getGqlFragmentEntityByName(string $fragmentName): GqlInlineFragmentInterface
    {
        if (!preg_match('/^(?P<fieldHandle>[\w]+)_BlockType$/i', $fragmentName, $matches)) {
            throw new InvalidArgumentException('Invalid fragment name: ' . $fragmentName);
        }

        if ($this->handle !== $matches['fieldHandle']) {
            throw new InvalidArgumentException('Invalid fragment name: ' . $fragmentName);
        }

        return $this->getBlockTypes()[0];
    }

    // Events
    // -------------------------------------------------------------------------

    /**
     * @inheritdoc
     */
    public function beforeSave(bool $isNew): bool
    {
        if (!parent::beforeSave($isNew)) {
            return false;
        }

        // Prep the block types & fields for save
        $fieldsService = Craft::$app->getFields();

        foreach ($this->getBlockTypes() as $blockType) {
            // Ensure the block type has a UID
            if ($blockType->getIsNew()) {
                $blockType->uid = StringHelper::UUID();
            } else if (!$blockType->uid) {
                $blockType->uid = Db::uidById('{{%supertableblocktypes}}', $blockType->id);
            }

            foreach ($blockType->getFields() as $field) {
                $field->context = 'superTableBlockType:' . $blockType->uid;
                $fieldsService->prepFieldForSave($field);

                if (!$field->beforeSave($field->getIsNew())) {
                    return false;
                }
            }
        }

        // Set the content table name and remember the original propagation method
        if ($this->id) {
            $oldField = $fieldsService->getFieldById($this->id);

            if ($oldField instanceof self) {
                $this->contentTable = $oldField->contentTable;
                $this->_oldPropagationMethod = $oldField->propagationMethod;
            }
        }

        $this->contentTable = SuperTable::$plugin->getService()->defineContentTableName($this);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function afterSave(bool $isNew)
    {
        SuperTable::$plugin->getService()->saveSettings($this, false);

        // If the propagation method just changed, resave all the SuperTable blocks
        if ($this->_oldPropagationMethod && $this->propagationMethod !== $this->_oldPropagationMethod) {
            Craft::$app->getQueue()->push(new ResaveElements([
                'elementType' => SuperTableBlockElement::class,
                'criteria' => [
                    'fieldId' => $this->id,
                    'siteId' => '*',
                    'unique' => true,
                    'status' => null,
                    'enabledForSite' => false,
                ]
            ]));

            $this->_oldPropagationMethod = null;
        }

        parent::afterSave($isNew);
    }

    /**
     * @inheritdoc
     */
    public function beforeApplyDelete()
    {
        SuperTable::$plugin->getService()->deleteSuperTableField($this);

        parent::beforeApplyDelete();
    }

    /**
     * @inheritdoc
     */
    public function afterElementPropagate(ElementInterface $element, bool $isNew)
    {
        /** @var Element $element */
        if ($element->duplicateOf !== null) {
            SuperTable::$plugin->getService()->duplicateBlocks($this, $element->duplicateOf, $element, true);
        } else {
            SuperTable::$plugin->getService()->saveField($this, $element);
        }

        // Reset the field value if this is a new element
        if ($element->duplicateOf || $isNew) {
            $element->setFieldValue($this->handle, null);
        }

        parent::afterElementPropagate($element, $isNew);
    }

    /**
     * @inheritdoc
     */
    public function beforeElementDelete(ElementInterface $element): bool
    {
        if (!parent::beforeElementDelete($element)) {
            return false;
        }

        // Delete any SuperTable blocks that belong to this element(s)
        foreach (Craft::$app->getSites()->getAllSiteIds() as $siteId) {
            $supertableBlocksQuery = SuperTableBlockElement::find();
            $supertableBlocksQuery->anyStatus();
            $supertableBlocksQuery->siteId($siteId);
            $supertableBlocksQuery->ownerId($element->id);

            /** @var SuperTableBlockElement[] $supertableBlocks */
            $supertableBlocks = $supertableBlocksQuery->all();
            $elementsService = Craft::$app->getElements();

            foreach ($supertableBlocks as $supertableBlock) {
                $supertableBlock->deletedWithOwner = true;
                $elementsService->deleteElement($supertableBlock, $element->hardDelete);
            }
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function afterElementRestore(ElementInterface $element)
    {
        // Also restore any Super Table blocks for this element
        $elementsService = Craft::$app->getElements();

        foreach (ElementHelper::supportedSitesForElement($element) as $siteInfo) {
            $blocks = SuperTableBlockElement::find()
                ->anyStatus()
                ->siteId($siteInfo['siteId'])
                ->ownerId($element->id)
                ->trashed()
                ->andWhere(['supertableblocks.deletedWithOwner' => true])
                ->all();

            foreach ($blocks as $block) {
                $elementsService->restoreElement($block);
            }
        }

        parent::afterElementRestore($element);
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
        $namespace = Craft::$app->getView()->namespaceInputName('blockTypes[__BLOCK_TYPE_ST__][fields][__FIELD_ST__][typesettings]', $originalNamespace);
        Craft::$app->getView()->setNamespace($namespace);

        foreach (Craft::$app->getFields()->getAllFieldTypes() as $class) {
            /** @var Field|string $class */
            // No SuperTable-Inception, sorry buddy.
            if ($class === self::class) {
                continue;
            }

            Craft::$app->getView()->startJsBuffer();

            /** @var FieldInterface $field */
            $field = new $class();

            // A Matrix field will fetch all available fields, grabbing their Settings HTML. Then Super Table will do the same,
            // causing an infinite loop - extract some methods from MatrixFieldType
            if ($class === Matrix::class) {
                $settingsBodyHtml = Craft::$app->getView()->namespaceInputs((string)SuperTable::$plugin->matrixService->getMatrixSettingsHtml($field));
            } else {
                $settingsBodyHtml = Craft::$app->getView()->namespaceInputs((string)$field->getSettingsHtml());
            }

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

    /**
     * Returns info about each block type and their field types for the Super Table field input.
     *
     * @param ElementInterface|null $element
     *
     * @return array
     */
    private function _getBlockTypeInfoForInput(ElementInterface $element = null): array
    {
        $settings = $this->getSettings();

        $blockTypes = [];

        // Set a temporary namespace for these
        $originalNamespace = Craft::$app->getView()->getNamespace();
        $namespace = Craft::$app->getView()->namespaceInputName($this->handle . '[__BLOCK_ST__][fields]', $originalNamespace);
        Craft::$app->getView()->setNamespace($namespace);

        foreach ($this->getBlockTypes() as $blockType) {
            // Create a fake SuperTableBlockElement so the field types have a way to get at the owner element, if there is one
            $block = new SuperTableBlockElement();
            $block->fieldId = $this->id;
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

            $bodyHtml = Craft::$app->getView()->namespaceInputs(Craft::$app->getView()->renderTemplate('super-table/fields', [
                'namespace' => null,
                'fields' => $fieldLayoutFields,
                'element' => $block,
                'settings' => $settings,
                'staticField' => $this->staticField,
            ]));

            // Reset $_isFresh's
            foreach ($fieldLayoutFields as $field) {
                $field->setIsFresh(null);
            }

            $footHtml = Craft::$app->getView()->clearJsBuffer();

            $blockTypes[] = [
                'type' => $blockType->id,
                'bodyHtml' => $bodyHtml,
                'footHtml' => $footHtml,
            ];
        }

        Craft::$app->getView()->setNamespace($originalNamespace);

        return $blockTypes;
    }

    /**
     * Creates an array of blocks based on the given serialized data.
     *
     * @param array|string          $value   The raw field value
     * @param ElementInterface|null $element The element the field is associated with, if there is one
     *
     * @return SuperTableBlockElement[]
     */
    private function _createBlocksFromSerializedData($value, ElementInterface $element = null): array
    {
        if (!is_array($value)) {
            return [];
        }

        /** @var Element $element */
        // Get the possible block types for this field
        /** @var SuperTableBlockType[] $blockTypes */
        $blockTypes = ArrayHelper::index(SuperTable::$plugin->getService()->getBlockTypesByFieldId($this->id), 'id');

        $oldBlocksById = [];

        // Get the old blocks that are still around
        if ($element && $element->id) {
            $ownerId = $element->id;

            $ids = [];

            foreach (array_keys($value) as $blockId) {
                if (is_numeric($blockId) && $blockId != 0) {
                    $ids[] = $blockId;

                    // If that block was duplicated earlier in this request, check for that as well.
                    if (isset(Elements::$duplicatedElementIds[$blockId])) {
                        $ids[] = Elements::$duplicatedElementIds[$blockId];
                    }
                }
            }

            if (!empty($ids)) {
                $oldBlocksQuery = SuperTableBlockElement::find();
                $oldBlocksQuery->fieldId($this->id);
                $oldBlocksQuery->ownerId($ownerId);
                $oldBlocksQuery->id($ids);
                $oldBlocksQuery->anyStatus();
                $oldBlocksQuery->siteId($element->siteId);
                $oldBlocksQuery->indexBy('id');
                $oldBlocksById = $oldBlocksQuery->all();
            }
        } else {
            $ownerId = null;
        }

        $isLivePreview = Craft::$app->getRequest()->getIsLivePreview();
        $blocks = [];
        $sortOrder = 0;
        $prevBlock = null;

        foreach ($value as $blockId => $blockData) {
            if (!isset($blockData['type']) || !isset($blockTypes[$blockData['type']])) {
                continue;
            }

            $blockType = $blockTypes[$blockData['type']];

            // If this is a preexisting block but we don't have a record of it,
            // check to see if it was recently duplicated.
            if (
                strpos($blockId, 'new') !== 0 &&
                !isset($oldBlocksById[$blockId]) &&
                isset(Elements::$duplicatedElementIds[$blockId])
            ) {
                $blockId = Elements::$duplicatedElementIds[$blockId];
            }

            // Is this new? (Or has it been deleted?)
            if (strpos($blockId, 'new') === 0 || !isset($oldBlocksById[$blockId])) {
                $block = new SuperTableBlockElement();
                $block->fieldId = $this->id;
                $block->typeId = $blockType->id;
                $block->ownerId = $ownerId;
                $block->siteId = $element->siteId;
            } else {
                $block = $oldBlocksById[$blockId];
            }

            $block->setOwner($element);

            // Set the content post location on the block if we can
            $fieldNamespace = $element->getFieldParamNamespace();

            if ($fieldNamespace !== null) {
                $blockFieldNamespace = ($fieldNamespace ? $fieldNamespace . '.' : '') . $this->handle . '.' . $blockId . '.fields';
                $block->setFieldParamNamespace($blockFieldNamespace);
            }

            if (isset($blockData['fields'])) {
                foreach ($blockData['fields'] as $fieldHandle => $fieldValue) {
                    try {
                        $block->setFieldValue($fieldHandle, $fieldValue);
                    } catch (UnknownPropertyException $e) {
                        // the field was probably deleted
                    }
                }
            }
            
            $sortOrder++;
            $block->sortOrder = $sortOrder;

            // Set the prev/next blocks
            if ($prevBlock) {
                /** @var ElementInterface $prevBlock */
                $prevBlock->setNext($block);
                /** @var ElementInterface $block */
                $block->setPrev($prevBlock);
            }
            $prevBlock = $block;

            $blocks[] = $block;
        }

        return $blocks;
    }
}
