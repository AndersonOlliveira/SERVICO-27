<?php

namespace App\core;


class Auxiliares
{

    const TIPO_NOTIFICACAO = 7;
    const TIPO_FATURA_VENCIDA = 6;
    const TIPO_RESPONSAVEL = NULL;
    const TIPO_P_CONTATO = NULL;

    public static function getDataAtual()
    {
        return date('Y-m-d');
    }
}
