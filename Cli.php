<?php

//declare(strict_types=1);

namespace cryodrift\mailviewer;

use cryodrift\fw\Context;
use cryodrift\fw\Core;
use cryodrift\fw\FileCache;
use cryodrift\fw\interface\Handler;
use cryodrift\fw\interface\Installable;
use cryodrift\fw\trait\CliHandler;

class Cli implements Handler, Installable
{
    use CliHandler;


    /**
     * @cli database tools
     * @param Context $ctx
     */
    protected function db(Context $ctx)
    {
        $ctx->request()->shiftArgs();
        return Core::newObject(DbCli::class, $ctx)->handle($ctx);
    }

    public function handle(Context $ctx): Context
    {
        return $this->handleCli($ctx);
    }

    /**
     * @cli mbox tools
     * @param Context $ctx
     */
    protected function mbox(Context $ctx): Context
    {
        $ctx->request()->shiftArgs();
        return Core::newObject(MboxCli::class, $ctx)->handle($ctx);
    }

    /**
     * @cli pop3 tools
     * @param Context $ctx
     */
    protected function pop3(Context $ctx): Context
    {
        $ctx->request()->shiftArgs();
        return Core::newObject(Pop3Cli::class, $ctx)->handle($ctx);
    }

    /**
     * @cli fetch new emails from all accounts into db
     * @param Context $ctx
     */
    public function fetch(Context $ctx, Cache $cache, Pop3Cli $pop3, DbCli $db): Context
    {
        $files = [];
        foreach ($pop3->show() as $key => $account) {
            $data = $pop3->read(...Core::getParams($pop3, 'read', ['id' => (string)$key], $ctx));
            $files = array_merge($files, $data);
        }
        if (count($files)) {
//            Core::echo(__METHOD__, $files);
            foreach ($files as $file) {
                $db->import($ctx, $file);
                $cache->clear();
            }
            $ctx->response()->setData($files);
        }
        return $ctx;
    }

    /**
     * @cli clears the mailsend ui cache
     */
    protected function clearcache(Cache $cache): string
    {
        $cache->clear();
        return 'Cache cleared';
    }

    /**
     * @cli installer
     */
    public function install(Context $ctx): array
    {
        $out = [];
        $ctx->request()->setParam('a', true);
        $out[] = Core::newObject(DbCli::class, $ctx)->handle($ctx, 'create');
        return $out;
    }
}
