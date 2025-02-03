<?php
namespace verbb\supertable\console\controllers;

use verbb\supertable\migrations\m240115_000000_craft5;

use Craft;
use craft\console\Controller;
use craft\db\Query;
use craft\db\Table;
use craft\fields\Matrix;
use craft\helpers\Console;

use yii\console\ExitCode;

class MigrateController extends Controller
{
    /**
     * Migrates all Super Table fields to Matrix fields.
     *
     * @return int
     */
    public function actionIndex(): int
    {
        foreach (Craft::$app->getFields()->getAllFields(false) as $field) {
            if (get_class($field) === 'verbb\supertable\fields\SuperTableField') {
                $config = Craft::$app->getFields()->createFieldConfig($field);
                $settings = $config['settings'] ?? [];

                // Filter out any settings that aren't compatible with Matrix fields
                foreach ($settings as $property => $setting) {
                    if (!property_exists(Matrix::class, $property) && !method_exists(Matrix::class, 'set' . $property)) {
                        unset($settings[$property]);
                    }
                }

                $config['type'] = Matrix::class;
                $config['settings'] = $settings;

                Craft::$app->getProjectConfig()->set('fields.' . $field->uid, $config);

                $this->stdout("Migrated Super Table field #" . $field->id . ' (' . $field->handle . ") to Matrix.\n", Console::FG_GREEN);
            }
        }

        $this->stdout("Done.\n", Console::FG_GREEN);

        return ExitCode::OK;
    }

    /**
     * Runs the Super Table > Matrix migration. DO NOT run this unless you know what you're doing.
     *
     * @return int
     */
    public function actionForceFieldMigration(): int
    {
        $migration = new m240115_000000_craft5();
        $migration->safeUp();

        $this->stdout("Done.\n", Console::FG_GREEN);

        return ExitCode::OK;
    }
}