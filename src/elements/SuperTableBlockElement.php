<?php
namespace verbb\supertable\elements;

use verbb\supertable\SuperTable;
use verbb\supertable\elements\db\SuperTableBlockQuery;
use verbb\supertable\fields\SuperTableField;
use verbb\supertable\models\SuperTableBlockTypeModel;
use verbb\supertable\records\SuperTableBlockRecord;
use verbb\supertable\assetbundles\SuperTableAsset;

use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\ArrayHelper;
use craft\helpers\ElementHelper;
use craft\validators\SiteIdValidator;
use yii\base\Exception;
use yii\base\InvalidConfigException;

class SuperTableBlockElement extends Element
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
        $supertableFieldId = $sourceElements[0]->fieldId;
        $blockTypes = SuperTable::$plugin->service->getBlockTypesByFieldId($supertableFieldId);

        if (!isset($blockTypes[0])) {
            // Not a valid block type handle (assuming all $sourceElements are blocks from the same SuperTable field)
            return false;
        }

        $blockType = $blockTypes[0];

        // Set the field context
        $contentService = Craft::$app->getContent();
        $originalFieldContext = $contentService->fieldContext;
        $contentService->fieldContext = 'superTableBlockType:' . $blockType->id;

        $map = parent::eagerLoadingMap($sourceElements, $handle);

        $contentService->fieldContext = $originalFieldContext;

        return $map;
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
        $rules[] = [['ownerSiteId'], SiteIdValidator::class];

        return $rules;
    }

    /**
     * @inheritdoc
     */
    public function getSupportedSites(): array
    {
        // If the SuperTable field is translatable, than each individual block is tied to a single site, and thus aren't
        // translatable. Otherwise all blocks belong to all sites, and their content is translatable.

        if ($this->ownerSiteId !== null) {
            return [$this->ownerSiteId];
        }

        $owner = $this->getOwner();

        if ($owner) {
            // Just send back an array of site IDs -- don't pass along enabledByDefault configs
            $siteIds = [];

            foreach (ElementHelper::supportedSitesForElement($owner) as $siteInfo) {
                $siteIds[] = $siteInfo['siteId'];
            }

            return $siteIds;
        }

        return [Craft::$app->getSites()->getPrimarySite()->id];
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

        $blockType = SuperTable::$plugin->service->getBlockTypeById($this->typeId);

        if (!$blockType) {
            throw new InvalidConfigException('Invalid SuperTable block ID: '.$this->typeId);
        }

        return $blockType;
    }

    /**
     * @inheritdoc
     */
    public function getOwner()
    {
        if ($this->_owner !== null) {
            return $this->_owner !== false ? $this->_owner : null;
        }

        if ($this->ownerId === null) {
            return null;
        }

        if (($this->_owner = Craft::$app->getElements()->getElementById($this->ownerId, null, $this->siteId)) === null) {
            // Be forgiving of invalid ownerId's in this case, since the field
            // could be in the process of being saved to a new element/site
            $this->_owner = false;

            return null;
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
        return SuperTable::$plugin->service->getContentTableName($this->_getField());
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
        return 'superTableBlockType:' . $this->typeId;
    }

    /**
     * @inheritdoc
     */
    public function getHasFreshContent(): bool
    {
        // Defer to the owner element
        $owner = $this->getOwner();

        return $owner ? $owner->getHasFreshContent() : false;
    }


    // Events
    // -------------------------------------------------------------------------

    /**
     * @inheritdoc
     * @throws Exception if reasons
     */
    public function afterSave(bool $isNew)
    {
        // Get the block record
        if (!$isNew) {
            $record = SuperTableBlockRecord::findOne($this->id);

            if (!$record) {
                throw new Exception('Invalid SuperTable block ID: ' . $this->id);
            }
        } else {
            $record = new SuperTableBlockRecord();
            $record->id = $this->id;
        }

        $record->fieldId = $this->fieldId;
        $record->ownerId = $this->ownerId;
        $record->ownerSiteId = $this->ownerSiteId;
        $record->typeId = $this->typeId;
        $record->sortOrder = $this->sortOrder;
        $record->save(false);

        parent::afterSave($isNew);
    }


    // Private Methods
    // =========================================================================

    /**
     * Returns the SuperTable field.
     *
     * @return SuperTable
     */
    private function _getField()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return Craft::$app->getFields()->getFieldById($this->fieldId);
    }
}
