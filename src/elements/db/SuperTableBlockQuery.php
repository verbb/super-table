<?php
namespace verbb\supertable\elements\db;

use verbb\supertable\elements\SuperTableBlockElement;
use verbb\supertable\fields\SuperTableField;
use verbb\supertable\models\SuperTableBlockType;

use Craft;
use craft\base\ElementInterface;
use craft\db\Query;
use craft\db\QueryAbortedException;
use craft\db\Table;
use craft\helpers\ArrayHelper;
use craft\elements\db\ElementQuery;
use craft\helpers\Db;

use yii\base\InvalidArgumentException;
use yii\base\InvalidConfigException;
use yii\db\Connection;

use ReflectionProperty;
use ReflectionClass;

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
    protected array $defaultOrderBy = ['supertableblocks_owners.sortOrder' => SORT_ASC];

    // General parameters
    // -------------------------------------------------------------------------

    /**
     * @var int|int[]|string|false|null The field ID(s) that the resulting Super Table blocks must belong to.
     * @used-by fieldId()
     */
    public mixed $fieldId = null;

    /**
     * @var int|int[]|null The primary owner element ID(s) that the resulting Super Table blocks must belong to.
     * @used-by primaryOwner()
     * @used-by primaryOwnerId()
     * @since 3.0.0
     */
    public mixed $primaryOwnerId = null;

    /**
     * @var int|int[]|null The owner element ID(s) that the resulting Super Table blocks must belong to.
     * @used-by owner()
     * @used-by ownerId()
     */
    public mixed $ownerId = null;

    /**
     * @var bool|null Whether the owner elements can be drafts.
     * @used-by allowOwnerDrafts()
     * @since 2.4.2
     */
    public ?bool $allowOwnerDrafts = null;

    /**
     * @var bool|null Whether the owner elements can be revisions.
     * @used-by allowOwnerRevisions()
     * @since 2.4.2
     */
    public ?bool $allowOwnerRevisions = null;

    /**
     * @var int|int[]|null The block type ID(s) that the resulting Super Table blocks must have.
     * @used-by SuperTableBlockQuery::type()
     * @used-by typeId()
     */
    public mixed $typeId = null;

    /**
     * @var bool|null Whether the field is static.
     */
    public ?bool $staticField = null;


    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function __set($name, $value)
    {
        switch ($name) {
            case 'field':
                $this->field($value);
                break;
            case 'owner':
                $this->owner($value);
                break;
            case 'primaryOwner':
                $this->primaryOwner($value);
                break;
            case 'type':
                $this->type($value);
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
            $block = $this->one();

            if ($block && method_exists($block, $name)) {
                return $block->$name($params);
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
    public function field(mixed $value): self
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
    public function fieldId(mixed $value): self
    {
        $this->fieldId = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the primary owner element of the Super Table blocks, per the owners’ IDs.
     *
     * @param int|int[]|null $value The property value
     * @return self self reference
     * @uses $primaryOwnerId
     * @since 3.0.0
     */
    public function primaryOwnerId(mixed $value): self
    {
        $this->primaryOwnerId = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the primary owner element of the Super Table blocks, per the owners’ IDs.
     *
     * @param int|int[]|null $value The property value
     * @return self self reference
     * @uses $primaryOwnerId
     * @since 3.0.0
     */
    public function primaryOwner(ElementInterface $primaryOwner): self
    {
        $this->primaryOwnerId = [$primaryOwner->id];
        $this->siteId = $primaryOwner->siteId;
        return $this;
    }

    /**
     * Sets the [[ownerId]] property.
     *
     * @param int|int[]|null $value The property value
     *
     * @return static self reference
     */
    public function ownerId(array|int|null $value): static
    {
        $this->ownerId = $value;
        return $this;
    }

    /**
     * Sets the [[ownerId()]] and [[siteId()]] parameters based on a given element.
     *
     * @param ElementInterface $owner The owner element
     * @return self self reference
     * @uses $ownerId
     */
    public function owner(ElementInterface $owner): self
    {
        $this->ownerId = [$owner->id];
        $this->siteId = $owner->siteId;
        return $this;
    }

    /**
     * Narrows the query results based on whether the Super Table blocks’ owners are drafts.
     *
     * @param bool|null $value The property value
     * @return self self reference
     * @uses $allowOwnerDrafts
     */
    public function allowOwnerDrafts(?bool $value = true): self
    {
        $this->allowOwnerDrafts = $value;
        return $this;
    }

    /**
     * Narrows the query results based on whether the Super Table blocks’ owners are revisions.
     *
     * @param bool|null $value The property value
     * @return self self reference
     * @uses $allowOwnerDrafts
     * @since 2.4.2
     */
    public function allowOwnerRevisions(?bool $value = true): self
    {
        $this->allowOwnerRevisions = $value;
        return $this;
    }

    /**
     * Sets the [[typeId]] property based on a given block type(s)’s id(s).
     *
     * @param string|string[]|SuperTableBlockType|null $value The property value
     * @return self self reference
     * @uses $typeId
     */
    public function type(mixed $value): self
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
    public function typeId(mixed $value): self
    {
        $this->typeId = $value;
        return $this;
    }

    public function staticField(mixed $value): self
    {
        $this->staticField = $value;
        return $this;
    }

    public function criteriaAttributes(): array
    {
        // Would be nice to use this, but due to how people call Super Table blocks directly, it's not possible.
        // if (!$this->staticField) {
        //     return parent::criteriaAttributes();
        // }

        $class = new ReflectionClass($this);
        $names = [];

        // Restore legacy-handling for people using `entry.stfield.field` via direct access. They shouldn't be, as it's
        // considered invalid unless it's a static field, but we better keep this alive for the time-being to keep the peace.
        // This was a direct issue with changes to `criteriaAttributes()` in Craft 3.5.17 which causes ST fields to error
        // when being saved, due to incorrect custom fields.
        foreach ($class->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if (!$property->isStatic()) {
                $dec = $property->getDeclaringClass();
                if (
                    ($dec->getName() === self::class || $dec->isSubclassOf(self::class)) &&
                    !in_array($property->getName(), ['elementType', 'query', 'subQuery', 'contentTable', 'customFields', 'asArray'], true)
                ) {
                    $names[] = $property->getName();
                }
            }
        }

        return $names;
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function beforePrepare(): bool
    {
        $this->_normalizeFieldId();

        try {
            $this->primaryOwnerId = $this->_normalizeOwnerId($this->primaryOwnerId);
        } catch (InvalidArgumentException) {
            throw new InvalidConfigException('Invalid ownerId param value');
        }

        try {
            $this->ownerId = $this->_normalizeOwnerId($this->ownerId);
        } catch (InvalidArgumentException) {
            throw new InvalidConfigException('Invalid ownerId param value');
        }

        $this->joinElementTable('supertableblocks');

        // Join in the supertableblocks_owners table
        $ownersCondition = [
            'and',
            '[[supertableblocks_owners.blockId]] = [[elements.id]]',
            $this->ownerId ? ['supertableblocks_owners.ownerId' => $this->ownerId] : '[[supertableblocks_owners.ownerId]] = [[supertableblocks.primaryOwnerId]]',
        ];

        $this->query->innerJoin(['supertableblocks_owners' => '{{%supertableblocks_owners}}'], $ownersCondition);
        $this->subQuery->innerJoin(['supertableblocks_owners' => '{{%supertableblocks_owners}}'], $ownersCondition);

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
            'supertableblocks.primaryOwnerId',
            'supertableblocks.typeId',
            'supertableblocks_owners.ownerId',
            'supertableblocks_owners.sortOrder',
        ]);

        if ($this->fieldId) {
            $this->subQuery->andWhere(['supertableblocks.fieldId' => $this->fieldId]);
        }

        if ($this->primaryOwnerId) {
            $this->subQuery->andWhere(['supertableblocks.primaryOwnerId' => $this->primaryOwnerId]);
        }

        if (isset($this->typeId)) {
            // If typeId is an empty array, it's because type() was called but no valid type handles were passed in
            if (empty($this->typeId)) {
                return false;
            }

            $this->subQuery->andWhere(Db::parseNumericParam('supertableblocks.typeId', $this->typeId));
        }

        // Ignore revision/draft blocks by default
        $allowOwnerDrafts = $this->allowOwnerDrafts ?? ($this->id || $this->primaryOwnerId || $this->ownerId);
        $allowOwnerRevisions = $this->allowOwnerRevisions ?? ($this->id || $this->primaryOwnerId || $this->ownerId);

        if (!$allowOwnerDrafts || !$allowOwnerRevisions) {
            // todo: we will need to expand on this when Super Table blocks can be nested.
            $this->subQuery->innerJoin(
                ['owners' => Table::ELEMENTS],
                $this->ownerId ? '[[owners.id]] = [[supertableblocks_owners.ownerId]]' : '[[owners.id]] = [[supertableblocks.primaryOwnerId]]'
            );

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
    private function _normalizeFieldId(): void
    {
        if ($this->fieldId === null && $this->id) {
            $this->fieldId = (new Query())
                ->select(['fieldId'])
                ->distinct()
                ->from(['{{%supertableblocks}}'])
                ->where(Db::parseNumericParam('id', $this->id))
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
                ->where(Db::parseNumericParam('id', $this->fieldId))
                ->andWhere(['type' => SuperTableField::class])
                ->column();
        }
    }

    /**
     * Normalizes the primaryOwnerId param to an array of IDs or null
     *
     * @param mixed $value
     * @return int[]|null
     * @throws InvalidArgumentException
     */
    private function _normalizeOwnerId(mixed $value): ?array
    {
        if (empty($value)) {
            return null;
        }

        if (is_numeric($value)) {
            return [$value];
        }

        if (!is_array($value) || !ArrayHelper::isNumeric($value)) {
            throw new InvalidArgumentException();
        }

        return $value;
    }

    /**
     * @inheritdoc
     */
    protected function customFields(): array
    {
        // This method won't get called if $this->fieldId isn't set to a single int
        /** @var SuperTableField $supertableField */
        $supertableField = Craft::$app->getFields()->getFieldById(reset($this->fieldId));

        if (!empty($this->typeId)) {
            $blockTypes = ArrayHelper::toArray($this->typeId);

            if (ArrayHelper::isNumeric($blockTypes)) {
                return $supertableField->getBlockTypeFields($blockTypes);
            }
        }

        return $supertableField->getBlockTypeFields();
    }

    /**
     * @inheritdoc
     */
    protected function cacheTags(): array
    {
        $tags = [];

        // If both the field and owner are set, then only tag the combos
        if ($this->fieldId && $this->primaryOwnerId) {
            foreach ($this->fieldId as $fieldId) {
                foreach ($this->primaryOwnerId as $primaryOwnerId) {
                    $tags[] = "field-owner:$fieldId-$primaryOwnerId";
                }
            }
        } else {
            if ($this->fieldId) {
                foreach ($this->fieldId as $fieldId) {
                    $tags[] = "field:$fieldId";
                }
            }
            if ($this->primaryOwnerId) {
                foreach ($this->primaryOwnerId as $primaryOwnerId) {
                    $tags[] = "owner:$primaryOwnerId";
                }
            }
        }
        
        return $tags;
    }
}
