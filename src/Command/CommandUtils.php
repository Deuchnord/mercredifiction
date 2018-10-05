<?php
/**
 * Created by PhpStorm.
 * User: jerome
 * Date: 05/07/2018
 * Time: 16:36.
 */

namespace App\Command;

use Symfony\Component\Console\Style\SymfonyStyle;

class CommandUtils
{
    public static function writeError(SymfonyStyle $io, string $message, \Exception $e = null)
    {
        $str = $message;

        if (null != $e) {
            $str .= "\nError was: ".$e->getMessage().' in '.$e->getFile().' at line '.$e->getLine();
        }

        $io->error($str);
    }
}
