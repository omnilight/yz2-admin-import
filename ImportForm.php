<?php

namespace yz\admin\import;

use yii\base\Model;
use yii\web\UploadedFile;


/**
 * Class ImportForm
 */
class ImportForm extends Model
{
    const ENCODING_UTF8 = 'utf8';
    const ENCODING_CP1251 = 'windows1251';

    /**
     * @var UploadedFile
     */
    public $file;
    /**
     * @var string File encoding
     */
    public $encoding;
    /**
     * @var string Fields in the file
     */
    public $fields;
    /**
     * @var bool If to skip first line that contains header
     */
    public $skipFirstLine = true;
    /**
     * @var string Separator
     */
    public $separator = ';';
    /**
     * @var array Fields available for import
     */
    public $availableFields;

    public function attributeLabels()
    {
        return [
            'file' => \Yii::t('admin/import', 'CSV file'),
            'encoding' => \Yii::t('admin/import', 'Encoding'),
            'fields' => \Yii::t('admin/import', 'Fields'),
            'skipFirstLine' => \Yii::t('admin/import', 'Skip first line'),
            'separator' => \Yii::t('admin/import', 'Separator'),
        ];
    }


    public static function getEncodingValues()
    {
        return [
            self::ENCODING_UTF8 => \Yii::t('admin/import', 'UTF-8'),
            self::ENCODING_CP1251 => \Yii::t('admin/import', 'Windows-1251'),
        ];
    }

    public function load($data, $formName = null)
    {
        if (parent::load($data, $formName)) {
            $this->file = UploadedFile::getInstance($this, 'file');
            return true;
        } else {
            return false;
        }
    }

    public function process()
    {
        if ($this->validate()) {



            return true;
        } else {
            return false;
        }
    }
} 