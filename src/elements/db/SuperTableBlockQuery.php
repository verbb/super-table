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
use craft\db\Table;
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
     * @inheritdoc
     */
    protected $defaultOrderBy = ['supertableblocks.sortOrder' => SORT_ASC];
    
    // General parameters
    // -------------------------------------------------------------------------

    /**
     * @var int|int[]|string|false|null The field ID(s) that the resulting SuperTable blocks must belong to.
     */
    public $fieldId;

    /**
     * @var int|int[]|null The owner element ID(s) that the resulting SuperTable blocks must belong to.
     */
    public $ownerId;

    /**
     * @var mixed
     * @deprecated in 2.2.0
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
    public function __set($name, $value)
    {
        switch ($name) {
            case 'ownerSite':
                Craft::$app->getDeprecator()->log('SuperTableBlockQuery::ownerSite()', 'The “ownerSite” SuperTable block query param has been deprecated. Use “site” or “siteId” instead.');
                break;
            case 'type':
                $this->type($value);
                break;
            case 'ownerLocale':
                Craft::$app->getDeprecator()->log('SuperTableBlockQuery::ownerLocale()', 'The “ownerLocale” SuperTable block query param has been deprecated. Use “site” or “siteId” instead.');
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
     * @inheritdoc
     */
    public function __call($name, $params)
    {
        // Handle calling methods via a Static Super Table field - `{{ superTable.getFieldLayout().fields }}`
        if (is_string($name)) {
            $block = $this->one() ?? null;

            if ($block && method_exists($block, $name)) {
                return $block->$name($params) ?? null;
            }
        }

        return parent::__call($name, $params);
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
     * @return static self reference
     * @deprecated in 2.2.0.
     */
    public function ownerSiteId()
    {
        Craft::$app->getDeprecator()->log('SuperTableBlockQuery::ownerSiteId()', 'The “ownerSiteId” SuperTable block query param has been deprecated. Use “site” or “siteId” instead.');
        return $this;
    }

    /**
     * @return static self reference
     * @deprecated in 2.2.0.
     */
    public function ownerSite()
    {
        Craft::$app->getDeprecator()->log('SuperTableBlockQuery::ownerSite()', 'The “ownerSite” SuperTable block query param has been deprecated. Use “site” or “siteId” instead.');
        return $this;
    }

    /**
     * @return static self reference
     * @deprecated in 2.0.
     */
    public function ownerLocale($value)
    {
        Craft::$app->getDeprecator()->log('SuperTableBlockQuery::ownerLocale()', 'The “ownerLocale” SuperTable block query param has been deprecated. Use “site” or “siteId” instead.');
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
     * Sets the [[typeId]] property based on a given block type(s)’s id(s).
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

        if (!$this->fieldId && $this->id) {
            $fieldIds = (new Query())
                ->select(['fieldId'])
                ->distinct()
                ->from(['{{%supertableblocks}}'])
                ->where(Db::parseParam('id', $this->id))
                ->column();

            $this->fieldId = count($fieldIds) === 1 ? $fieldIds[0] : $fieldIds;
        }

        if ($this->fieldId && is_numeric($this->fieldId)) {
            /** @var SuperTableField $supertableField */
            $supertableField = Craft::$app->getFields()->getFieldById($this->fieldId);

            if ($supertableField) {
                $this->contentTable = $supertableField->contentTable;
            }
        }

        $this->query->select([
            'supertableblocks.fieldId',
            'supertableblocks.ownerId',
            'supertableblocks.typeId',
            'supertableblocks.sortOrder',
        ]);

        if ($this->fieldId) {
            $this->subQuery->andWhere(Db::parseParam('supertableblocks.fieldId', $this->fieldId));
        }

        if ($this->ownerId) {
            $this->subQuery->andWhere(Db::parseParam('supertableblocks.ownerId', $this->ownerId));
        }

        if ($this->typeId !== null) {
            // If typeId is an empty array, it's because type() was called but no valid type handles were passed in
            if (empty($this->typeId)) {
                return false;
            }

            $this->subQuery->andWhere(Db::parseParam('supertableblocks.typeId', $this->typeId));
        }

        // Ignore revision/draft blocks by default
        if (!$this->id && !$this->ownerId) {
            // todo: we will need to expand on this when Super Table blocks can be nested.
            $this->subQuery
                ->innerJoin(Table::ELEMENTS . ' owners', '[[owners.id]] = [[supertableblocks.ownerId]]')
                ->andWhere([
                    'owners.draftId' => null,
                    'owners.revisionId' => null,
                ]);
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
