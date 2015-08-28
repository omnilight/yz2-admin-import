<?php

namespace yz\admin\import;

use Goodby\CSV\Import\Standard\Interpreter;
use Goodby\CSV\Import\Standard\Lexer;
use Goodby\CSV\Import\Standard\LexerConfig;
use yii\base\InvalidConfigException;
use yii\base\Model;
use yii\helpers\FileHelper;
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

    const SKIP_FIELD_NAME = 'skip';

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
    protected $_fieldsArray = [];
    /**
     * @var string
     */
    private $_errorMessage = null;
    private $_errorRow = [];
    /**
     * @var array
     */
    private $_skippedRows = [];

    public function init()
    {
        if ($this->availableFields === null) {
            throw new InvalidConfigException('Available fields are not defined');
        }
        if ($this->fields === null) {
            $this->fields = implode(', ', array_keys($this->availableFields));
        }
        $this->availableFields = array_merge([
            self::SKIP_FIELD_NAME => \Yii::t('admin/import', 'Skip this column. Can be used multiple times')
        ], $this->availableFields);
    }

    public function rules()
    {
        return [
            [['file', 'encoding', 'fields', 'separator',], 'required'],
            [['file'], 'file', 'extensions' => ['csv', 'xls', 'xlsx'], 'checkExtensionByMimeType' => false],
            [['skipFirstLine'], 'boolean'],
            [['separator'], 'string', 'length' => 1],
            [['encoding'], 'in', 'range' => array_keys(self::getEncodingValues())],
        ];
    }

    public static function getEncodingValues()
    {
        return [
            self::ENCODING_CP1251 => \Yii::t('admin/import', 'Windows-1251'),
            self::ENCODING_UTF8 => \Yii::t('admin/import', 'UTF-8'),
        ];
    }

    public function attributeLabels()
    {
        return [
            'file' => \Yii::t('admin/import', 'Excel/CSV file'),
            'encoding' => \Yii::t('admin/import', 'Encoding'),
            'fields' => \Yii::t('admin/import', 'Fields'),
            'skipFirstLine' => \Yii::t('admin/import', 'Skip first line'),
            'separator' => \Yii::t('admin/import', 'Separator'),
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
        $this->_errorMessage = null;
        $this->_errorRow = [];

        $transaction = \Yii::$app->db->beginTransaction();
        try {
            if ($this->validate() && $this->beforeImport()) {

                if (FileHelper::getMimeTypeByExtension($this->file->extension) == 'text/csv') {
                    $this->importCsv();
                } else {
                    $this->importExcel();
                }

                $this->afterImport();

                $transaction->commit();

            } else {
                return false;
            }

        } catch (InterruptImportException $e) {
            $transaction->rollBack();
            $this->_errorMessage = $e->getMessage();
            $this->_errorRow = $e->row;
            return false;
        }

        return true;

    }

    protected function beforeImport()
    {
        if ($this->beforeImport === null) {
            return true;
        }

        return call_user_func($this->beforeImport, $this);
    }

    private function importCsv()
    {
        $this->_importCounter = 0;
        $this->_skippedRows = [];

        $lexer = $this->getLexer();
        $interpreter = $this->getInterpreter();

        $interpreter->addObserver(function ($row) {
            try {
                $row = $this->compose($row);
                $this->rowImport($row);
                $this->_importCounter++;
            } catch (SkipRowException $e) {
                $row = $e->row ? $e->row : $row;
                $this->_skippedRows[] = [$e->getMessage(), $row];
            } catch (InterruptImportException $e) {
                $e->row = $e->row ? $e->row : $row;
                throw $e;
            }
        });

        $lexer->parse($this->file->tempName, $interpreter);
    }

    /**
     * @return Lexer
     */
    protected function getLexer()
    {
        $lexer = new Lexer((new LexerConfig())
            ->setDelimiter($this->separator)
            ->setIgnoreHeaderLine($this->skipFirstLine)
            ->setFromCharset($this->encoding)
            ->setToCharset('utf8')
        );
        return $lexer;
    }

    /**
     * @return Interpreter
     */
    protected function getInterpreter()
    {
        $interpreter = new Interpreter();
        $interpreter->unstrict();
        return $interpreter;
    }

    /**
     * @param array $row
     * @return array
     */
    protected function compose($row)
    {
        $result = [];
        foreach ($this->fieldsArray as $id => $name) {
            if ($name == self::SKIP_FIELD_NAME) {
                continue;
            }
            if (isset($row[$id])) {
                $result[$name] = $row[$id];
            } else {
                $result[$name] = null;
            }
        }
        return $result;
    }

    protected function rowImport($row)
    {
        if ($this->rowImport === null) {
            return true;
        }

        return call_user_func($this->rowImport, $this, $row);
    }

    /**
     * @throws InterruptImportException
     * @throws \Exception
     */
    private function importExcel()
    {
        $this->_importCounter = 0;
        $this->_skippedRows = [];

        try {
            $objPhpExcel = \PHPExcel_IOFactory::load($this->file->tempName);
            $worksheet = $objPhpExcel->getActiveSheet();

            $highestRow = $worksheet->getHighestRow();
            $highestColumn = $worksheet->getHighestColumn();
            $highestColumnIndex = \PHPExcel_Cell::columnIndexFromString($highestColumn);

            for ($row = 0; $row < $highestRow; $row++) {
                $dataRow = [];
                for ($col = 0; $col < $highestColumnIndex; $col++) {
                    $cell = $worksheet->getCellByColumnAndRow($col, $row+1);
                    $dataRow[] = $cell->getValue();
                }
                try {
                    $dataRow = $this->compose($dataRow);
                    $this->rowImport($dataRow);
                    $this->_importCounter++;
                } catch (SkipRowException $e) {
                    $dataRow = $e->row ? $e->row : $dataRow;
                    $this->_skippedRows[] = [$e->getMessage(), $dataRow];
                } catch (InterruptImportException $e) {
                    $e->row = $e->row ? $e->row : $dataRow;
                    throw $e;
                }
            }
        } catch (\PHPExcel_Reader_Exception $e) {
            throw new InterruptImportException('Ошибка при импорте файла: ' . $e->getMessage());
        }
    }

    protected function afterImport()
    {
        if ($this->afterImport === null) {
            return;
        }

        call_user_func($this->afterImport, $this);
    }

    /**
     * @return array
     */
    public function getFieldsArray()
    {
        if (!array_key_exists($this->fields, $this->_fieldsArray)) {
            $this->_fieldsArray[$this->fields] = preg_split('/\s*[,;]\s*/', $this->fields, -1, PREG_SPLIT_NO_EMPTY);
        }
        return $this->_fieldsArray[$this->fields];
    }

    /**
     * @return int
     */
    public function getImportCounter()
    {
        return $this->_importCounter;
    }

    /**
     * @return string
     */
    public function getErrorMessage()
    {
        return $this->_errorMessage;
    }

    public function hasErrorMessage()
    {
        return $this->_errorMessage !== null;
    }

    /**
     * @return array
     */
    public function getErrorRow()
    {
        return $this->_errorRow;
    }

    /**
     * @return array
     */
    public function getSkippedRows()
    {
        return $this->_skippedRows;
    }

    /**
     * @return bool
     */
    public function hasSkippedRows()
    {
        return count($this->_skippedRows) > 0;
    }
}