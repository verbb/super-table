<?php
namespace verbb\supertable\controllers;

use verbb\supertable\SuperTable;

use Craft;
use craft\fields\Matrix;

use craft\web\Controller;

class FieldsController extends Controller
{
    // Public Methods
    // =========================================================================

    public function actionRenderSettings()
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();
        $type = $request->getRequiredBodyParam('type');
        $field = Craft::$app->getFields()->createField($type);

        $view = Craft::$app->getView();
        $namespace = $request->getBodyParam('namespace');

        // A Matrix inside a Super Table field needs special handling to get the right namespace
        if ($type === Matrix::class) {
            $oldNamespace = $view->getNamespace();
            $view->setNamespace($namespace);
            $html = SuperTable::$plugin->matrixService->getMatrixSettingsHtml($field);
            $view->setNamespace($oldNamespace);
            
            if ($html !== null) {
                $html = $view->namespaceInputs($html, $namespace);
            }
        } else {
            $html = $view->renderTemplate('settings/fields/_type-settings', [
                'field' => $field,
                'namespace' => $namespace,
            ]);
        }

        return $this->asJson([
            'settingsHtml' => $html,
            'headHtml' => $view->getHeadHtml(),
            'footHtml' => $view->getBodyHtml(),
        ]);
    }

}
