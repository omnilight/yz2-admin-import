<?php

namespace yz\admin\import;

use Goodby\CSV\Import\Standard\Interpreter;
use Goodby\CSV\Import\Standard\Lexer;
use Goodby\CSV\Import\Standard\LexerConfig;
use yii\base\InvalidCallException;
use yii\base\InvalidConfigException;
use yii\base\Model;
use yii\helpers\FileHelper;
use yii\helpers\Json;
use yii\web\UploadedFile;


/**
 * Class ImportForm
 *
 * @property array $fieldsArray
 * @property int $importCounter
 */
class ImportForm extends Model implements ImporterInterface
{
    use Importer;

    /**
     * @var BatchImportAction
     */
    public $action;
    /**
     * @var UploadedFile
     */
    public $file;
    /**
     * List of extensions that are allowed
     * @var array
     */
    protected $allowedExtensions = ['csv', 'xls', 'xlsx'];

    public function init()
    {
        parent::init();
        $this->initImporter();
    }

    public function rules()
    {
        return [
            [['file', 'encoding', 'fields', 'separator',], 'required'],
            [['file'], 'file', 'extensions' => $this->allowedExtensions, 'checkExtensionByMimeType' => false],
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
        return $this->processImport();
    }

    /**
     * Returns path to the imported file
     * @return string
     */
    public function getImportedFileName()
    {
        return $this->file->tempName;
    }
}