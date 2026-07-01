<?php


namespace App\Core;

use DateTime;

class Functions
{


    function converterData($data)
    {
        // tenta ano com 4 dígitos
        $date = DateTime::createFromFormat('d/m/Y', $data);

        // se falhar tenta com 2 dígitos
        if (!$date) {
            $date = DateTime::createFromFormat('d/m/y', $data);
        }

        return $date;
    }

    private static function utf8ize($data)
    {

        if (is_array($data)) {

            foreach ($data as $key => $value) {
                $data[$key] = self::utf8ize($value);
            }
        } elseif (is_string($data)) {

            return trim(mb_convert_encoding($data, "UTF-8", 'ISO-8859-1'));
        }
        return $data;
    }
    public static function convertEncode($data)
    {
        $data_utf8 = self::utf8ize($data);
        return $data_utf8;
    }
    private static function latin1ize($data)
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = self::latin1ize($value);
            }
        } elseif (is_string($data)) {
            // Converte de UTF-8 para ISO-8859-1 (Latin1)
            return trim(mb_convert_encoding($data, 'ISO-8859-1', 'UTF-8'));
        }
        return $data;
    }

    public static function convertToLatin1($data)
    {
        return self::latin1ize($data);
    }
}
