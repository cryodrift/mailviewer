<?php

//declare(strict_types=1);

namespace cryodrift\mailviewer;

use cryodrift\fw\Main;
use cryodrift\mailviewer\db\Repository;
use cryodrift\mailviewer\lib\Parser;
use cryodrift\mailviewer\ui\shared\search\Comp as SearchComponent;
use cryodrift\fw\Config;
use cryodrift\fw\Context;
use cryodrift\fw\Core;
use cryodrift\fw\FakeFileInfo;
use cryodrift\fw\FileHandler;
use cryodrift\fw\HtmlUi;
use cryodrift\fw\interface\Handler;
use cryodrift\fw\trait\WebHandler;


class Api implements Handler
{
    use WebHandler;


    public function __construct(protected Repository $db, protected Config $config)
    {
        $this->outHelperAttributes([
            'ROUTE' => '/mail'
          ]
        );
    }

    public function handle(Context $ctx): Context
    {
        HtmlUi::setUistate(Core::getValue('getvar_uistate', $this->config, []));
        if (Core::getValue('cache_templates', $this->config, false)) {
            HtmlUi::cache();
            $ctx->response()->addAfterRunner(fn() => HtmlUi::cache());
        }
        $ctx->request()->setDefaultVars(Core::getValue('getvar_defaults', $this->config, []));
        $this->methodname = 'command';
        return $this->handleWeb($ctx);
    }


    /**
     * @web domains HtmlUi
     */
    protected function domains(Context $ctx, string $domains_id = '', int $domains_page = 0, string $domain_search = ''): HtmlUi
    {
        $data = $this->db->domains($domains_page, $domain_search);
        if ($data) {
            $data = HtmlUi::makeActive($data, $domains_id, 'domain');
            $data = HtmlUi::addQuery($ctx, $data, ['domain' => 'domains_id'], ['domain_search', 'domains_page']);
            return HtmlUi::fromFile('mailviewer/ui/domains/domains_block.html')->fromBlock('domains_block')
              ->setAttributes(['domains' => $data, ...$ctx->request()->vars()]);
        } else {
            return HtmlUi::fromString('');
        }
    }

    /**
     * @web messages HtmlUi
     */
    protected function messages(Context $ctx, string $domains_id, int $messages_page = 0, string $messages_id = '', string $domain_search = '', string $message_search = ''): HtmlUi
    {
        $data = $this->db->messages($domains_id, $messages_page, $domain_search, $message_search);
        if ($data) {
            $data = HtmlUi::makeActive($data, $messages_id);
            $data = HtmlUi::addQuery($ctx, $data, ['id' => 'messages_id'], ['domains_id', 'messages_page', 'domains_page', 'domain_search', 'message_search']);
            return HtmlUi::fromFile('mailviewer/ui/messages/messages_block.html')->fromBlock('messages_block')
              ->setAttributes(['messages' => $data, ...$ctx->request()->vars()]);
        } else {
            return HtmlUi::fromString('');
        }
    }

    /**
     * @web headers HtmlUi
     */
    protected function headers(Context $ctx): HtmlUi
    {
        $message_id = $ctx->request()->vars('messages_id');
        $data = $this->db->headers($message_id);
        if ($data) {
            return HtmlUi::fromFile('mailviewer/ui/email/headers_block.html')->fromBlock('headers', true)
              ->setAttributes(['headers' => $data]);
        } else {
            return HtmlUi::fromString('');
        }
    }

    /**
     * @web message HtmlUi
     */
    protected function message(Context $ctx): HtmlUi
    {
        $message_id = $ctx->request()->vars('messages_id');
        $part_id = $ctx->request()->vars('parts_id');
        $id = $message_id;
        if ($part_id) {
            $id = $part_id;
        }

        $data = $this->db->message($id);
        if ($data) {
            $parentmsg = $this->db->message($message_id);
            $data[Parser::TYPE_SUBJECT] = Core::getValue(Parser::TYPE_SUBJECT, $data, Core::getValue(Parser::TYPE_SUBJECT, $parentmsg), true);
            $data[Parser::TYPE_DATE] = Core::getValue(Parser::TYPE_DATE, $data, Core::getValue(Parser::TYPE_DATE, $parentmsg), true);
            $addr = $this->db->addresses($message_id);
            $data['messageadr'] = $addr;
            return HtmlUi::fromFile('mailviewer/ui/email/headers_message.html')->fromBlock('message')
              ->setAttributes($data);
        } else {
            return HtmlUi::fromString('');
        }
    }

