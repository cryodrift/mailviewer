<?php

//declare(strict_types=1);

namespace cryodrift\mailviewer;

use cryodrift\fw\Main;
use cryodrift\mailviewer\db\Repository;
use cryodrift\mailviewer\lib\Collector;
use cryodrift\mailviewer\lib\Parser;
use cryodrift\mailviewer\lib\Parser2;
use cryodrift\fw\cli\CliUi;
use cryodrift\fw\cli\Colors;
use cryodrift\fw\Config;
use cryodrift\fw\Context;
use cryodrift\fw\Core;
use cryodrift\fw\interface\Handler;
use cryodrift\fw\interface\Testable;
use cryodrift\fw\tool\DbHelperStatic;
use cryodrift\fw\trait\CliHandler;

class DbCli implements Handler, Testable
{
    use CliHandler;

    public function __construct(private Repository $db, private Config $config)
    {
    }

    public function handle(Context $ctx, string $methodname = ''): Context
    {
        return $this->handleCli($ctx, $methodname);
    }

    /**
     * @cli create schema
     * @cli params: -a, -s,-i,-v all, schema, indexes, views
     */
    protected function create(bool $a = false, bool $s = false, bool $i = false, bool $v = false): string
    {
        if ($a) {
            $s = $i = $v = $a;
        }
        $out = '';
        if ($s) {
            $out .= $this->db->migrate();
        }
        if ($i) {
            $out .= $this->db->migrate('c_indexes.sql');
        }
        if ($v) {
            $out .= $this->db->migrate('c_views.sql');
        }

        if ($out) {
            return $out . ' Done';
        } else {
            return '';
        }
    }

    /**
     * @cli run tests
     */
    public function test(Context $ctx, string $file1 = '', string $file2 = ''): array
    {
        $out = [];
        $ctx = clone $ctx;
        $ctx->request()->setParam('sessionuser', 'tester');
        $this->db = (fn(): Repository => Core::newObject(Repository::class, $ctx))();
        $this->db->dropSchema();
        $out[] = $this->create(true);
        if (!$file1) {
            $file1 = Main::path('mailviewer/76e56e0dcaf4f55db52e13bf1eb2558c.eml');
        }
        if (!$file2) {
            $file2 = Main::path('mailviewer/a8e401f124909929a73a9fed80510625.eml');
        }
//        '/0dd134ab472949a89916ab426aabb5fc.eml'
//        '/25fc2d1aa711f817900142577c9e00a0.eml'
        $out['import1'] = $this->import($ctx, $file1);
        $out['import2'] = $this->import($ctx, $file2);

        $out['domains'] = $this->db->domains();
        foreach ($out['domains'] as $row) {
            $domain_id = Core::getValue('domain', $row);
            $out['domain'][] = $this->db->domain($domain_id);
            $out['messages'][] = Core::pop($this->db->messages(Core::getValue('domain', $row)));
        }


        foreach ($out['messages'] as $msg) {
            $id = Core::getValue('id', $msg);
            $out['headers'][] = 'Found headers: ' . count($this->db->headers($id));
        }
        return $out;
    }

    /**
     * @cli drop schema
     */
    protected function drop(): string
    {
        $this->db->dropSchema();
        return 'Done';
    }

    /**
     * @cli vacuum the dbfile shrink it if possible
     */
    protected function vacuum(): string
    {
        $this->db->vacuum();
        return 'Done';
    }


