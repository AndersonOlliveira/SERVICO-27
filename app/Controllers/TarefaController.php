<?php

namespace App\Controllers;

use App\Models\Tarefa;
use App\Views\TarefaView;
use App\Models\ListaTratativa;
use App\Utilis\Process_tratamento;
use App\Core\MailClass;


class TarefaController
{
    // private Tarefa $model;
    protected $modelos;
    protected $utilitarios;
    protected $email;

    public function __construct()
    {
        // $this->model = new Tarefa();
        // $this->view = new TarefaView();
        $this->modelos = new ListaTratativa();
        $this->utilitarios = new Process_tratamento();
        $this->email = new MailClass();
    }

    public function processar_dados(): void
    {
        echo "<pre>";

        print_r('ESTOU SAINDO AQUI');

        $cobranca = NULL;
        $mes = '06-2026';
        $data_inicio = NULL;
        $data_fim = NULL;
        $retorno = $this->modelos->getRelatorio_origim_list($cobranca, $mes, $data_fim, $data_fim);

        echo "<pre>";

        print_R($retorno);

        die();
        if (!empty($retorno)) {

            foreach ($retorno as $result) {

                if (!empty($result['n_nro'])) {
                    $this->utilitarios->verifry_cobraca($result['n_nro']);
                }
            }
        }
    }

    public function processar_dados_email()
    {

        $retorno = $this->modelos->lista_notification();

        // echo "<pre>";

        // print_R($retorno);

        //TESTE DE ENVIAR O EMAIL 
        $destinatario = $_ENV['SMTP_USER'];
        $assunto = 'TESTE DE ENVIO';
        $corpo = 'NOTIFICACA';

        $this->email->enviar_email($destinatario, $assunto, $corpo, $altBody = null);
    }
}
