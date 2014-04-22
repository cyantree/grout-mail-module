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

    public function init()
    {
        $this->app->configs->setDefaultConfig($this->id, new MailConfig());
        $this->moduleConfig = $this->app->configs->getConfig($this->id);

        $this->app->events->join('mail', array($this, 'onMail'));

        $this->addRoute('', 'Pages\MailPage');
    }

    private $_directory;

    /** @param Event $event */
    public function onMail($event)
    {
        /** @var $mail Mail */
        $mail = $event->data;

        if (!$mail->from) {
            $mail->from = $this->moduleConfig->from;
        }

        $mode = $this->moduleConfig->mode;

        // Send mails
        if ($mode === self::MODE_SEND) {
            if ($to = $this->moduleConfig->to) {
                $mail->subject = '[DEBUG for ' . print_r($mail->recipients, true) . '] '.$mail->subject;
                $mail->recipients = $to;
                $mail->recipientsCc = $mail->recipientsBcc = null;
            }

            $mail->send();

            // Debug to directory
        } elseif ($mode === self::MODE_DIRECTORY) {
            if (!$this->_directory) {
                $this->_directory = $this->app->parseUri($this->moduleConfig->directory);
            }

            $t = time();
            $text = 'DATE: ' . date('Y-m-h H:i:s', $t) . chr(10) .
                  'TO: ' . json_encode($mail->recipients) . chr(10) .
                  ($mail->recipientsCc ? 'CC: ' . json_encode($mail->recipientsCc) . chr(10) : '') .
                  ($mail->recipientsBcc ? 'BCC: ' . json_encode($mail->recipientsBcc) . chr(10) : '') .
                  'SUBJECT: ' . $mail->subject . chr(10) .
                  'FROM: ' . $mail->from . chr(10) .
                  ($mail->returnPath ? 'RETURN-PATH: ' . $mail->returnPath . chr(10) : '') .
                  chr(10) .
                  $mail->text;

            $file = $this->_directory . date('y-m-d H-i-s', $t) . ' - ' . StringTools::toUrlPart($mail->subject) . ' - ' . StringTools::random(4);

            file_put_contents($file . '.txt', $text);

            if ($mail->htmlText) {
                file_put_contents($file . '.htm', $mail->htmlText);
            }
        }
    }
}