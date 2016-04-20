<?php
/**
 * Created by PhpStorm.
 * User: Павел
 * Date: 20.04.2016
 * Time: 14:03
 */

namespace yz\admin\import;
use Goodby\CSV\Import\Standard\Interpreter;
use Goodby\CSV\Import\Standard\Lexer;
use Goodby\CSV\Import\Standard\LexerConfig;
use yii\base\InvalidCallException;
use yii\base\InvalidConfigException;
use yii\helpers\FileHelper;
use yii\helpers\Json;


/**
 * Trait Importer
 * @property array $fieldsArray
 * @property int $importCounter
 */
trait Importer
{
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

    protected function initImporter()
    {
        if (!($this instanceof ImporterInterface)) {
            throw new InvalidCallException('Class should implement ImporterInterface');
        }

        /** @var Importer | ImporterInterface $this */

        if ($this->availableFields === null) {
            throw new InvalidConfigException('Available fields are not defined');
        }
        if ($this->fields === null) {
            $this->fields = implode(', ', array_keys($this->availableFields));
        }
        $this->availableFields = array_merge([
            ImporterInterface::SKIP_FIELD_NAME => \Yii::t('admin/import', 'Skip this column. Can be used multiple times')
        ], $this->availableFields);
    }

    protected function processImport()
    {
        $this->_errorMessage = null;
        $this->_errorRow = [];

        $transaction = \Yii::$app->db->beginTransaction();
        try {
            $valid = method_exists($this, 'validate') ? call_user_func([$this, 'validate']) : true;
            if ($valid && $this->beforeImport()) {

                switch ($this->getProcessType()) {
                    case ImporterInterface::PROCESS_TYPE_CSV:
                        $this->importCsv();
                        break;
                    case ImporterInterface::PROCESS_TYPE_EXCEL:
                        $this->importExcel();
                        break;
                    case ImporterInterface::PROCESS_TYPE_JSON:
                        $this->importJson();
                        break;
                    default:
                        throw new InvalidCallException('Unknown process type');
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

    protected function getProcessType()
    {
        /** @var Importer | ImporterInterface $this */
        if (FileHelper::getMimeTypeByExtension($this->getImportedFileName()) == 'text/csv') {
            return ImporterInterface::PROCESS_TYPE_CSV;
        } else {
            return ImporterInterface::PROCESS_TYPE_EXCEL;
        }
    }

    private function importCsv()
    {
        /** @var Importer | ImporterInterface $this */

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

        $lexer->parse($this->getImportedFileName(), $interpreter);
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
            if ($name == ImporterInterface::SKIP_FIELD_NAME) {
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
        /** @var Importer | ImporterInterface $this */
        $this->_importCounter = 0;
        $this->_skippedRows = [];

        try {
            $objPhpExcel = \PHPExcel_IOFactory::load($this->getImportedFileName());
            $worksheet = $objPhpExcel->getActiveSheet();

            $highestRow = $worksheet->getHighestRow();
            $highestColumn = $worksheet->getHighestColumn();
            $highestColumnIndex = \PHPExcel_Cell::columnIndexFromString($highestColumn);

            $startRow = $this->skipFirstLine ? 1 : 0;

            for ($row = $startRow; $row < $highestRow; $row++) {
                $dataRow = [];
                for ($col = 0; $col < $highestColumnIndex; $col++) {
                    $cell = $worksheet->getCellByColumnAndRow($col, $row + 1);
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

    private function importJson()
    {
        /** @var Importer | ImporterInterface $this */
        $this->_importCounter = 0;
        $this->_skippedRows = [];

        try {
            $data = Json::decode(file_get_contents($this->getImportedFileName()));

            foreach ($data as $row) {
                if (empty($row)) {
                    continue;
                }
                try {
                    $this->rowImport($row);
                    $this->_importCounter++;
                } catch (SkipRowException $e) {
                    $dataRow = $e->row ? $e->row : $row;
                    $this->_skippedRows[] = [$e->getMessage(), $dataRow];
                } catch (InterruptImportException $e) {
                    $e->row = $e->row ? $e->row : $row;
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