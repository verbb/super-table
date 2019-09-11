<?php
namespace verbb\supertable\models;

use Craft;
use verbb\supertable\elements\SuperTableBlockElement;
use verbb\supertable\fields\SuperTableField;

use craft\base\FieldInterface;
use craft\base\GqlInlineFragmentInterface;
use craft\base\Model;
use craft\behaviors\FieldLayoutBehavior;

use yii\base\InvalidConfigException;

class SuperTableBlockTypeModel extends Model implements GqlInlineFragmentInterface
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

    /**
     * @var string|mixed
     */
    public $uid;

    /**
     * @var string
     */
    private $handle;


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
        $rules = parent::rules();
        $rules[] = [['id', 'fieldId'], 'number', 'integerOnly' => true];

        return $rules;
    }

    /**
     * Set fake handle.
     *
     * @param string
     */
    public function setHandle($handle)
    {
        $this->handle = $handle;
    }

    /**
     * Fake handle for easier integrations.
     *
     * @return string
     */
    public function getHandle()
    {
        if (!isset($this->handle) && $this->fieldId) {
            $field = Craft::$app->fields->getFieldById($this->fieldId);
            
            foreach ($field->getBlockTypes() as $index => $blockType) {
                if ($blockType->id == $this->id) {
                    $this->handle = $field->handle . '-' . $index;
                    break;
                }
            }
        }

        return $this->handle;
    }

    /**
     * Use the block type handle as the string representation.
     *
     * @return string
     */
    public function __toString(): string
    {
        return (string)$this->id ?: static::class;
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

    /**
     * Returns the block type's field.
     *
     * @return SuperTableField
     * @throws InvalidConfigException if [[fieldId]] is missing or invalid
     */
    public function getField(): SuperTableField
    {
        if ($this->fieldId === null) {
            throw new InvalidConfigException('Block type missing its field ID');
        }

        /** @var SuperTableField $field */
        if (($field = Craft::$app->getFields()->getFieldById($this->fieldId)) === null) {
            throw new InvalidConfigException('Invalid field ID: ' . $this->fieldId);
        }

        return $field;
    }

    /**
     * @inheritdoc
     */
    public function getFieldContext(): string
    {
        return 'superTableBlockType:' . $this->uid;
    }

    /**
     * @inheritdoc
     */
    public function getEagerLoadingPrefix(): string
    {
        return '';
    }
}
