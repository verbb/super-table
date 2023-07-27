<?php
namespace verbb\supertable\console\controllers;

use verbb\supertable\migrations\FixContentTableIndexes;

use Throwable;

use yii\helpers\Console;
use yii\console\Controller;
use yii\console\ExitCode;

class MigrateController extends Controller
{
    // Public Methods
    // =========================================================================

    public function actionFixContentTableIndexes(): int
    {
        $migration = new FixContentTableIndexes();
        $migration->up();

        return ExitCode::OK;
    }
}
