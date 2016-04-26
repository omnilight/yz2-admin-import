<?php

namespace yz\admin\import;

use yii\base\Component;
use yii\helpers\FileHelper;


/**
 * Class ImportCommand
 */
class ImportCommand extends Component implements ImporterInterface
{
    use Importer;

    /**
     * File to import
     * @var string
     */
    public $file;

    /**
     * @var callable
     */
    public $output;

    /**
     * Returns path to the imported file
     * @return string
     */
    public function getImportedFileName()
    {
        return $this->file;
    }

    public function handle()
    {
        $this->initImporter();
        $this->processImport();
        if (is_callable($this->output)) {
            call_user_func($this->output, $this);
        }
    }

    /**
     * Returns MIME of the file
     * @return string
     */
    public function getImportedMime()
    {
        return FileHelper::getMimeTypeByExtension($this->file);
    }
}