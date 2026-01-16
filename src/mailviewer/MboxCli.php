<?php

//declare(strict_types=1);

namespace cryodrift\mailviewer;

use cryodrift\fw\cli\CliUi;
use cryodrift\fw\Context;
use cryodrift\fw\Core;
use cryodrift\fw\interface\Handler;
use cryodrift\fw\trait\CliHandler;

class MboxCli implements Handler
{
    use CliHandler;

    public function handle(Context $ctx): Context
    {
        return $this->handleCli($ctx);
    }

    /**
     * @cli Read thunderbird mbox file and extract all email to a folder
     * @cli -outdir (dir to write files)
     * @cli -file   (mboxfile)
     * @cli Example: read -outdir="/tmp" -file="/mails/inbox"
     */
    protected function read(Context $ctx)
    {
        $file = $ctx->request()->param('file');
        $dir = $ctx->request()->param('outdir');

        $messageCount = 0;
        if ($file && is_file($file) && $dir && is_dir($dir)) {
            $handle = fopen($file, 'r');

            if ($handle) {
                while (($line = fgets($handle)) !== false) {
                    if (preg_match('/^From /', $line)) {
                        $messageCount += 1;
                    }
                }

                // Reset the file pointer to the beginning of the file
                rewind($handle);

                $generator = function () use ($handle) {
                    $content = '';
                    while (($line = fgets($handle)) !== false) {
                        if (preg_match('/^From /', $line)) {
                            if ($content) {
                                yield $content;
                                $content = '';
                            }
                        }
                        $content .= $line;
                    }
                    yield $content;
                };
                CliUi::withProgressBar($generator(), function ($content) use ($dir) {
                    $content = self::removeMeta($content);
                    self::saveEml($content, $dir);
                }, $messageCount);


                fclose($handle);
                CliUi::echoLine("Extracted $messageCount emails to $dir");
            } else {
                CliUi::echoLine("Cannot open mbox file.");
            }
        }
        return $ctx;
    }

    private static function removeMeta(string $content)
    {
        $delim = "\n" . 'Return-Path: ';
        $parts = explode($delim, $content);
        array_shift($parts);
        return 'Return-Path: ' . trim(implode($delim, $parts));
    }

    private static function saveEml(string $content, string $outputDir)
    {
        if (is_dir($outputDir)) {
            $emlPath = $outputDir . '/' . md5($content) . '.eml';
            Core::fileWrite($emlPath, $content);
        }
    }

}
