<?php

namespace App\Controllers;

use Exception;
use App\Core\MailClass;
use App\Core\Functions;
use App\Core\Auxiliares;
use App\Models\ListaTratativa;
use App\Core\AppManipularError;
use App\Utilis\Process_tratamento;





class TarefaController
{
    // private Tarefa $model;
    protected $modelos;
    protected $utilitarios;
    protected $email;
    protected $funciton;
    protected $manipulador;
    protected $manipuladorInfo;

    public function __construct()
    {
        // $this->model = new Tarefa();
        // $this->view = new TarefaView();
        $this->funciton  = new Functions();
        $this->modelos = new ListaTratativa();
        $this->utilitarios = new Process_tratamento();
        $this->email = new MailClass();
        $this->manipulador = new AppManipularError(__DIR__ . '/../error/error');
        $this->manipuladorInfo = new AppManipularError(__DIR__ . '/../info/sistema.log');
    }

    public function processar_dados(): void
    {
        echo "<pre>";

        print_r('ESTOU SAINDO AQUI');

        $cobranca = NULL;
        $mes = '06-2026';
        $data_inicio = NULL;
        $data_fim = NULL;
        $retorno = $this->modelos->getRelatorio_origim_list($mes);

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

        $msg = [];
        $destino = [];
        $crtId = [];
        $idCobranca = [];
        $p_contato = [];

        $notificacao = false;

        $data_atual  = Auxiliares::getDataAtual();


        $retorno = $this->modelos->lista_notification();
        try {

            if (isset($retorno) && !isset($retorno['msg'])) {


                foreach ($retorno as $key => $value) {

                    if ($value['p_contato'] < $data_atual) {
                        $notificacao = true;

                        $msg[] = 'Foi marcado para que entrar em contato com o cliente ' . $value['cliente'] . ' referente á Fatura nº ' . $value['n_nro'] . ' com a data de vencimento ' . $value['vencimento'];

                        $destino[] = $value['res']['email'];
                        $crtId[] = $value['contratoresponsavel'];
                        $idCobranca[] = $value['n_nro'];
                        $p_contato[] = $value['p_contato'];
                    } else {

                        $notificacao = false;
                        $this->manipuladorInfo->manipuladorDeErros(1, 'Nenhuma Notificação encontrada para envio de e-mail. dados: Fatura nº ' . $value['n_nro'] . '- data configurada: ' . $value['p_contato'], __FILE__, __LINE__);
                    }
                }


                if ($notificacao) {

                    //ENVIAR O EMAIL 
                    $destinatario =  $destino[0];
                    $ctr_interno =  $crtId[0];
                    $idCobrancas = $idCobranca[0];
                    // $destinatario = $_ENV['SMTP_DESTINATION'];
                    $assunto = $_ENV['SMTP_SUBJECT'];
                    $corpo = implode("<br>", $msg);
                    $retorno_envio_email = $this->email->enviar_email($destinatario, $assunto, $corpo, $altBody = null);

                    if ($retorno_envio_email) {

                        $tipo_acoes =  Auxiliares::TIPO_NOTIFICACAO;
                        echo "<pre>";
                        print_R($tipo_acoes);
                        //   $this->modelos->insert_notification($corpo, $tipo_acoes, $ctr_interno, $idCobrancas, $p_contato[0]);
                    } else {

                        $this->manipulador->manipuladorDeErros(20, 'Erro ao enviar e-mail para o destinatário: ' . $destinatario, __FILE__, __LINE__);
                    }
                }
            } else {
                $this->manipuladorInfo->manipuladorDeErros(1, 'Nenhuma Notificação encontrada para envio de e-mail.' . $retorno['msg'], __FILE__, __LINE__);
            }
        } catch (Exception $e) {
            $this->manipulador->manipuladorDeErros(20, 'Erro ao processar dados para envio de e-mail: ' . $e->getMessage(), __FILE__, __LINE__);
        }
    }
}
