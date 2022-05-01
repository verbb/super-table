<?php
namespace verbb\supertable\records;

use craft\base\Field;
use craft\db\ActiveQuery;
use craft\db\ActiveRecord;
use craft\models\FieldLayout;

class SuperTableBlockType extends ActiveRecord
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
     * @return ActiveQuery The relational query object.
     */
    public function getField(): ActiveQuery
    {
        return $this->hasOne(Field::class, ['id' => 'fieldId']);
    }

    /**
     * Returns the SuperTable block type’s fieldLayout.
     *
     * @return ActiveQuery The relational query object.
     */
    public function getFieldLayout(): ActiveQuery
    {
        return $this->hasOne(FieldLayout::class, ['id' => 'fieldLayoutId']);
    }
}
