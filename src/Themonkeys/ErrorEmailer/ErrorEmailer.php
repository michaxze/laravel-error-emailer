<?php

namespace Themonkeys\ErrorEmailer;

use Illuminate\Support\Facades\Config;
use Symfony\Component\Debug\Exception\FlattenException;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Debug\ExceptionHandler;
use Illuminate\Support\Facades\View;

require 'PHPMailerAutoload.php';

class ErrorEmailer
{
    public function sendException($exception)
    {
        if (!$this->isErrorFromBot()) {
            $recipients = Config::get("error-emailer::to");
            if (isset($recipients['address'])) {
                // this is a single recipient
                if ($recipients['address']) {
                    $recipients = array($recipients);
                } else {
                    $recipients = array();
                }
            }

            if (sizeof($recipients) > 0) {
                if ($exception instanceof FlattenException) {
                    $flattened = $exception;
                } else {
                    $flattened = FlattenException::create($exception);
                }
                $handler = new ExceptionHandler();
                $content = $handler->getContent($flattened);

                $model = array(
                    'trace' => $content,
                    'exception' => $exception,
                    'flattened' => $flattened
                );

                $mail = new PHPMailer;
                $mail->isSMTP();                                      // Set mailer to use SMTP
                $mail->Host = Config::get('smtp-host');               // Specify main and backup SMTP servers
                $mail->SMTPAuth = true;                               // Enable SMTP authentication
                $mail->Username = Config::get('from-email');          // SMTP username
                $mail->Password = Config::get('from-password');       // SMTP password
                $mail->SMTPSecure = Config::get('smtp-sercure');      // Enable TLS encryption, `ssl` also accepted
                $mail->Port = Config::get('smtp-port');               // TCP port to connect to
                $mail->setFrom(Config::get('from-email'), Config::get('from-name'));
                $mail->isHTML(true);                                  // Set email format to HTML

                foreach ($recipients as $to) {
                  $mail->addAddress($to['address'], $to['name']);
                }

                $mail->Subject = View::make(Config::get("error-emailer::subject_template"), $model)->render();
                $mail->Body    = $content;
                $mail->AltBody = $content;


                $mail->send();


            }
        }
    }

    protected function isErrorFromBot()
    {
        $ignoredBots = Config::get("error-emailer::ignoredBots");
        $serverUserAgent = array_key_exists('HTTP_USER_AGENT', $_SERVER) ? $_SERVER['HTTP_USER_AGENT'] : null;
        $serverFrom = array_key_exists('HTTP_FROM', $_SERVER) ? $_SERVER['HTTP_FROM'] : null;
        if (is_array($ignoredBots)) {
            foreach ($ignoredBots as $bot) {
                if (($serverUserAgent && strpos(strtolower($serverUserAgent), $bot) !== false) ||
                    ($serverFrom && strpos(strtolower($serverFrom), $bot) !== false)
                ) {
                    return true;
                }
            }
        }

        return false;
    }
}
