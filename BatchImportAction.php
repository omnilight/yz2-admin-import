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
     * @var string Import form class
     */
    public $formClass = 'yz\admin\import\ImportForm';
    /**
     * @var array Fields available for user
     */
    public $availableFields;
    /**
     * @var array|string Where to redirect after successful upload
     */
    public $redirectAfterImport = ['index'];

    public function run()
    {
        /** @var ImportForm $model */
        $model = new $this->formClass;
        $model->availableFields = array_keys($this->availableFields);
        $model->fields = implode(', ', array_keys($this->availableFields));

        if ($model->load(\Yii::$app->request->post())) {
            return $this->controller->redirect($this->redirectAfterImport);
        }

        return $this->controller->render($this->view, [
            'model' => $model,
        ]);
    }
} 