    /**
     * @cli test the dateconverter with a list of dates
     */
    protected function testdates(): string
    {
        $dates = [];
        $dates[1] = 'Tue, 06 Aug 2024 11:56:20 +0200';
        $dates[2] = '12 Jul 2024 10:40:32 +0200';
        $dates[3] = '21 Dec 2020 10:52:51 -0400';
        $dates[4] = 'Thu Feb  1 22:42:43 2024';
        $dates[5] = '11 Sep 2023 04:42:15 +0000';
        $dates[6] = 'Tue, 6 Aug 2024 09:04:57 -0700';
        $dates[7] = '13 Dec 2020 23:10:39 +1000';
        $dates[8] = 'Tue, 06 Aug 2024 16:00:42 +0000 (UTC)';
        $dates[9] = 'Wed, 8 Mar 2006 11:14:58 +0100 (Westeurop�ische Normalzeit)';
        $dates[10] = 'Wed, 7 Apr 2004 06:29:36 +0200 (MET DST)';
        $dates[11] = 'Wed, 26 Nov SE Asia Standard Time';
        $dates[12] = 'Wed, 19 May 2004 15:05:53 UT';
        $dates[13] = 'Fri, 9 May 2008 17:42:54 UT';
        $dates[14] = 'Wed, 02 May 2007 10:57:16 --100 (EET)';
        $dates[15] = '2010-11-16 16:29:22';
        $dates[16] = 'Tue, 30 Mar 2004 23:20:11 +0200 (MET DST)';
        $dates[17] = 'Mon, 04 May 2009 19:50:49 +0200';
        $dates[18] = '(qmail 23689 invoked from network); 9 May 2009 11:31:00 +0200';
        $dates[19] = '';
        $dates[20] = 'from ren-mail-pmxin-mg51.mx.mymagenta.at ([192.168.233.133]) by ren-mail-pdcdr-mg04 with LMTP id sHinDDs9dmfZwAEAXMeZxA:T2310 (envelope-from &lt;upc-wildcard-mapping@westweb.at&gt;) for &lt;gabmeyer@westweb.at&gt;; Thu, 02 Jan 2025 17:37:34 +0100';
        $dates[21] = 'from tibet225.server4you.de (HELO smtp.noa.at) (85.25.5.55)
  by zeus.maincodes.at with SMTP; 9 May 2009 11:31:00 +0200
';
        $dates[22] = ' Thu, 02 Jan 2025 17:37:34 +0100';
        $out = '';

        foreach ($dates as $key => $date) {
            $out .= $key . ' ' . DbHelperStatic::dateFormat($date) . PHP_EOL;
        }
        return $out . ' Done';
    }

    /**
     * @cli Import eml file into db
     * @cli param: -path= (file or folder)
     * @cli param: [-filter=(searchstring)]
     * @cli param: [-skip=(false skip existing mails)]
     * @cli param: [-newparser (use new parser)]
     */
    public function import(Context $ctx, string $path, bool $skip = true, bool $newparser = false, string $filter = ''): array
    {
        $out = [];
        if ($newparser) {
            $values = Parser2::parseFile($path);
            Core::echo(Core::removeKeys([''], $values));
//            $parser = new MimeParser();
//            $parser->parse($path);
        } else {
            if ($skip) {
                $this->db->throwOnExisting();
            }

            if (is_dir($path)) {
                try {
                    $this->db->transaction();
                    $log = [];
                    $files = Core::dirList($path);
                    CliUi::withProgressBar($files, function (\SplFileInfo $file) use ($filter, &$log) {
                        if ($file->isDir()) {
                            return;
                        }
                        do {
                            try {
                                $fobj = new \SplFileObject($file->getPathname());
                                if ($filter) {
                                    if (str_contains(file_get_contents($file->getPathname()), $filter)) {
                                        $this->saveMail($fobj);
                                    }
                                } else {
                                    $this->saveMail($fobj);
                                }
                            } catch (\Exception $ex) {
                                Core::echo(__METHOD__, 'import failed', $ex->getMessage(), $file->getPathname(), $filter);
                                // if disk is in sleepmode wait for it
                                usleep(500);
                            }
                        } while (!$fobj);
                    });
                    $this->db->commit();
                    $out[Colors::get('[Done]', Colors::FG_green)] = $path;
                } catch (\Exception $hl) {
                    $this->db->rollback();
                    $out[Colors::get('[Error]', Colors::FG_red)] = Core::toLog($path, $hl);
                }
            } elseif (is_file($path)) {
                try {
                    $this->db->transaction();
                    $this->saveMail(new \SplFileObject($path));
                    $this->db->commit();
                    $out[Colors::get('[Done]', Colors::FG_green)] = $path;
                } catch (\Exception $hl) {
                    $this->db->rollback();
                    $out[Colors::get('[Error]', Colors::FG_red)] = Core::toLog($path, $hl);
                }
            } else {
                $out[Colors::get('[Error]', Colors::FG_red)] = Core::toLog('Wrong Path', $path);
            }
        }
        return $out;
    }

