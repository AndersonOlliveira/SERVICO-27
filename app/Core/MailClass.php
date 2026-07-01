<?php

namespace App\Core;

use App\Core\AppManipularError;
use App\Core\Functions;



use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;



class MailClass
{
    protected $manipulador;
    protected $function;

    public function __construct()
    {
        $this->manipulador = new AppManipularError(__DIR__ . '/../error/error');
        $this->function = new Functions();
        // throw new \Exception('Not implemented');
    }

    public function enviar_email($destinatario, $assunto, $corpo, $altBody = null)
    {
        $mail = new PHPMailer(true);
        try {
            //Server settings
            $mail->SMTPDebug = 2;
            $mail->isSMTP(); //Send using SMTP
            $mail->Host       = $_ENV['SMTP_HOST'];                     //Set the SMTP server to send through
            $mail->SMTPAuth   = true;                                   //Enable SMTP authentication
            $mail->Username   = $_ENV['SMTP_USER'];                     //SMTP username
            $mail->Password   = $_ENV['SMTP_PASSWORD'];                 //SMTP password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;        //Enable implicit TLS encryption
            $mail->Port       = $_ENV['SMTP_PORT'];                     //TCP port to connect to

            //Recipients
            $mail->setFrom($_ENV['SMTP_USER'], 'Mailer');
            $mail->addAddress($destinatario);
            //$mail->addCC($_ENV['SMTP_CC']); // add cc recpient

            //Content
            $corpo = mb_convert_encoding($corpo, 'UTF-8', 'auto');
            $assunto = mb_convert_encoding($assunto, 'UTF-8', 'auto');
            $mail->isHTML(true);                                  //Set email format to HTML
            $mail->Subject = $assunto;
            $mail->Body    = $corpo;
            $mail->AltBody = $altBody ? $altBody : strip_tags($corpo);
            $mail->CharSet = 'UTF-8';
            $mail->send();
            return true;
        } catch (Exception $e) {
            $this->manipulador->manipuladorDeErros(11, 'Erro em enviar e-mail: ' . $e->getMessage(), __FILE__, __LINE__);
            echo 'Message could not be sent.';
            echo 'Mailer Error: ' . $mail->ErrorInfo;
            return false;
        }
    }
}
