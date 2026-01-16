<?php

//declare(strict_types=1);

namespace cryodrift\mailviewer;

use cryodrift\mailviewer\db\Repository;
use cryodrift\mailviewer\lib\Pop3;
use cryodrift\user\AccountStorage;
use cryodrift\fw\cli\CliUi;
use cryodrift\fw\Context;
use cryodrift\fw\Core;
use cryodrift\fw\interface\Handler;
use cryodrift\fw\trait\CliHandler;

class Pop3Cli implements Handler
{
    use CliHandler;

    protected string $dir;

    public function __construct(Context $ctx, protected AccountStorage $store, string $storagedir)
    {
        //TODO get /mails/ from config
        $this->dir = $storagedir . $ctx->user() . '/mails/';
        Core::dirCreate($this->dir, false);
    }

    public function handle(Context $ctx): Context
    {
        return $this->handleCli($ctx);
    }

    /**
     * @cli Show a list of mailsend accounts
     */
    public function show(): array
    {
        $accounts = $this->store->load();
        $accounts = array_values($accounts);
        return Core::removeKeys(['password'], $accounts);
    }

    /**
     * @cli test the progress bar
     * @return void
     */
    protected function progress(): void
    {
        $list = explode(' ', str_pad(' ', 1000, "# #"));
        CliUi::withProgressBar($list, fn($i) => usleep(2));
    }

    /**
     * @cli read mails from server into directory as .eml files
     * @cli param: -id (account id from show)
     */
    public function read(string $id, Repository $db): array
    {
        //
        $accounts = $this->store->load();
        $accounts = array_values($accounts);
        $dir = $this->dir;
        $newmails = [];
        if (is_dir($dir) && file_exists($dir) && array_key_exists($id, $accounts)) {
            $account = (object)Core::getValue($id, $accounts);
            $dir = $dir . Core::cleanFilename(trim($account->name)) . '/';

            Core::dirCreate($dir, false);

            $files = Core::dirList($dir);
            $mailcount = (int)Core::getValue('mailcount', $db->mailcount($account->host), iterator_count($files), true);
            $pop = new Pop3($account->host, $account->name, $account->password);
            $serverfiles = $pop->msgnums();
            $diffcnt = $mailcount - count($serverfiles);
            $maillist = array_slice($serverfiles, $diffcnt);

            if ($diffcnt !== 0) {
                $db->mailcount($account->host, (string)count($serverfiles));
                // Process emails
                CliUi::withProgressBar($maillist, function ($info) use ($pop, $dir, &$newmails) {
                    $mail = $pop->fetchmail($info);
//                    $uid = DbCli::getUIDFromFile(Collector::memfile($mail));
                    $filename = $dir . md5($mail) . '.eml';
                    Core::fileWrite($filename, $mail);
                    $newmails[] = $filename;
                });
            }
            $pop->quit();
        }
        return $newmails;
    }

}
