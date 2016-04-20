<?php

namespace yz\admin\import;

use yii\base\Component;


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
}