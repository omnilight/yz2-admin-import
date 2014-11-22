<?php

namespace yz\admin\import;

use Goodby\CSV\Import\Standard\Interpreter;
use Goodby\CSV\Import\Standard\Lexer;
use Goodby\CSV\Import\Standard\LexerConfig;
use yii\base\InvalidConfigException;
use yii\base\Model;
use yii\web\UploadedFile;


/**
 * Class ImportForm
 *
 * @property array $fieldsArray
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
     * @var array Fields available for user
     */
    public $availableFields;
    /**
     * @var callable Format:
     * ```php
     * function (ImportForm $form) {
     *
     * }
     * ```
     */
    public $beforeImport;
    /**
     * @var callable Format:
     * ```php
     * function (ImportForm $form, array $row) {
     *
     * }
     * ```
     */
    public $rowImport;
    /**
     * @var callable Format:
     * ```php
     * function (ImportForm $form) {
     *
     * }
     * ```
     */
    public $afterImport;

    public function init()
    {
        if ($this->availableFields === null) {
            throw new InvalidConfigException('Available fields are not defined');
        }
        if ($this->fields === null) {
            $this->fields = implode(', ', array_keys($this->availableFields));
        }
    }


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
        if ($this->validate() && $this->callHandler('beforeImport', [$this])) {
            $lexer = new Lexer((new LexerConfig())
                    ->setDelimiter($this->separator)
                    ->setIgnoreHeaderLine($this->skipFirstLine)
                    ->setFromCharset($this->encoding)
            );
            $interpreter = new Interpreter();
            $interpreter->unstrict();
            $interpreter->addObserver(function ($row) {
                $row = self::compose($row);
                $this->callHandler('rowImport', [$this, $row]);
            });
            $lexer->parse($this->file->tempName, $interpreter);
            $this->callHandler('afterImport', [$this]);

            return true;
        } else {
            return false;
        }
    }

    /**
     * @param callable $handler
     * @param array $data
     * @return bool
     */
    protected function callHandler($handler, $data)
    {
        if ($this->{$handler} === null)
            return true;

        return call_user_func_array($this->{$handler}, $data);
    }

    protected $_fieldsArray = [];

    /**
     * @return array
     */
    public function getFieldsArray()
    {
        if (!isset($this->_fieldsArray[$this->fields])) {
            $this->_fieldsArray[$this->fields] = preg_split('/\s*[,;]\s*/', $this->fields, -1, PREG_SPLIT_NO_EMPTY);
        }
        return $this->_fieldsArray[$this->fields];
    }

    /**
     * @param array $row
     * @return array
     */
    protected function compose($row)
    {
        $result = [];
        foreach ($this->fieldsArray as $name => $id) {
            if (isset($row[$id])) {
                $result[$name] = $row[$id];
            } else {
                $result[$name] = null;
            }
        }
        return $result;
    }
} 