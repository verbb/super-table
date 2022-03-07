<?php
namespace verbb\supertable\elements;

use verbb\supertable\SuperTable;
use verbb\supertable\elements\db\SuperTableBlockQuery;
use verbb\supertable\fields\SuperTableField;
use verbb\supertable\models\SuperTableBlockTypeModel;
use verbb\supertable\records\SuperTableBlockRecord;
use verbb\supertable\assetbundles\SuperTableAsset;

use Craft;
use craft\base\BlockElementInterface;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\db\Table;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use craft\helpers\ElementHelper;
use craft\models\Section;
use craft\models\Site;
use craft\validators\SiteIdValidator;

use yii\base\Exception;
use yii\base\InvalidConfigException;

class SuperTableBlockElement extends Element implements BlockElementInterface
{
    // Static Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('super-table', 'SuperTable Block');
    }

    /**
     * @inheritdoc
     */
    public static function lowerDisplayName(): string
    {
        return Craft::t('super-table', 'SuperTable block');
    }

    /**
     * @inheritdoc
     */
    public static function pluralDisplayName(): string
    {
        return Craft::t('super-table', 'SuperTable Blocks');
    }

    /**
     * @inheritdoc
     */
    public static function pluralLowerDisplayName(): string
    {
        return Craft::t('super-table', 'SuperTable blocks');
    }

    /**
     * @inheritdoc
     */
    public static function refHandle(): ?string
    {
        return 'supertableblock';
    }

    /**
     * @inheritdoc
     */
    public static function trackChanges(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function hasContent(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function isLocalized(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function hasStatuses(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     * @return SuperTableBlockQuery The newly created [[SuperTableBlockQuery]] instance.
     */
    public static function find(): SuperTableBlockQuery
    {
        return new SuperTableBlockQuery(static::class);
    }

    /**
     * @inheritdoc
     */
    public static function eagerLoadingMap(array $sourceElements, string $handle): array|null|false
    {
        // Get the block type
        $supertableFieldId = ArrayHelper::firstValue($sourceElements)->fieldId;
        $blockTypes = SuperTable::$plugin->getService()->getBlockTypesByFieldId($supertableFieldId);

        if (!isset($blockTypes[0])) {
            // Not a valid block type handle (assuming all $sourceElements are blocks from the same SuperTable field)
            return false;
        }

        $blockType = $blockTypes[0];

        // Set the field context
        $contentService = Craft::$app->getContent();
        $originalFieldContext = $contentService->fieldContext;
        $contentService->fieldContext = 'superTableBlockType:' . $blockType->uid;

        $map = parent::eagerLoadingMap($sourceElements, $handle);

        $contentService->fieldContext = $originalFieldContext;

        return $map;
    }

    /**
     * @inheritdoc
     */
    public static function gqlTypeNameByContext(mixed $context): string
    {
        return $context->getField()->handle . '_BlockType';
    }


    // Properties
    // =========================================================================

    /**
     * @var int|null Field ID
     */
    public $fieldId;

    /**
     * @var int|null Owner ID
     */
    public $ownerId;

    /**
     * @var int|null Owner site ID
     * @deprecated in 2.2.0. Use [[$siteId]] instead.
     */
    public $ownerSiteId;

    /**
     * @var int|null Type ID
     */
    public $typeId;

    /**
     * @var int|null Sort order
     */
    public $sortOrder;

    /**
     * @var bool Whether the block has changed.
     * @internal
     * @since 2.4.0
     */
    public $dirty = false;

    /**
     * @var bool Collapsed
     */
    public $collapsed = false;

    /**
     * @var bool Whether the block was deleted along with its owner
     * @see beforeDelete()
     */
    public $deletedWithOwner = false;

    /**
     * @var ElementInterface|false|null The owner element, or false if [[ownerId]] is invalid
     */
    private \craft\base\ElementInterface|false|null $_owner = null;

    /**
     * @var ElementInterface[]|null
     */
    private ?array $_eagerLoadedBlockTypeElements = null;


    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function attributes(): array
    {
        $names = parent::attributes();
        $names[] = 'owner';
        return $names;
    }

    /**
     * @inheritdoc
     */
    public function extraFields(): array
    {
        $names = parent::extraFields();
        $names[] = 'owner';
        $names[] = 'type';
        return $names;
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['fieldId', 'ownerId', 'typeId', 'sortOrder'], 'number', 'integerOnly' => true];
        return $rules;
    }

    /**
     * @inheritdoc
     */
    public function getSupportedSites(): array
    {
        try {
            $owner = $this->getOwner();
        } catch (InvalidConfigException) {
            $owner = $this->duplicateOf;
        }

        if (!$owner) {
            return [Craft::$app->getSites()->getPrimarySite()->id];
        }

        $field = $this->_field();
        return SuperTable::$plugin->getService()->getSupportedSiteIds($this->_field()->propagationMethod, $owner);
    }

    /**
     * @inheritdoc
     */
    public function getCacheTags(): array
    {
        return [
            "field-owner:$this->fieldId-$this->ownerId",
            "field:$this->fieldId",
            "owner:$this->ownerId",
        ];
    }

    /**
     * @inheritdoc
     */
    public function getFieldLayout(): ?\craft\models\FieldLayout
    {
        return parent::getFieldLayout() ?? $this->getType()->getFieldLayout();
    }

    /**
     * Returns the block type.
     *
     * @throws InvalidConfigException if [[typeId]] is missing or invalid
     */
    public function getType(): \SuperTableBlockType
    {
        if ($this->typeId === null) {
            throw new InvalidConfigException('SuperTable block is missing its type ID');
        }

        $blockType = SuperTable::$plugin->getService()->getBlockTypeById($this->typeId);

        if (!$blockType) {
            throw new InvalidConfigException('Invalid SuperTable block type ID: ' . $this->typeId);
        }

        return $blockType;
    }

    /** @inheritdoc */
    public function getOwner(): ?\craft\base\ElementInterface
    {
        if ($this->_owner === null) {
            if ($this->ownerId === null) {
                throw new InvalidConfigException('SuperTable block is missing its owner ID');
            }

            if (($this->_owner = Craft::$app->getElements()->getElementById($this->ownerId, null, $this->siteId)) === null) {
                throw new InvalidConfigException('Invalid owner ID: ' . $this->ownerId);
            }
        }
        return $this->_owner;
    }

    /**
     * Sets the owner
     *
     * @param ElementInterface|null $owner
     */
    public function setOwner(ElementInterface $owner = null): void
    {
        $this->_owner = $owner;
    }

    /**
     * @inheritdoc
     */
    public function getContentTable(): string
    {
        return $this->_field()->contentTable;
    }

    /**
     * @inheritdoc
     */
    public function getFieldColumnPrefix(): string
    {
        return 'field_';
    }

    /**
     * Returns the field context this element's content uses.
     */
    public function getFieldContext(): string
    {
        return 'superTableBlockType:' . $this->getType()->uid;
    }

    /**
     * @inheritdoc
     */
    public function getGqlTypeName(): string
    {
        return static::gqlTypeNameByContext($this->getType());
    }


    // Events
    // -------------------------------------------------------------------------

    /**
     * @inheritdoc
     * @throws Exception if reasons
     */
    public function afterSave(bool $isNew): void
    {
        if (!$this->propagating) {
            // Get the block record
            if (!$isNew) {
                $record = SuperTableBlockRecord::findOne($this->id);

                if (!$record) {
                    throw new Exception('Invalid SuperTable block ID: ' . $this->id);
                }
            } else {
                $record = new SuperTableBlockRecord();
                $record->id = (int)$this->id;
            }

            $record->fieldId = (int)$this->fieldId;
            $record->ownerId = (int)$this->ownerId;
            $record->typeId = (int)$this->typeId;
            $record->sortOrder = (int)$this->sortOrder ?: null;
            $record->save(false);
        }

        parent::afterSave($isNew);
    }

    /**
     * @inheritdoc
     */
    public function beforeDelete(): bool
    {
        if (!parent::beforeDelete()) {
            return false;
        }

        // Update the block record
        Db::update('{{%supertableblocks}}', [
            'deletedWithOwner' => $this->deletedWithOwner,
        ], [
            'id' => $this->id,
        ], [], false);

        return true;
    }


    // Private Methods
    // =========================================================================
    /**
     * Returns the SuperTable field.
     */
    private function _field(): \verbb\supertable\SuperTable
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return Craft::$app->getFields()->getFieldById($this->fieldId);
    }
}
