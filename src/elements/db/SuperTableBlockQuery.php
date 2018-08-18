<?php
namespace verbb\supertable\elements\db;

use verbb\supertable\SuperTable;
use verbb\supertable\elements\SuperTableBlockElement;
use verbb\supertable\fields\SuperTableField;
use verbb\supertable\models\SuperTableBlockTypeModel;

use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\db\Query;
use craft\elements\db\ElementQuery;
use craft\helpers\Db;
use craft\models\Site;

use yii\base\Exception;
use yii\db\Connection;

class SuperTableBlockQuery extends ElementQuery
{
    // Properties
    // =========================================================================

    /**
     * @var int|int[]|string|false|null The field ID(s) that the resulting SuperTable blocks must belong to.
     */
    public $fieldId;

    /**
     * @var int|int[]|null The owner element ID(s) that the resulting SuperTable blocks must belong to.
     */
    public $ownerId;

    /**
     * @var int|string|null The site ID that the resulting SuperTable blocks must have been defined in, or ':empty:' to find blocks without an owner site ID.
     */
    public $ownerSiteId;

    /**
     * @var int|int[]|null The block type ID(s) that the resulting SuperTable blocks must have.
     */
    public $typeId;
    

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function __construct($elementType, array $config = [])
    {
        // Default orderBy
        if (!isset($config['orderBy'])) {
            $config['orderBy'] = 'supertableblocks.sortOrder';
        }

        parent::__construct($elementType, $config);
    }

    /**
     * @inheritdoc
     */
    public function __set($name, $value)
    {
        switch ($name) {
            case 'ownerSite':
                $this->ownerSite($value);
                break;
            case 'type':
                $this->type($value);
                break;
            case 'ownerLocale':
                Craft::$app->getDeprecator()->log('SuperTableBlockQuery::ownerLocale()', 'The “ownerLocale” SuperTable block query param has been deprecated. Use “ownerSite” or “ownerSiteId” instead.');
                $this->ownerSite($value);
                break;
            default:
                parent::__set($name, $value);
        }
    }

    /**
     * @inheritdoc
     */
    public function __get($name)
    {
        // Handle querying via the direct field handles for a Static Super Table field - `{{ superTable.customField }}`
        if (is_string($name)) {
            return $this->one()->$name ?? null;
        }

        return parent::__get($name);
    }

    /**
     * Sets the [[fieldId]] property.
     *
     * @param int|int[]|null $value The property value
     *
     * @return static self reference
     */
    public function fieldId($value)
    {
        $this->fieldId = $value;

        return $this;
    }

    /**
     * Sets the [[ownerId]] property.
     *
     * @param int|int[]|null $value The property value
     *
     * @return static self reference
     */
    public function ownerId($value)
    {
        $this->ownerId = $value;

        return $this;
    }

    /**
     * Sets the [[ownerSiteId]] and [[siteId]] properties.
     *
     * @param int|string|null $value The property value
     *
     * @return static self reference
     */
    public function ownerSiteId($value)
    {
        $this->ownerSiteId = $value;

        if ($value && strtolower($value) !== ':empty:') {
            // A block will never exist in a site that is different than its ownerSiteId,
            // so let's set the siteId param here too.
            $this->siteId = (int)$value;
        }

        return $this;
    }

    /**
     * Sets the [[ownerSiteId]] property based on a given site(s)’s handle(s).
     *
     * @param string|string[]|Site $value The property value
     *
     * @return static self reference
     * @throws Exception if $value is an invalid site handle
     */
    public function ownerSite($value)
    {
        if ($value instanceof Site) {
            $this->ownerSiteId($value->id);
        } else {
            $site = Craft::$app->getSites()->getSiteByHandle($value);

            if (!$site) {
                throw new Exception('Invalid site hadle: '.$value);
            }

            $this->ownerSiteId($site->id);
        }

        return $this;
    }

    /**
     * Sets the [[ownerLocale]] property.
     *
     * @param string|string[] $value The property value
     *
     * @return static self reference
     * @deprecated in 3.0. Use [[ownerSiteId()]] instead.
     */
    public function ownerLocale($value)
    {
        Craft::$app->getDeprecator()->log('ElementQuery::ownerLocale()', 'The “ownerLocale” SuperTable block query param has been deprecated. Use “site” or “siteId” instead.');
        $this->ownerSite($value);

        return $this;
    }

    /**
     * Sets the [[ownerId]] and [[ownerSiteId]] properties based on a given element.
     *
     * @param ElementInterface $owner The owner element
     *
     * @return static self reference
     */
    public function owner(ElementInterface $owner)
    {
        /** @var Element $owner */
        $this->ownerId = $owner->id;
        $this->siteId = $owner->siteId;

        return $this;
    }

    /**
     * Sets the [[typeId]] property based on a given block type(s)’s handle(s).
     *
     * @param string|string[]|SuperTableBlockType|null $value The property value
     *
     * @return static self reference
     */
    public function type($value)
    {
        if ($value instanceof SuperTableBlockType) {
            $this->typeId = $value->id;
        } else if ($value !== null) {
            $this->typeId = (new Query())
                ->select(['id'])
                ->from(['{{%supertableblocktypes}}'])
                ->where(Db::parseParam('id', $value))
                ->column();
        } else {
            $this->typeId = null;
        }

        return $this;
    }

    /**
     * Sets the [[typeId]] property.
     *
     * @param int|int[]|null $value The property value
     *
     * @return static self reference
     */
    public function typeId($value)
    {
        $this->typeId = $value;

        return $this;
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function beforePrepare(): bool
    {
        $this->joinElementTable('supertableblocks');

        // Figure out which content table to use
        $this->contentTable = null;

        if (!$this->fieldId && $this->id && is_numeric($this->id)) {
            $this->fieldId = (new Query())
                ->select(['fieldId'])
                ->from(['{{%supertableblocks}}'])
                ->where(['id' => $this->id])
                ->scalar();
        }

        if ($this->fieldId && is_numeric($this->fieldId)) {
            /** @var SuperTableField $supertableField */
            $supertableField = Craft::$app->getFields()->getFieldById($this->fieldId);

            if ($supertableField) {
                $this->contentTable = SuperTable::$plugin->service->getContentTableName($supertableField);
            }
        }

        $this->query->select([
            'supertableblocks.fieldId',
            'supertableblocks.ownerId',
            'supertableblocks.ownerSiteId',
            'supertableblocks.typeId',
            'supertableblocks.sortOrder',
        ]);

        if ($this->fieldId) {
            $this->subQuery->andWhere(Db::parseParam('supertableblocks.fieldId', $this->fieldId));
        }

        if ($this->ownerId) {
            $this->subQuery->andWhere(Db::parseParam('supertableblocks.ownerId', $this->ownerId));
        }

        if ($this->ownerSiteId) {
            $this->subQuery->andWhere(Db::parseParam('supertableblocks.ownerSiteId', $this->ownerSiteId));
        }

        if ($this->typeId !== null) {
            // If typeId is an empty array, it's because type() was called but no valid type handles were passed in
            if (empty($this->typeId)) {
                return false;
            }

            $this->subQuery->andWhere(Db::parseParam('supertableblocks.typeId', $this->typeId));
        }

        return parent::beforePrepare();
    }

    /**
     * @inheritdoc
     */
    protected function customFields(): array
    {
        // This method won't get called if $this->fieldId isn't set to a single int
        /** @var SuperTableField $supertableField */
        $supertableField = Craft::$app->getFields()->getFieldById($this->fieldId);
        return $supertableField->getBlockTypeFields();
    }
}
