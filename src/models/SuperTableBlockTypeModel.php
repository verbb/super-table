<?php
namespace verbb\supertable\models;

use verbb\supertable\elements\SuperTableBlockElement;

use craft\base\FieldInterface;
use craft\base\Model;
use craft\behaviors\FieldLayoutBehavior;

class SuperTableBlockTypeModel extends Model
{
    // Properties
    // =========================================================================

    /**
     * @var int|string|null ID The block ID. If unsaved, it will be in the format "newX".
     */
    public $id;

    /**
     * @var int|null Field ID
     */
    public $fieldId;

    /**
     * @var int|null Field layout ID
     */
    public $fieldLayoutId;

    /**
     * @var bool
     */
    public $hasFieldErrors = false;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'fieldLayout' => [
                'class' => FieldLayoutBehavior::class,
                'elementType' => SuperTableBlockElement::class
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id', 'fieldId'], 'number', 'integerOnly' => true],
        ];
    }

    /**
     * Use the block type handle as the string representation.
     *
     * @return string
     */
    public function __toString(): string
    {
        return (string)$this->id;
    }

    /**
     * Returns whether this is a new block type.
     *
     * @return bool
     */
    public function getIsNew(): bool
    {
        return (!$this->id || strpos($this->id, 'new') === 0);
    }
}
