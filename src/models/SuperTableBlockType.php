<?php
namespace verbb\supertable\models;

use Craft;
use verbb\supertable\elements\SuperTableBlockElement;
use verbb\supertable\fields\SuperTableField;

use craft\base\GqlInlineFragmentInterface;
use craft\base\Model;
use craft\behaviors\FieldLayoutBehavior;

use yii\base\InvalidConfigException;

class SuperTableBlockType extends Model implements GqlInlineFragmentInterface
{
    // Properties
    // =========================================================================

    /**
     * @var int|string|null ID The block ID. If unsaved, it will be in the format "newX".
     */
    public string|int|null $id = null;

    /**
     * @var int|null Field ID
     */
    public ?int $fieldId = null;

    /**
     * @var int|null Field layout ID
     */
    public ?int $fieldLayoutId = null;

    /**
     * @var string|null Handle
     */
    public ?string $handle = null;

    /**
     * @var bool
     */
    public bool $hasFieldErrors = false;

    /**
     * @var string|null
     */
    public ?string $uid = null;


    // Public Methods
    // =========================================================================

    /**
     * Fake handle for easier integrations.
     */
    public function getHandle(): string
    {
        if (!isset($this->handle) && $this->fieldId) {
            $field = Craft::$app->fields->getFieldById($this->fieldId);

            foreach ($field->getBlockTypes() as $index => $blockType) {
                if ($blockType->id == $this->id) {
                    $this->handle = $field->handle . '_' . $index;
                    break;
                }
            }
        }

        return $this->handle;
    }

    /**
     * Set fake handle.
     *
     * @param string
     */
    public function setHandle($handle): void
    {
        $this->handle = $handle;
    }

    /**
     * Use the block type handle as the string representation.
     */
    public function __toString(): string
    {
        return (string)$this->id ?: static::class;
    }

    /**
     * Returns whether this is a new block type.
     */
    public function getIsNew(): bool
    {
        return (!$this->id || str_starts_with($this->id, 'new'));
    }

    /**
     * Returns the block type's field.
     *
     * @throws InvalidConfigException if [[fieldId]] is missing or invalid
     */
    public function getField(): SuperTableField
    {
        if (!isset($this->fieldId)) {
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

    /**
     * Returns the field layout config for this block type.
     */
    public function getConfig(): array
    {
        $field = $this->getField();

        $config = [
            'field' => $field->uid,
            'fields' => [],
        ];

        $fieldLayout = $this->getFieldLayout();
        $fieldLayoutConfig = $fieldLayout->getConfig();

        if ($fieldLayoutConfig) {
            $config['fieldLayouts'][$fieldLayout->uid] = $fieldLayoutConfig;
        }

        $fieldsService = Craft::$app->getFields();
        foreach ($this->getCustomFields() as $field) {
            $config['fields'][$field->uid] = $fieldsService->createFieldConfig($field);
        }

        return $config;
    }

    /**
     * @inheritdoc
     */
    protected function defineBehaviors(): array
    {
        return [
            'fieldLayout' => [
                'class' => FieldLayoutBehavior::class,
                'elementType' => SuperTableBlockElement::class,
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['id', 'fieldId'], 'number', 'integerOnly' => true];

        return $rules;
    }
}
