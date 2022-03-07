<?php
namespace verbb\supertable\records;

use craft\db\ActiveRecord;
use craft\db\Table;

use yii\db\ActiveQueryInterface;

class SuperTableBlockRecord extends ActiveRecord
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
    public function getElement(): \craft\db\ActiveQuery
    {
        return $this->hasOne(Element::class, ['id' => 'id']);
    }

    /**
     * Returns the SuperTable block’s owner.
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getOwner(): \craft\db\ActiveQuery
    {
        return $this->hasOne(Element::class, ['id' => 'ownerId']);
    }

    /**
     * Returns the SuperTable block’s field.
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getField(): \craft\db\ActiveQuery
    {
        return $this->hasOne(Field::class, ['id' => 'fieldId']);
    }

    /**
     * Returns the SuperTable block’s type.
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getType(): \craft\db\ActiveQuery
    {
        return $this->hasOne(SuperTableBlockType::class, ['id' => 'typeId']);
    }
}