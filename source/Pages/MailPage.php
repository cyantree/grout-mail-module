<?php
namespace Grout\Cyantree\MailModule\Pages;

use Cyantree\Grout\App\Page;
use Cyantree\Grout\App\Types\ContentType;
use Cyantree\Grout\Tools\FileTools;
use Cyantree\Grout\Tools\StringTools;
use Cyantree\Grout\Ui\Ui;
use Grout\Cyantree\MailModule\MailModule;

class MailPage extends Page
{
    public function parseTask()
    {
        /** @var $m MailModule */
        $m = $this->task->module;

        $mode = $this->request()->get->get('mode');
        $status = null;

        if ($m->moduleConfig->mode != MailModule::MODE_DIRECTORY) {
            $this->setResult('Directory logging is disabled.');
            return;
        }

        $directory = $m->moduleConfig->directory;

        $content = '';

        if ($mode == 'clear') {
            FileTools::deleteContents($directory);
            $status = 'All mails have been cleared.';

        } elseif ($mode == 'showhtml') {
            $id = $this->task->request->get->asString('mail')->asInput(128)->value;

            if (preg_match('!^[a-zA-Z0-9_+ -]+$!', $id) && is_file($directory . $id . '.htm')) {
                $this->setResult(file_get_contents($directory . $id . '.htm'), ContentType::TYPE_HTML_UTF8);
                return;

            } else {
                $content = 'Mail does not exist';
            }

        } elseif ($mode == 'show') {
            $id = $this->task->request->get->asString('mail')->asInput(128)->value;

            if (preg_match('!^[a-zA-Z0-9_+ -]+$!', $id)) {
                if ($id != 'latest' && !is_file($directory . $id . '.txt')) {
                    $content = 'Mail does not exist.';

                } else {
                    if ($id == 'latest') {
                        $files = scandir($directory);
                        $id = null;

                        foreach ($files as $file) {
                            if ($file == '.' || $file == '..' || !preg_match('!^(.*)\.txt$!', $file, $fileData)) {
                                continue;
                            }

                            $id = $fileData[1];
                        }
                    }

                    if (!$id) {
                        $content = 'Mail does not exist';

                    } else {
                        $content = StringTools::escapeHtml(file_get_contents($directory . $id . '.txt'));
                        $content = preg_replace('![a-zA-Z0-9]+://[^\s\(\)]+!', '<a href="\0" target="_blank">\0</a>', $content);
                        $content = '<pre>' . $content . '</pre>';

                        if (is_file($directory . $id . '.htm')) {
                            $content .= '<iframe src="?mode=showhtml&amp;mail=' . rawurlencode($id) . '" width="900" height="600"></iframe>';
                        }
                    }


                }
            } else {
                $content = 'Please enter a valid id.';
            }


        } else {
            $files = FileTools::listDirectory($directory, false);

            $mailList = array();

            foreach ($files as $file) {
                if (preg_match('!^(.+)\.([a-zA-Z0-9]+)$!', $file, $fileData)) {
                    if (in_array($fileData[2], array('txt', 'htm'))) {
                        $mailList[$fileData[1]] = true;
                    }
                }
            }

            krsort($mailList);

            $ui = new Ui();

            $content = '<ul>';

            foreach ($mailList as $mailId => $foo) {
                $content .= '<li>' . $ui->link('?mode=show&mail=' . rawurlencode($mailId), $mailId) . '</li>';
            }

            $content .= '</ul>';
        }

        $content = <<<CNT
<!DOCTYPE html>
<body>
<div>
<a href="?">Show mails</a>
<a href="?mode=show&amp;mail=latest">Show latest mail</a>
<a href="?mode=clear">Clear mails</a>
</div>
<div><strong>
{$status}
</strong></div>
<hr>
{$content}
</table>
</pre>
</body>
CNT;

        $this->task->response->postContent($content, ContentType::TYPE_HTML_UTF8);
    }
}