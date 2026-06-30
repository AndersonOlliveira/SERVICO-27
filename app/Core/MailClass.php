<?php

namespace App\Core;

use App\Core\AppManipularError;



use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;



class MailClass
{
    protected $manipulador;

    public function __construct()
    {
        $this->manipulador = new AppManipularError(__DIR__ . '/../error/error');
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

            //Content
            $mail->isHTML(true);                                  //Set email format to HTML
            $mail->Subject = $assunto;
            $mail->Body    = $corpo;
            $mail->AltBody = $altBody ?? strip_tags($corpo);

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
