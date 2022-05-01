<?php
namespace verbb\supertable\elements;

use verbb\supertable\SuperTable;
use verbb\supertable\elements\db\SuperTableBlockQuery;
use verbb\supertable\fields\SuperTableField;
use verbb\supertable\models\SuperTableBlockType;
use verbb\supertable\records\SuperTableBlock as SuperTableBlockRecord;

use Craft;
use craft\base\BlockElementInterface;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use craft\models\FieldLayout;

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
    public ?int $fieldId = null;

    /**
     * @var int|null Primary owner ID
     * @since 3.0.0
     */
    public ?int $primaryOwnerId = null;

    /**
     * @var int|null Owner ID
     */
    public ?int $ownerId = null;

    /**
     * @var int|null Type ID
     */
    public ?int $typeId = null;

    /**
     * @var int|null Sort order
     */
    public ?int $sortOrder = null;

    /**
     * @var bool Whether the block has changed.
     * @internal
     * @since 2.4.0
     */
    public bool $dirty = false;

    /**
     * @var bool Collapsed
     */
    public bool $collapsed = false;

    /**
     * @var bool Whether the block was deleted along with its owner
     * @see beforeDelete()
     */
    public bool $deletedWithOwner = false;

    /**
     * @var bool Whether to save the block’s row in the `supertableblock_owners` table in [[afterSave()]].
     * @since 3.0.0
     */
    public bool $saveOwnership = true;

    /**
     * @var ElementInterface|null The owner element, or false if [[ownerId]] is invalid
     */
    private ?ElementInterface $_owner = null;

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
        $rules[] = [['fieldId', 'primaryOwnerId', 'typeId', 'sortOrder'], 'number', 'integerOnly' => true];
        return $rules;
    }

    /**
     * @inheritdoc
     */
    public function getSupportedSites(): array
    {
        try {
            $owner = $this->getOwner();
        } catch (InvalidConfigException $e) {
            $owner = $this->duplicateOf;
        }

        if (!$owner) {
            return [Craft::$app->getSites()->getPrimarySite()->id];
        }

        $field = $this->_field();
        return SuperTable::$plugin->getService()->getSupportedSiteIds($field->propagationMethod, $owner, $field->propagationKeyFormat);
    }

    /**
     * @inheritdoc
     */
    public function getCacheTags(): array
    {
        return [
            "field-owner:$this->fieldId-$this->primaryOwnerId",
            "field:$this->fieldId",
            "owner:$this->primaryOwnerId",
        ];
    }

    /**
     * @inheritdoc
     */
    public function getFieldLayout(): ?FieldLayout
    {
        return parent::getFieldLayout() ?? $this->getType()->getFieldLayout();
    }

    /**
     * Returns the block type.
     *
     * @throws InvalidConfigException if [[typeId]] is missing or invalid
     */
    public function getType(): SuperTableBlockType
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
    public function getOwner(): ?ElementInterface
    {
        if (!isset($this->_owner)) {
            if (!isset($this->ownerId)) {
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
    public function setOwner(?ElementInterface $owner = null): void
    {
        $this->_owner = $owner;
        $this->ownerId = $owner->id;
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
     */
    public function beforeSave(bool $isNew): bool
    {
        if (!$this->primaryOwnerId && !$this->ownerId) {
            throw new InvalidConfigException('No owner ID assigned to the Super Table block.');
        }

        return parent::beforeSave($isNew);
    }

    /**
     * @inheritdoc
     * @throws Exception if reasons
     */
    public function afterSave(bool $isNew): void
    {
        if (!$this->propagating) {
            $this->primaryOwnerId = $this->primaryOwnerId ?? $this->ownerId;
            $this->ownerId = $this->ownerId ?? $this->primaryOwnerId;

            // Get the block record
            if (!$isNew) {
                $record = SuperTableBlockRecord::findOne($this->id);

                if (!$record) {
                    throw new InvalidConfigException("Invalid SuperTable block ID: $this->id");
                }
            } else {
                $record = new SuperTableBlockRecord();
                $record->id = (int)$this->id;
            }

            $record->fieldId = $this->fieldId;
            $record->primaryOwnerId = $this->primaryOwnerId ?? $this->ownerId;
            $record->typeId = $this->typeId;
            $record->save(false);

            // ownerId will be null when creating a revision
            if ($this->saveOwnership) {
                if ($isNew) {
                    Db::insert('{{%supertableblocks_owners}}', [
                        'blockId' => $this->id,
                        'ownerId' => $this->ownerId,
                        'sortOrder' => $this->sortOrder ?? 0,
                    ]);
                } else {
                    Db::update('{{%supertableblocks_owners}}', [
                        'sortOrder' => $this->sortOrder ?? 0,
                    ], [
                        'blockId' => $this->id,
                        'ownerId' => $this->ownerId,
                    ]);
                }
            }
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
    private function _field(): SuperTableField
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return Craft::$app->getFields()->getFieldById($this->fieldId);
    }
}
