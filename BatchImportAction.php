<?php

namespace yz\admin\import;

use yii\base\Action;
use yii\web\Controller;


/**
 * Class BatchImportAction provides base action that can be used for importing of the records from CSV files
 */
class BatchImportAction extends Action
{
    /**
     * @var Controller the controller that owns this action
     */
    public $controller;
    /**
     * @var string View used for the action
     */
    public $view = '@yz/admin/import/views/batch-import.php';
    /**
     * @var string Import configuration. Required field is 'availableFields'
     */
    public $importConfig = [];
    /**
     * @var array|string Where to redirect after successful upload
     */
    public $redirectAfterImport = ['index'];

    public function run()
    {
        /** @var ImportForm $model */
        $model = \Yii::createObject(array_merge([
            'class' => 'yz\admin\import\ImportForm',
        ], $this->importConfig));

        if ($model->load(\Yii::$app->request->post()) && $model->process()) {
            return $this->controller->redirect($this->redirectAfterImport);
        }

        return $this->controller->render($this->view, [
            'model' => $model,
        ]);
    }
} 