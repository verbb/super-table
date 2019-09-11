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
use craft\helpers\ElementHelper;
use craft\models\Section;
use craft\models\Site;
use craft\validators\SiteIdValidator;

use yii\base\Exception;
use yii\base\InvalidConfigException;

class SuperTableBlockElement extends Element implements BlockElementInterface
{
    // Static
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
    public static function pluralDisplayName(): string
    {
        return Craft::t('super-table', 'SuperTable Blocks');
    }

    /**
     * @inheritdoc
     */
    public static function refHandle()
    {
        return 'supertableblock';
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
     */
    public static function find(): ElementQueryInterface
    {
        return new SuperTableBlockQuery(static::class);
    }

    /**
     * @inheritdoc
     *
     * @return SuperTableBlockQuery The newly created [[SuperTableBlockQuery]] instance.
     */
    public static function eagerLoadingMap(array $sourceElements, string $handle)
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
    public static function gqlTypeNameByContext($context): string
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
    private $_owner;

    /**
     * @var ElementInterface[]|null
     */
    private $_eagerLoadedBlockTypeElements;


    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function attributes()
    {
        $names = parent::attributes();
        $names[] = 'owner';
        return $names;
    }

    /**
     * @inheritdoc
     */
    public function extraFields()
    {
        $names = parent::extraFields();
        $names[] = 'owner';
        $names[] = 'type';
        
        return $names;
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        $rules = parent::rules();
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
        } catch (InvalidConfigException $e) {
            $owner = $this->duplicateOf;
        }

        if (!$owner) {
            return [Craft::$app->getSites()->getPrimarySite()->id];
        }

        return SuperTable::$plugin->getService()->getSupportedSiteIdsForField($this->_field(), $owner);
    }

    /**
     * @inheritdoc
     */
    public function getFieldLayout()
    {
        return parent::getFieldLayout() ?? $this->getType()->getFieldLayout();
    }

    /**
     * @inheritdoc
     */
    public function getType()
    {
        if ($this->typeId === null) {
            throw new InvalidConfigException('SuperTable block is missing its type ID');
        }

        $blockType = SuperTable::$plugin->getService()->getBlockTypeById($this->typeId);

        if (!$blockType) {
            throw new InvalidConfigException('Invalid SuperTable block ID: ' . $this->typeId);
        }

        return $blockType;
    }

    /**
     * @inheritdoc
     */
    public function getOwner(): ElementInterface
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
     * @inheritdoc
     */
    public function setOwner(ElementInterface $owner = null)
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
     * @inheritdoc
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
    public function afterSave(bool $isNew)
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
        Craft::$app->getDb()->createCommand()
            ->update('{{%supertableblocks}}', [
                'deletedWithOwner' => $this->deletedWithOwner,
            ], ['id' => $this->id], [], false)
            ->execute();

        return true;
    }


    // Private Methods
    // =========================================================================

    /**
     * Returns the SuperTable field.
     *
     * @return SuperTable
     */
    private function _field(): SuperTableField
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return Craft::$app->getFields()->getFieldById($this->fieldId);
    }
}
