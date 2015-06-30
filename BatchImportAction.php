<?php

namespace yz\admin\import;

use yii\base\Action;
use yii\web\Controller;
use yz\Yz;


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
     * @var string Extra view that can be used to append fields. Passed variables are $form and $model
     */
    public $extraView;
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
            'action' => $this,
        ], $this->importConfig));

        if ($model->load(\Yii::$app->request->post()) && $model->process()) {

            $this->showSkippedRows($model);

            \Yii::$app->session->setFlash(Yz::FLASH_INFO, \Yii::t('admin/import', 'Import have been done successfully. Total imported records: {total}', [
                'total' => $model->importCounter,
            ]));
            return $this->controller->redirect($this->redirectAfterImport);
        }

        $this->showErrorsMessage($model);

        return $this->controller->render($this->view, [
            'model' => $model,
            'extraView' => $this->extraView,
        ]);
    }

    /**
     * @param $model
     */
    protected function showSkippedRows(ImportForm $model)
    {
        if ($model->hasSkippedRows()) {
            $skipped = $model->getSkippedRows();

            $skippedMessage = ['Следующие строки были пропущены:'];
            for ($i = 0; $i < min(5, count($skipped)); $i++) {
                $skippedMessage[] = strtr("<code>{row}</code> - причина: <i>{info}</i>", [
                    '{row}' => implode(';', $skipped[$i][1]),
                    '{info}' => $skipped[$i][0],
                ]);
            }

            if (count($skipped) > 5) {
                $skippedMessage[] = strtr('... и еще {extra} строк, которые не показаны', [
                    '{extra}' => count($skipped) - 5,
                ]);
            }

            \Yii::$app->session->setFlash(Yz::FLASH_WARNING, implode("<br>", $skippedMessage));
        }
    }

    /**
     * @param $model
     */
    protected function showErrorsMessage(ImportForm $model)
    {
        if ($model->hasErrorMessage()) {
            \Yii::$app->session->setFlash(Yz::FLASH_ERROR, $model->getErrorMessage());
            $row = $model->getErrorRow();
            if ($row != []) {
                \Yii::$app->session->setFlash(Yz::FLASH_INFO, \Yii::t('admin/import', 'Row caused error: {row}', [
                    'row' => implode('; ', $row)
                ]));
            }
        }
    }
} 