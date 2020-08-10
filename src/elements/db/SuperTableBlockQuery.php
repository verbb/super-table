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
use craft\db\QueryAbortedException;
use craft\db\Table;
use craft\helpers\ArrayHelper;
use craft\elements\db\ElementQuery;
use craft\helpers\Db;
use craft\models\Site;

use yii\base\InvalidConfigException;
use yii\base\Exception;
use yii\db\Connection;

/**
 * @method SuperTableBlockElement[]|array all($db = null)
 * @method SuperTableBlockElement|array|null nth(int $n, Connection $db = null)
 * @method SuperTableBlockElement|array|null one($db = null)
 */
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
     * @var bool|null Whether the owner elements can be drafts.
     * @used-by allowOwnerDrafts()
     * @since 2.4.2
     */
    public $allowOwnerDrafts;

    /**
     * @var bool|null Whether the owner elements can be revisions.
     * @used-by allowOwnerRevisions()
     * @since 2.4.2
     */
    public $allowOwnerRevisions;

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
     * Narrows the query results based on the field the Super Table blocks belong to.
     *
     * @param string|string[]|SuperTableField|null $value The property value
     * @return static self reference
     * @uses $fieldId
     * @since 2.4.1
     */
    public function field($value)
    {
        if ($value instanceof SuperTableField) {
            $this->fieldId = [$value->id];
        } else if (is_string($value) || (is_array($value) && count($value) === 1)) {
            if (!is_string($value)) {
                $value = reset($value);
            }
            $field = Craft::$app->getFields()->getFieldByHandle($value);
            if ($field && $field instanceof SuperTableField) {
                $this->fieldId = [$field->id];
            } else {
                $this->fieldId = false;
            }
        } else if ($value !== null) {
            $this->fieldId = (new Query())
                ->select(['id'])
                ->from([Table::FIELDS])
                ->where(Db::parseParam('handle', $value))
                ->andWhere(['type' => SuperTableField::class])
                ->column();
        } else {
            $this->fieldId = null;
        }

        return $this;
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
        $this->ownerId = [$owner->id];
        $this->siteId = $owner->siteId;

        return $this;
    }

    /**
     * Narrows the query results based on whether the Super Table blocks’ owners are drafts.
     *
     * @param bool|null $value The property value
     * @return static self reference
     * @uses $allowOwnerDrafts
     * @since 2.4.1
     */
    public function allowOwnerDrafts($value = true)
    {
        $this->allowOwnerDrafts = $value;
        return $this;
    }

    /**
     * Narrows the query results based on whether the Super Table blocks’ owners are revisions.
     *
     * @param bool|null $value The property value
     * @return static self reference
     * @uses $allowOwnerDrafts
     * @since 2.4.1
     */
    public function allowOwnerRevisions($value = true)
    {
        $this->allowOwnerRevisions = $value;
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
        $this->_normalizeFieldId();
        $this->joinElementTable('supertableblocks');

        // Figure out which content table to use
        $this->contentTable = null;
        if ($this->fieldId && count($this->fieldId) === 1) {
            /** @var SuperTableField $superTableField */
            $superTableField = Craft::$app->getFields()->getFieldById(reset($this->fieldId));
            
            if ($superTableField) {
                $this->contentTable = $superTableField->contentTable;
            }
        }

        $this->query->select([
            'supertableblocks.fieldId',
            'supertableblocks.ownerId',
            'supertableblocks.typeId',
            'supertableblocks.sortOrder',
        ]);

        if ($this->fieldId) {
            $this->subQuery->andWhere(['supertableblocks.fieldId' => $this->fieldId]);
        }

        $this->_normalizeOwnerId();
        if ($this->ownerId) {
            $this->subQuery->andWhere(['supertableblocks.ownerId' => $this->ownerId]);
        }

        if ($this->typeId !== null) {
            // If typeId is an empty array, it's because type() was called but no valid type handles were passed in
            if (empty($this->typeId)) {
                return false;
            }

            $this->subQuery->andWhere(Db::parseParam('supertableblocks.typeId', $this->typeId));
        }

        // Ignore revision/draft blocks by default
        $allowOwnerDrafts = $this->allowOwnerDrafts ?? ($this->id || $this->ownerId);
        $allowOwnerRevisions = $this->allowOwnerRevisions ?? ($this->id || $this->ownerId);

        if (!$allowOwnerDrafts || !$allowOwnerRevisions) {
            // todo: we will need to expand on this when Super Table blocks can be nested.
            $this->subQuery->innerJoin(['owners' => Table::ELEMENTS], '[[owners.id]] = [[supertableblocks.ownerId]]');

            if (!$allowOwnerDrafts) {
                $this->subQuery->andWhere(['owners.draftId' => null]);
            }

            if (!$allowOwnerRevisions) {
                $this->subQuery->andWhere(['owners.revisionId' => null]);
            }
        }

        return parent::beforePrepare();
    }

    /**
     * Normalizes the fieldId param to an array of IDs or null
     *
     * @throws QueryAbortedException
     */
    private function _normalizeFieldId()
    {
        if ($this->fieldId === null && $this->id) {
            $this->fieldId = (new Query())
                ->select(['fieldId'])
                ->distinct()
                ->from(['{{%supertableblocks}}'])
                ->where(Db::parseParam('id', $this->id))
                ->column() ?: false;
        }

        if ($this->fieldId === false) {
            throw new QueryAbortedException();
        }

        if (empty($this->fieldId)) {
            $this->fieldId = null;
        } else if (is_numeric($this->fieldId)) {
            $this->fieldId = [$this->fieldId];
        } else if (!is_array($this->fieldId) || !ArrayHelper::isNumeric($this->fieldId)) {
            $this->fieldId = (new Query())
                ->select(['id'])
                ->from([Table::FIELDS])
                ->where(Db::parseParam('id', $this->fieldId))
                ->andWhere(['type' => SuperTableField::class])
                ->column();
        }
    }

    /**
     * Normalizes the ownerId param to an array of IDs or null
     *
     * @throws InvalidConfigException
     */
    private function _normalizeOwnerId()
    {
        if (empty($this->ownerId)) {
            $this->ownerId = null;
        } else if (is_numeric($this->ownerId)) {
            $this->ownerId = [$this->ownerId];
        } else if (!is_array($this->ownerId) || !ArrayHelper::isNumeric($this->ownerId)) {
            throw new InvalidConfigException('Invalid ownerId param value');
        }
    }

    /**
     * @inheritdoc
     */
    protected function customFields(): array
    {
        // This method won't get called if $this->fieldId isn't set to a single int
        /** @var SuperTableField $supertableField */
        $supertableField = Craft::$app->getFields()->getFieldById(reset($this->fieldId));
        return $supertableField->getBlockTypeFields();
    }

    /**
     * @inheritdoc
     */
    protected function cacheTags(): array
    {
        $tags = [];
        // If both the field and owner are set, then only tag the combos
        if ($this->fieldId && $this->ownerId) {
            if (is_array($this->fieldId)) {
            foreach ($this->fieldId as $fieldId) {
                foreach ($this->ownerId as $ownerId) {
                    $tags[] = "field-owner:$fieldId-$ownerId";
                }
            }
            }
        } else {
            if ($this->fieldId) {
                if (is_array($this->fieldId)) {
                    foreach ($this->fieldId as $fieldId) {
                        $tags[] = "field:$fieldId";
                    }
                }
            }
            if ($this->ownerId) {
                if (is_array($this->ownerId)) {
                    foreach ($this->ownerId as $ownerId) {
                        $tags[] = "owner:$ownerId";
                    }
                }
            }
        }
        return $tags;
    }
}
