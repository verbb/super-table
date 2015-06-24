<?php
namespace Craft;

class SuperTable_BlockElementType extends BaseElementType
{
	// Public Methods
	// =========================================================================

	public function getName()
	{
		return Craft::t('SuperTable Blocks');
	}

	public function hasContent()
	{
		return true;
	}

	public function isLocalized()
	{
		return true;
	}

	public function defineCriteriaAttributes()
	{
		return array(
			'fieldId'     => AttributeType::Number,
			'order'       => array(AttributeType::String, 'default' => 'supertableblocks.sortOrder'),
			'ownerId'     => AttributeType::Number,
			'ownerLocale' => AttributeType::Locale,
			'type'        => AttributeType::Mixed,
		);
	}

	public function getContentTableForElementsQuery(ElementCriteriaModel $criteria)
	{
		if (!$criteria->fieldId && $criteria->id && is_numeric($criteria->id)) {
			$criteria->fieldId = craft()->db->createCommand()
				->select('fieldId')
				->from('supertableblocks')
				->where('id = :id', array(':id' => $criteria->id))
				->queryScalar();
		}

		if ($criteria->fieldId && is_numeric($criteria->fieldId)) {
			$superTableField = craft()->fields->getFieldById($criteria->fieldId);

			if ($superTableField) {
				return craft()->superTable->getContentTableName($superTableField);
			}
		}
	}

	public function getFieldsForElementsQuery(ElementCriteriaModel $criteria)
	{
		$fields = array();

		$blockTypes = craft()->superTable->getBlockTypesByFieldId($criteria->fieldId);

		foreach ($blockTypes as $blockType)
		{
			$contexts = array();

			foreach ($blockTypes as $blockType)
			{
				$contexts[] = 'superTableBlockType:'.$blockType->id;
			}

			// Preload them to save ourselves some DB queries, and discard
			craft()->fields->getAllFields(null, $contexts);
		}

		foreach ($blockTypes as $blockType)
		{
			$fieldColumnPrefix = 'field_';

			foreach ($blockType->getFields() as $field) {
				$field->columnPrefix = $fieldColumnPrefix;
				$fields[] = $field;
			}
		}

		return $fields;
	}

	public function modifyElementsQuery(DbCommand $query, ElementCriteriaModel $criteria)
	{
		$query
			->addSelect('supertableblocks.fieldId, supertableblocks.ownerId, supertableblocks.ownerLocale, supertableblocks.typeId, supertableblocks.sortOrder')
			->join('supertableblocks supertableblocks', 'supertableblocks.id = elements.id');

		if ($criteria->fieldId) {
			$query->andWhere(DbHelper::parseParam('supertableblocks.fieldId', $criteria->fieldId, $query->params));
		}

		if ($criteria->ownerId) {
			$query->andWhere(DbHelper::parseParam('supertableblocks.ownerId', $criteria->ownerId, $query->params));
		}

		if ($criteria->ownerLocale) {
			$query->andWhere(DbHelper::parseParam('supertableblocks.ownerLocale', $criteria->ownerLocale, $query->params));
		}

		if ($criteria->type) {
			$query->join('supertableblocktypes supertableblocktypes', 'supertableblocktypes.id = supertableblocks.typeId');
			$query->andWhere(DbHelper::parseParam('supertableblocktypes.handle', $criteria->type, $query->params));
		}
	}

	public function populateElementModel($row)
	{
		return SuperTable_BlockModel::populateModel($row);
	}
}