    public static function getUIDFromFile(\SplFileObject $file): string
    {
        return md5_file($file->getRealPath());
//        $collector = new Collector($file);
//        $collector->recursivOff();
//        $collector->start();
//        $values = $collector->getMail();
//        $uid = md5(Core::toLog(Core::getValue(Parser::TYPE_DATE, $values), Core::getValue(Parser::TYPE_SUBJECT, $values), Core::getValue(Parser::TYPE_FROM, $values), Core::getValue(Parser::TYPE_TO, $values)));
//        return $uid;
    }


    /**
     * @cli rename all emails using uid as filename
     * @cli -path= (path to folder or email)
     */
    protected function rename(string $path): void
    {
        $files = Core::dirList($path);
        CliUi::withProgressBar($files, function (\SplFileInfo $file) {
            $this->renameone($file);
        });
    }

    private function renameone(\SplFileInfo $file): string
    {
        Core::time();
        do {
            try {
                $fobj = new \SplFileObject($file->getPathname());
                $filename = explode('.', $fobj->getFilename(), -1)[0];
                $uid = self::getUIDFromFile($fobj);
                if ($this->db->hasMail($uid)) {
                    return $file->getPathname();
                }
                if ($uid === $filename) {
                    return $file->getPathname();
                }
                $pathname = $fobj->getPathname();
                $to = dirname($pathname) . '/' . $uid . '.eml';
                rename($pathname, $to);
                Core::echo('rename: ', $pathname, ' to ', $to, Core::time());
                return $to;
            } catch (\Exception $ex) {
                Core::echo(__METHOD__, $ex);
                // if disk is in sleepmode wait for it
                sleep(1);
            }
        } while (!$fobj);
        return '';
    }

    /**
     */
    private function saveMail(\SplFileObject $file): void
    {
        $pathname = $file->getPathname();

        if ($this->db->hasMail(explode('.', $file->getFilename(), -1)[0])) {
            return;
        }

        $uid = self::getUIDFromFile($file);

        $pathname = $this->renameone($file->getFileInfo());
        if ($this->db->hasMail($uid)) {
            return;
        }
        if ($pathname) {
            $file = new \SplFileObject($pathname);
            $collector = new Collector($file);
            $collector->start();
            $values = $collector->getMail();

            $values = $this->addUid($values, $uid);

            try {
//                Core::echo(Colors::get(__METHOD__, Colors::FG_green), count($values));
                $this->db->saveEmail($values);
            } catch (\Exception $ex) {
                Core::echo(Colors::get(__METHOD__, Colors::FG_red), $ex->getMessage());
                Core::echoTmp($ex);
                if ($ex->getCode() === 111) {
                    return;
                } else {
                    Core::echoTmp($ex);
                }
            }
        }
    }

    private function addUid(array $data, string $uid, bool $reset = true): array
    {
        static $current;
        if ($reset) {
            $current = 0;
        }
        $data[Parser::TYPE_UID] = $uid . $current;
        foreach ($data[Parser::TYPE_PARTS] as $key => $part) {
            $current++;
            $data[Parser::TYPE_PARTS][$key] = $this->addUid($part, $uid, false);
        }
        return $data;
    }


}
