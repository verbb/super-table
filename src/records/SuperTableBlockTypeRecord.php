<?php
namespace verbb\supertable\records;

use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

class SuperTableBlockTypeRecord extends ActiveRecord
{
    // Public Methods
    // =========================================================================
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%supertableblocktypes}}';
    }

    /**
     * Returns the SuperTable block type’s field.
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getField(): \craft\db\ActiveQuery
    {
        return $this->hasOne(Field::class, ['id' => 'fieldId']);
    }

    /**
     * Returns the SuperTable block type’s fieldLayout.
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getFieldLayout(): \craft\db\ActiveQuery
    {
        return $this->hasOne(FieldLayout::class, ['id' => 'fieldLayoutId']);
    }
}
