<?php

namespace App\core;


class Auxiliares
{

    const TIPO_NOTIFICACAO = 7;

    public static function getDataAtual()
    {
        return date('Y-m-d');
    }
}
