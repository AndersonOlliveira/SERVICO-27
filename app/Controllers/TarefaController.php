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
        $data = date('d-m-Y', strtotime(Auxiliares::getDataAtual()));
        $retorno = $this->modelos->getRelatorio_origim_list($data);
        try {
            if (!empty($retorno)) {

                foreach ($retorno as $result) {

                    if (!empty($result['n_nro'])) {
                        $this->modelos->insert_notification(true, Auxiliares::TIPO_FATURA_VENCIDA, Auxiliares::TIPO_RESPONSAVEL, $result['n_nro'], Auxiliares::TIPO_P_CONTATO);
                    }
                }
            } else {
                $this->manipuladorInfo->manipuladorDeErros(1, 'Nenhuma Fatura encontrada para o dia ' . $data, __FILE__, __LINE__);
            }
        } catch (Exception $e) {

            $this->manipuladorInfo->manipuladorDeErros(5, 'Erro ao processar dados para o dia ' . $data . ' com a MSG: ' . $e->getMessage(), __FILE__, __LINE__);
        }
    }

    public function processar_dados_email()
    {
        $data_atual = Auxiliares::getDataAtual();
        $retorno = $this->modelos->lista_notification();

        try {
            if (isset($retorno) && !isset($retorno['msg'])) {

                $notificacoesPorDestinatario = [];

                foreach ($retorno as $value) {
                    // Verifica se a data de contato é igual  à data atual
                    if ($value['p_contato'] == $data_atual) {
                        $dataFormatada = date('d/m/Y', strtotime($value['vencimento']));
                        $mensagem = "Lembrete de contato: entrar em contato com o cliente <strong>{$value['cliente']}</strong> referente à Fatura nº <strong>{$value['n_nro']}</strong> com a data de vencimento {$dataFormatada}\n";

                        $email = 'anderson@proscore.com.br';
                        // $email = $value['res']['email'];

                        // Agrupa mensagens por destinatário
                        if (!isset($notificacoesPorDestinatario[$email])) {
                            $notificacoesPorDestinatario[$email] = [
                                'mensagens' => [],
                                'contratos' => [],
                                'cobrancas' => [],
                                'p_contato' => []
                            ];
                        }

                        $notificacoesPorDestinatario[$email]['mensagens'][] = $mensagem;
                        $notificacoesPorDestinatario[$email]['contratos'][] = $value['contratoresponsavel'];
                        $notificacoesPorDestinatario[$email]['cobrancas'][] = $value['n_nro'];
                        $notificacoesPorDestinatario[$email]['p_contato'][] = $value['p_contato'];
                    } else {
                        $this->manipuladorInfo->manipuladorDeErros(
                            1,
                            'Nenhuma Notificação encontrada para envio de e-mail. dados: Fatura nº ' . $value['n_nro'] . ' - data configurada: ' . $value['p_contato'],
                            __FILE__,
                            __LINE__
                        );
                    }
                }

                foreach ($notificacoesPorDestinatario as $destinatario => $dados) {
                    $assunto = $_ENV['SMTP_SUBJECT'];
                    $corpo = implode("<br>", $dados['mensagens']);

                    $retorno_envio_email = $this->email->enviar_email($destinatario, $assunto, $corpo);

                    if ($retorno_envio_email) {
                        $tipo_acoes = Auxiliares::TIPO_NOTIFICACAO;

                        // cobranças do destinatário
                        foreach ($dados['cobrancas'] as $index => $idCobranca) {
                            $ctr_interno = $dados['contratos'][$index];
                            $p_contato = $dados['p_contato'][$index];

                            $this->modelos->insert_notification(
                                Auxiliares::TIPO_P_CONTATO,
                                $tipo_acoes,
                                $ctr_interno,
                                $idCobranca,
                                $p_contato
                            );
                        }
                    } else {
                        $this->manipulador->manipuladorDeErros(
                            20,
                            'Erro ao enviar e-mail para o destinatário: ' . $destinatario,
                            __FILE__,
                            __LINE__
                        );
                    }
                }
            } else {
                $this->manipuladorInfo->manipuladorDeErros(
                    1,
                    'Nenhuma Notificação encontrada para envio de e-mail.' . $retorno['msg'],
                    __FILE__,
                    __LINE__
                );
            }
        } catch (Exception $e) {
            $this->manipulador->manipuladorDeErros(
                20,
                'Erro ao processar dados para envio de e-mail: ' . $e->getMessage(),
                __FILE__,
                __LINE__
            );
        }
    }


    public function processar_dados_email_old()
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

                $notificacoesPorDestinatario = [];
                foreach ($retorno as $key => $value) {

                    ///SE FOR A DATA IGUAL A DATA ATUAL, ENVIAR NOTIFICAÇÃO
                    if ($value['p_contato'] <= $data_atual) {
                        $notificacao = true;

                        $destino[] = $value['res']['email'];
                        $email = $value['res']['email'];

                        $dataFormatada = date('d/m/Y', strtotime($value['vencimento']));

                        // $msg[] = "Lembrete de contato: entrar em contato com o cliente <strong>{$value['cliente']}</strong> referente à Fatura nº  <strong>{$value['n_nro']}</strong> com a data de vencimento {$dataFormatada}\n";
                        $msg = "Lembrete de contato: entrar em contato com o cliente <strong>{$value['cliente']}</strong> referente à Fatura nº  <strong>{$value['n_nro']}</strong> com a data de vencimento {$dataFormatada}\n";
                        if (!isset($notificacoesPorDestinatario[$email])) {
                            $notificacoesPorDestinatario[$email] = [];
                        }
                        $notificacoesPorDestinatario[$email][] = $msg;
                        $destino[] = $value['res']['email'];
                        $crtId[] = $value['contratoresponsavel'];
                        $idCobranca[] = $value['n_nro'];
                        $p_contato[] = $value['p_contato'];
                    } else {

                        $notificacao = false;
                        $this->manipuladorInfo->manipuladorDeErros(1, 'Nenhuma Notificação encontrada para envio de e-mail. dados: Fatura nº ' . $value['n_nro'] . '- data configurada: ' . $value['p_contato'], __FILE__, __LINE__);
                    }
                }

                // foreach ($notificacoesPorDestinatario as $destinatario => $mensagens) {
                //     $assunto = $_ENV['SMTP_SUBJECT'];
                //     $corpo = implode("<br>", $mensagens);

                //     // Aqui você chama sua função de envio de e-mail
                //     $this->enviarEmail($destinatario, $assunto, $corpo);
                // }


                if ($notificacao) {

                    //ENVIAR O EMAIL 
                    // $destinatario =  $destino[0];
                    $ctr_interno =  $crtId[0];
                    $idCobrancas = $idCobranca[0];
                    $destinatario = $_ENV['SMTP_DESTINATION'];
                    $assunto = $_ENV['SMTP_SUBJECT'];
                    $corpo = implode("<br>", $msg);

                    $retorno_envio_email = $this->email->enviar_email($destinatario, $assunto, $corpo, $altBody = null);

                    if ($retorno_envio_email) {

                        $tipo_acoes =  Auxiliares::TIPO_NOTIFICACAO;

                        $this->modelos->insert_notification(Auxiliares::TIPO_P_CONTATO, $tipo_acoes, $ctr_interno, $idCobrancas, $p_contato[0]);
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
