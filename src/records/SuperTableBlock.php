<?php
namespace verbb\supertable\records;

use craft\base\Element;
use craft\base\Field;
use craft\db\ActiveQuery;
use craft\db\ActiveRecord;

use yii\db\ActiveQueryInterface;

class SuperTableBlock extends ActiveRecord
{
    // Public Methods
    // =========================================================================
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%supertableblocks}}';
    }

    /**
     * Returns the SuperTable block’s element.
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getElement(): ActiveQuery
    {
        return $this->hasOne(Element::class, ['id' => 'id']);
    }

    /**
     * Returns the SuperTable block’s owner.
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getPrimaryOwner(): ActiveQueryInterface
    {
        return $this->hasOne(Element::class, ['id' => 'primaryOwnerId']);
    }

    /**
     * Returns the SuperTable block’s field.
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getField(): ActiveQuery
    {
        return $this->hasOne(Field::class, ['id' => 'fieldId']);
    }

    /**
     * Returns the SuperTable block’s type.
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getType(): ActiveQuery
    {
        return $this->hasOne(SuperTableBlockType::class, ['id' => 'typeId']);
    }
}