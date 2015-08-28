<?php

use yii\helpers\Html;
use yz\admin\widgets\ActionButtons;
use yz\admin\widgets\ActiveForm;
use yz\admin\widgets\Box;
use yz\admin\widgets\FormBox;

/**
 * @var yii\web\View $this
 * @var string $extraView
 * @var \yz\admin\import\ImportForm $model
 */
$this->title = Yii::t('admin/import', 'Batch import');
$this->params['breadcrumbs'][] = ['label' => Yii::t('admin/import', 'All records'), 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
$this->params['header'] = $this->title;
?>
<div class="batch-import">

    <div class="text-right">
        <?php Box::begin() ?>
        <?= ActionButtons::widget([
            'order' => [['index', 'return']],
            'addReturnUrl' => false,
        ]) ?>
        <?php Box::end() ?>
    </div>

    <?php $box = FormBox::begin(['cssClass' => 'batch-import-form box-primary', 'title' => '']) ?>
    <?php $box->beginBody() ?>

    <?php $form = ActiveForm::begin([
        'options' => ['enctype' => 'multipart/form-data']
    ]); ?>

    <?= $form->field($model, 'file')->fileInput() ?>
    <?= $form->field($model, 'encoding')->dropDownList($model->getEncodingValues()) ?>
    <?php
    $hint = Html::tag('p', Yii::t('admin/import', 'Available fields:'));
    $fields = $model->availableFields;
    array_walk($fields, function (&$item, $key) {
        $item = Html::tag('strong', $key) . ' - ' . $item;
    });
    $hint .= Html::beginTag('p') . implode('</p><p>', $fields);
    ?>
    <?= $form->field($model, 'fields')->hint($hint)->textInput() ?>
    <?= $form->field($model, 'skipFirstLine')->checkbox() ?>
    <?= $form->field($model, 'separator')->textInput()
        ->hint(Yii::t('admin/import', 'Only suitable for CSV files, ignored for Excel files'))
    ?>

    <?php if ($extraView): ?>
        <?= $this->render($extraView, ['form' => $form, 'model' => $model]) ?>
    <?php endif ?>

    <?php $box->endBody() ?>

    <?php $box->actions([
        Html::submitButton(Yii::t('admin/import', 'Upload and Import'), ['class' => 'btn btn-primary']),
    ]) ?>
    <?php ActiveForm::end(); ?>

    <?php FormBox::end() ?>

</div>
