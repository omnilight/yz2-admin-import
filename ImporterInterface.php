<?php
/**
 * Created by PhpStorm.
 * User: Павел
 * Date: 20.04.2016
 * Time: 14:04
 */

namespace yz\admin\import;


/**
 * Interface ImporterInterface
 */
interface ImporterInterface
{
    const ENCODING_UTF8 = 'utf8';
    const ENCODING_CP1251 = 'cp1251';

    const SKIP_FIELD_NAME = 'skip';
    const PROCESS_TYPE_CSV = 'csv';
    const PROCESS_TYPE_EXCEL = 'excel';
    const PROCESS_TYPE_JSON = 'json';

    /**
     * Returns path to the imported file
     * @return string
     */
    public function getImportedFileName();
}