    /**
     * @web part iframe HtmlUi
     */
    protected function partview(Context $ctx): HtmlUi
    {
        $message_id = $ctx->request()->vars('messages_id');
        $part_id = $ctx->request()->vars('parts_id');
        $content_type = $ctx->request()->vars('content_type', 'text');

        if ($part_id) {
            $message = $this->db->message($part_id);
        } else {
            $parts = $this->db->parts($message_id);
            $parts = array_filter($parts, fn($a) => $a['shorttype'] === $content_type);
            $message = array_pop($parts);
//            Core::echo($message);
        }
        Core::echo(__METHOD__, $message_id, $part_id, $content_type, $message);
        if ($message) {
            $data = HtmlUi::addQuery($ctx, $message, ['id' => 'parts_id'], ['messages_id']);
            //TODO dont send caching headers for pdf
            $data['query'] .= '&v=' . md5(microtime());
            return HtmlUi::fromFile('mailviewer/ui/email/partview_block.html')->fromBlock('partview')
              ->setAttributes($data);
        }
        return HtmlUi::fromString('');
    }

    /**
     * @web parts listing HtmlUi
     */
    protected function parts(Context $ctx): HtmlUi
    {
        $message_id = $ctx->request()->vars('messages_id');

        if ($message_id) {
            $data = $this->db->parts($message_id);

            if ($data) {
                $data = ['parts' => $data];
                $data = HtmlUi::addQuery($ctx, $data, ['id' => 'parts_id', 'shorttype' => 'content_type'], ['domains_id', 'messages_page', 'domains_page', 'domain_search', 'message_search', 'messages_id']);
                return HtmlUi::fromFile('mailviewer/ui/email/parts_block.html')->fromBlock('parts', true)
                  ->setAttributes($data);
            }
        }
        return HtmlUi::fromString('');
    }


    /**
     * @web message search form HtmlUi
     */
    protected function message_search(Context $ctx): HtmlUi
    {
        return new SearchComponent($ctx, 'message_search', 'messages', ['domains_id', 'message_search']);
    }

    /**
     * @web domain search form HtmlUi
     */
    protected function domain_search(Context $ctx): HtmlUi
    {
        return new SearchComponent($ctx, 'domain_search', 'domains messages', ['domain_search'], array_map(fn($a) => $a ? '=' . $a : '', $this->db::DATENAMES));
    }

    /**
     * @web cid content for html mailbody media
     */
    protected function cid(Context $ctx): Context
    {
        $messages_id = $ctx->request()->vars('messages_id');
        $cid = $ctx->request()->vars('cid', '');
        $parts = $this->db->parts($messages_id);
        $part = array_filter($parts, fn($a) => str_contains(Core::getValue(Parser::TYPE_CONTENTID, $a, '', true), $cid));
        $ctx->request()->setVar('parts_id', Core::getValue('id', array_pop($part)));
        return $this->partcontent($ctx);
    }

