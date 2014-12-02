<?php

namespace yz\admin\import;

use Goodby\CSV\Import\Standard\Interpreter;
use Goodby\CSV\Import\Standard\Lexer;
use Goodby\CSV\Import\Standard\LexerConfig;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\base\Model;
use yii\web\UploadedFile;


/**
 * Class ImportForm
 *
 * @property array $fieldsArray
 * @property int $importCounter
 */
class ImportForm extends Model
{
    const ENCODING_UTF8 = 'utf8';
    const ENCODING_CP1251 = 'cp1251';

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
    public $skipFirstLine = false;
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
    /**
     * @var BatchImportAction
     */
    public $action;
    /**
     * @var int
     */
    protected $_importCounter;

    public function init()
    {
        if ($this->availableFields === null) {
            throw new InvalidConfigException('Available fields are not defined');
        }
        if ($this->fields === null) {
            $this->fields = implode(', ', array_keys($this->availableFields));
        }
    }

    public function rules()
    {
        return [
            [['file', 'encoding', 'fields', 'separator',], 'required'],
            [['file'], 'file', 'extensions' => ['csv'], 'checkExtensionByMimeType' => false],
            [['skipFirstLine'], 'boolean'],
            [['separator'], 'string', 'length' => 1],
            [['encoding'], 'in', 'range' => array_keys(self::getEncodingValues())],
        ];
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
            self::ENCODING_CP1251 => \Yii::t('admin/import', 'Windows-1251'),
            self::ENCODING_UTF8 => \Yii::t('admin/import', 'UTF-8'),
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
            $this->_importCounter = 0;
            $lexer = new Lexer((new LexerConfig())
                    ->setDelimiter($this->separator)
                    ->setIgnoreHeaderLine($this->skipFirstLine)
                    ->setFromCharset($this->encoding)
                    ->setToCharset('utf8')
            );
            $interpreter = new Interpreter();
            $interpreter->unstrict();
            $interpreter->addObserver(function ($row) {
                $row = self::compose($row);
                $this->callHandler('rowImport', [$this, $row]);
                $this->_importCounter++;
            });
            $transaction = \Yii::$app->db->beginTransaction();
            try {
                $lexer->parse($this->file->tempName, $interpreter);
                $transaction->commit();
            } catch (Exception $e) {
                $transaction->rollBack();
                throw $e;
            }

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
        foreach ($this->fieldsArray as $id => $name) {
            if (isset($row[$id])) {
                $result[$name] = $row[$id];
            } else {
                $result[$name] = null;
            }
        }
        return $result;
    }

    /**
     * @return int
     */
    public function getImportCounter()
    {
        return $this->_importCounter;
    }
}