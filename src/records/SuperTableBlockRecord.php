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
     *
     * @return string
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
    public function getElement(): ActiveQueryInterface
    {
        return $this->hasOne(Element::class, ['id' => 'id']);
    }

    /**
     * Returns the SuperTable block’s owner.
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getOwner(): ActiveQueryInterface
    {
        return $this->hasOne(Element::class, ['id' => 'ownerId']);
    }

    /**
     * Returns the SuperTable block’s field.
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getField(): ActiveQueryInterface
    {
        return $this->hasOne(Field::class, ['id' => 'fieldId']);
    }

    /**
     * Returns the SuperTable block’s type.
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getType(): ActiveQueryInterface
    {
        return $this->hasOne(SuperTableBlockType::class, ['id' => 'typeId']);
    }
}