    /**
     * @web part iframe src content HtmlUi
     */
    protected function partcontent(Context $ctx): Context
    {
        $id = $ctx->request()->vars('parts_id');
        $messages_id = $ctx->request()->vars('messages_id');
        $message = $this->db->message($id);
        $content = $this->db->content($id);
        Core::echo(__METHOD__, $id, $messages_id, count($message), count($content), $message);
        $base = HtmlUi::fromString('');
        $encoding = Core::getValue(Parser::TYPE_ENCODING, $message);
        if ($message && $content) {
            switch ($encoding) {
                case Parser::ENCODING_BASE64:
                    $data = base64_decode($content[Parser::TYPE_CONTENT], true);
                    if (!$data) {
                        $data = $content[Parser::TYPE_CONTENT];
                    }
                    break;
                case Parser::ENCODING_QUOTEDPRINTABLE:
//                    $data = quoted_printable_decode($content[Parser::TYPE_CONTENT]);
                    $data = $content[Parser::TYPE_CONTENT];
                    break;
                case Parser::ENCODING_8BIT:
                case Parser::ENCODING_7BIT:
                case Parser::ENCODING_BIN:
                    // if we have problems (wrong character encoding) with it figure out whats wrong with the mail
                    $data = $content[Parser::TYPE_CONTENT];
//                $data = iconv('UTF-8', 'US-ASCII//TRANSLIT//IGNORE', $data);
//                $data=quoted_printable_decode($data);

//                    $data = mb_decode_mimeheader($content[Parser::TYPE_CONTENT]);
                    break;
                default:
                    // add unknown encoding to begin for debug
                    $data = Core::getValue(Parser::TYPE_ENCODING, $message);
                    $data .= $content[Parser::TYPE_CONTENT];
            }

            $type = strtolower(Core::getValue(Parser::TYPE_TYPE, $message));

            switch (true) {
                case $type === Parser::CONTENTTYPE_TEXT . 'html':
                    $data = mb_convert_encoding($data, 'UTF-8', $message['charset']);
//                                        $data = html_entity_decode($data);
                    $data = str_replace('src="cid:', 'src="/mail/api/cid/?messages_id=' . $messages_id . '&cid=', $data);
                    $base = HtmlUi::fromString($data);
                    break;
                case str_contains($type, 'text/calendar'):
                case $type === Parser::CONTENTTYPE_TEXT . 'plain':
//                    Core::echo(__METHOD__ . 'X', 'type', $type, Parser::CONTENTTYPE_TEXT . 'plain');
                    if ($encoding === Parser::ENCODING_7BIT) {
                        $data = quoted_printable_decode($data);
                    } else {
                        $data = mb_convert_encoding($data, 'UTF-8', $message['charset']);
                    }
                    $data = html_entity_decode($data);
                    $data = '<pre>' . $data . '</pre>';
                    $base = HtmlUi::fromString($data);
                    break;
                case str_contains($type, 'application/'):
                case str_contains($type, 'image/'):
                    $file = new FakeFileInfo(-1);
                    $file->fwrite($data);
                    $pdf = substr($data, 0, 4);
                    $fext = Core::getValue(1, explode('/', $type, 2));
                    // some emails have wrong content types then send plaintext
                    if ($fext === 'pdf' && $pdf !== '%PDF') {
                        $base = HtmlUi::fromString($data);
                    } else {
                        $filename = Core::getValue(Parser::TYPE_FILENAME, $message);
                        if ($fext === 'octet-stream') {
                            $parts = explode('.', $filename);
                            $fext = array_pop($parts);
                        }
                        $file->setFextension($fext);
                        $headers = FileHandler::getHeaders($file, $this->config['cacheDuration']);
                        $h = FileHandler::getDownloadHeader($type, $filename);
//                    Core::echo(__METHOD__, $fext, $pdf, $headers, $h, Core::removeKeys(['content'], $message));
//                    exit;
                        $ctx->response()->setHeaders([...$headers, ...$h]);
                        $base = HtmlUi::fromString($data);
                    }
                    break;
            }
        }
        $ctx->response()->setContent($base);
        return $ctx;
    }

    /**
     * @web fetch emails from server
     */
    protected function refresh(Context $ctx, Cli $cli, Cache $cache, DbCli $dbcli, Pop3Cli $pop3cli): HtmlUi
    {
        $alert = HtmlUi::fromFile(Main::$rootdir . '/src/qmemo/ui/shared/alert_fadeout.html');
        if ($ctx->request()->isPost()) {
            $out = $cli->fetch($ctx, $cache, $pop3cli, $dbcli)->response()->getData();
//            Core::echo(__METHOD__, $out);
            $alert->setAttributes(['type' => 'success', 'text' => Core::toLog('Downloaded ' . count($out), $out)]);
        } else {
            $alert->setAttributes(['type' => 'success', 'text' => '']);
        }
        return $alert;
    }


}
