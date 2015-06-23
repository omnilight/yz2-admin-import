<?php

namespace yz\admin\import;
use yii\base\Exception;


/**
 * Class InterruptImportException
 */
class InterruptImportException extends Exception
{
    /**
     * @var array Row where error was happening
     */
    public $row;

    public function __construct($message = "", $row = [], $code = 0, \Exception $previous = null)
    {
        $this->row = $row;
        parent::__construct($message, $code, $previous);
    }


}