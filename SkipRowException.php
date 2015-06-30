<?php

namespace yz\admin\import;
use yii\base\Exception;


/**
 * Class SkipRowException
 */
class SkipRowException extends Exception
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