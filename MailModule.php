<?php
namespace Grout\Cyantree\MailModule;

use Cyantree\Grout\App\Module;
use Cyantree\Grout\Event\Event;
use Cyantree\Grout\Mail\Mail;
use Cyantree\Grout\Tools\StringTools;
use Grout\Cyantree\MailModule\Types\MailConfig;

class MailModule extends Module
{
    const MODE_SEND = 'send';
    const MODE_DIRECTORY = 'directory';

    /** @var MailConfig */
    public $moduleConfig;

    private $count = 0;

    public function init()
    {
        $this->app->configs->setDefaultConfig($this->id, new MailConfig());
        $this->moduleConfig = $this->app->configs->getConfig($this->id);

        $this->app->events->join('mail', array($this, 'onMail'));

        $this->addRoute('', 'Pages\MailPage');
    }

    private $directory;

    /** @param Event $event */
    public function onMail($event)
    {
        /** @var $mail Mail */
        $mail = $event->data;

        if (!$mail->from) {
            $mail->from = $this->moduleConfig->from;
        }

        if (!$mail->returnPath) {
            $mail->returnPath = $this->moduleConfig->returnPath;
        }

        $mode = $this->moduleConfig->mode;

        // Send mails
        if ($mode === self::MODE_SEND) {
            if ($to = $this->moduleConfig->to) {
                $mail->subject = '[DEBUG for ' . print_r($mail->recipients, true) . '] ' . $mail->subject;
                $mail->recipients = $to;
                $mail->recipientsCc = $mail->recipientsBcc = null;
            }

            $mail->send();

            // Debug to directory
        } elseif ($mode === self::MODE_DIRECTORY) {
            if (!$this->directory) {
                $this->directory = $this->app->parseUri($this->moduleConfig->directory);
            }

            $this->count++;

            $t = time();
            $text = 'DATE: ' . date('Y-m-h H:i:s', $t) . chr(10) .
                  'TO: ' . json_encode($mail->recipients) . chr(10) .
                  ($mail->recipientsCc ? 'CC: ' . json_encode($mail->recipientsCc) . chr(10) : '') .
                  ($mail->recipientsBcc ? 'BCC: ' . json_encode($mail->recipientsBcc) . chr(10) : '') .
                  ($mail->replyTo ? 'REPLY-TO: ' . json_encode($mail->replyTo) . chr(10) : '') .
                  'SUBJECT: ' . $mail->subject . chr(10) .
                  'FROM: ' . json_encode($mail->from) . chr(10) .
                  ($mail->headers ? json_encode($mail->headers) . chr(10) : '') .
                  ($mail->returnPath ? 'RETURN-PATH: ' . $mail->returnPath . chr(10) : '') .
                  chr(10) .
                  $mail->text;

            $file = trim(
                $this->directory . date('Y-m-d-H-i-s', $t) . ' - '
                . str_pad($this->count, 4, '0', STR_PAD_LEFT)
                . ' - ' . StringTools::toUrlPart($mail->subject)
            );

            file_put_contents($file . '.txt', $text);

            if ($mail->htmlText) {
                file_put_contents($file . '.htm', $mail->htmlText);
            }
        }
    }
}
