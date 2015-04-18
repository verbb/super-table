<?php
namespace Craft;

class SuperTableService extends BaseApplicationComponent
{
	public function getContentTableName(SuperTableModel $table, $useOldHandle=false)
	{
		$name = '_'.StringHelper::toLowerCase($table->id);
		
		return 'sproutformscontent'.$name;
	}

	public function getContentTable($table)
	{
		//$form = $this->getFormById($formId);

		//if ($form)
		//{
			return sprintf('sproutformscontent_%s', trim(strtolower($table->id)));
		//}

		return 'content';
	}
}