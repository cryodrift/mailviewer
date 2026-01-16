<?php

//declare(strict_types=1);

namespace cryodrift\mailviewer\lib;

use cryodrift\fw\Core;
use cryodrift\fw\trait\DbHelper;
use cryodrift\fw\trait\DbHelperFnkDate;

class Collector
{
    use DbHelper;
    use DbHelperFnkDate;

    protected array $currentMail;

    /**
     * @var \SplFileObject
     */
    protected \SplFileObject|null $fileObject = null;

    /**
     *
     */
    protected int $currentheader = 0;

    public bool $headermode = true;

    protected bool $recursion = true;

    protected int $filetime = 0;

    /**
     * Collector constructor.
     * @param string $content
     * @param null $fileObject
     */
    public function __construct(\SplFileObject $file)
    {
        $line = $file->fgets();
        if ($file->getPathname() === 'php://memory' || preg_match('/^[a-zA-Z-]+:? /', $line)) {
            $this->currentMail = [Parser::TYPE_HEADERS => [], Parser::TYPE_PARTS => [], Parser::TYPE_TYPE => '', Parser::TYPE_ENCODING => '', Parser::TYPE_BOUNDARY => '', Parser::TYPE_CONTENT => ''];
            $this->fileObject = $file;
            if ($file->isFile()) {
                $this->currentMail[Parser::TYPE_OFILENAME] = $file->getFilename();
            }
        } else {
            throw new \Exception('Not a email file! ' . $file->getPathname(), 99);
        }
        if ($file->getPathname() !== 'php://memory') {
            $this->filetime = $file->getMTime();
        }
        $file->rewind();
    }

    public function start(): void
    {
        if ($this->validFileObject()) {
            Parser::parseMail($this, $this->recursion);
            $this->start();
        } else {
            if (Parser::isMultiMode($this->currentMail[Parser::TYPE_TYPE])) {
                if ($this->currentMail[Parser::TYPE_BOUNDARY]) {
                    $boundary = '--' . $this->currentMail[Parser::TYPE_BOUNDARY];
                    $parts = explode($boundary . Parser::LINE, $this->getContent());
                    $this->setContent();

                    foreach ($parts as $part) {
                        $part = trim($part);
                        if ($part && $part !== '--') {
                            $this->subMail(str_replace($boundary . '--', '', $part));
                        }
                    }
                } else {
                    Core::echo(__METHOD__, ['Boundary Missing! ', $this->currentMail['ofilename']]);
                    $this->handleContent();
                }
            } else {
                $this->handleContent();
            }
        }
    }

    public function addHeader(string $name, string $content): void
    {
        $this->currentheader++;
        $this->currentMail[Parser::TYPE_HEADERS][$this->currentheader] = [Parser::TYPE_NAME => $name, Parser::TYPE_HEADER_CONTENT => $content];
    }

    public function addToCurrentHeader(string $content): void
    {
        if (array_key_exists($this->currentheader, $this->currentMail[Parser::TYPE_HEADERS])) {
            $this->currentMail[Parser::TYPE_HEADERS][$this->currentheader][Parser::TYPE_HEADER_CONTENT] .= $content;
        } else {
            throw new \Exception('No header initialized for: ' . $content . ' in ' . $this->getFileObject()->getPathname());
        }
    }

    public function addContent(string $line): void
    {
        $this->currentMail[Parser::TYPE_CONTENT] .= $line;
    }

    public function getContent(): string
    {
        return $this->currentMail[Parser::TYPE_CONTENT];
    }

    public function setContent(string|null $content = null): void
    {
        $this->currentMail[Parser::TYPE_CONTENT] = $content;
    }

    protected function handleContent(): void
    {
        $this->setContent(trim($this->getContent()));
        if ($this->currentMail[Parser::TYPE_TYPE] === Parser::CONTENTTYPE_MESSAGERFC822) {
            $this->subMail($this->getContent());
        } else {
            if ($this->getBoundary()) {
                $this->setContent(str_replace('--' . $this->getBoundary() . '--', '', $this->getContent()));
            }
            switch ($this->currentMail[Parser::TYPE_ENCODING]) {
                case Parser::ENCODING_QUOTEDPRINTABLE:
                    $this->setContent(quoted_printable_decode($this->getContent()));
                    if (strtoupper(Core::getValue(Parser::TYPE_CHARSET, $this->currentMail)) == Parser::CHARSET_ISO88591) {
                        $this->setContent(mb_convert_encoding($this->getContent(), 'UTF-8', Parser::CHARSET_ISO88591));
                    }
                    break;
                case Parser::ENCODING_BASE64:
                    switch (true) {
                        case stripos(Core::getValue(Parser::TYPE_TYPE, $this->currentMail), Parser::CONTENTTYPE_TEXT) === 0:
                            $this->setContent(base64_decode(str_replace(Parser::LINE, '', $this->getContent())));
                            if (strtoupper(Core::getValue(Parser::TYPE_CHARSET, $this->currentMail)) == Parser::CHARSET_ISO88591) {
                                $this->setContent(mb_convert_encoding($this->getContent(), 'UTF-8'));
                            }
                            break;
                        // TODO saving binary data needs pdo-bind
                    }
                    break;
            }
        }
    }

    protected function subMail(string $part): void
    {
        $collector = new self(self::memfile($part));
        if (!Parser::isMultiMode($collector->currentMail[Parser::TYPE_TYPE])) {
            $collector->setBoundary($this->getBoundary());
        }
        $collector->start();
        $this->addPart($collector->currentMail);
    }

    public function addPart(array $mail): void
    {
        $this->currentMail[Parser::TYPE_PARTS][] = $mail;
    }

    public function getMail(): array
    {
        if (!$this->filetime) {
            $this->filetime = time();
        }
        $date = new \DateTime('@' . $this->filetime);
        $this->currentMail[Parser::TYPE_DATE] = Core::getValue(
          Parser::TYPE_DATE,
          $this->currentMail,
          Core::getValue(
            Parser::TYPE_RECEIVED,
            $this->currentMail,
            Core::getValue(
              Parser::HEADER_DELIVERYDATE,
              $this->currentMail,
              $date->format('Y-m-d H:i:s')
              ,
              true
            ),
            true
          ),
          true
        );

        return $this->currentMail;
    }

    public function setBoundary(string $boundary): string
    {
        return $this->currentMail[Parser::TYPE_BOUNDARY] = $boundary;
    }

    public function getBoundary(): string
    {
        return $this->currentMail[Parser::TYPE_BOUNDARY];
    }

    public function extractHeaderData(): void
    {
        foreach ($this->currentMail[Parser::TYPE_HEADERS] as $header) {
            $name = strtolower(Core::getValue(Parser::TYPE_NAME, $header));
            $content = Core::getValue(Parser::TYPE_HEADER_CONTENT, $header);
            switch (true) {
                case $name === Parser::HEADER_CONTENTTYPE:
                    $parts = Parser::getHeaderParts($content, $this->currentMail);
                    $this->currentMail[Parser::TYPE_TYPE] = strtolower(Core::getValue(0, $parts));
                    break;
                case $name === Parser::HEADER_CONTENTTRANSFERENCODING:
                    $this->currentMail[Parser::TYPE_ENCODING] = strtolower($content);
                    break;
                case $name === Parser::HEADER_FROM:
                    $this->currentMail[Parser::TYPE_FROM] = Parser::splitAddress($content, Parser::TYPE_FROM);
                    break;
                case $name === Parser::HEADER_TO:
                case $name === Parser::HEADER_ENVELOPETO:
                    $this->currentMail[Parser::TYPE_TO] = Parser::extractAddresses($content, Parser::TYPE_TO);
                    break;
                case $name === Parser::HEADER_DATE:
                    $this->currentMail[Parser::TYPE_DATE] = self::dateFormat($content);
                    break;
                case $name === Parser::HEADER_RECEIVED:
                    $this->currentMail[Parser::HEADER_RECEIVED] = self::dateFormat($content);
                    break;
                case $name === Parser::HEADER_DELIVERYDATE:
                    $this->currentMail[Parser::HEADER_DELIVERYDATE] = self::dateFormat($content);
                    break;
                case $name === Parser::HEADER_SUBJECT:
                    $this->currentMail[Parser::TYPE_SUBJECT] = mb_decode_mimeheader($content);
                    break;
                case $name === Parser::HEADER_CONTENTID:
                    $this->currentMail[Parser::TYPE_CONTENTID] = $content;
                    break;
            }
        }
    }

    public function removeSpecialHeaders(): void
    {
        $new = [];
        foreach ($this->currentMail[Parser::TYPE_HEADERS] as $key => $header) {
            switch (strtolower($header[Parser::TYPE_NAME])) {
//                case Parser::HEADER_FROM:
//                case Parser::HEADER_DELIVEREDTO:
//                case Parser::HEADER_TO:
//                case Parser::HEADER_DATE:
//                case Parser::HEADER_RECEIVED:
//                case Parser::HEADER_ENVELOPETO:
//                    break;
                default:
                    $new[] = $header;
            }
        }
        $this->currentMail[Parser::TYPE_HEADERS] = $new;
    }


    public static function memfile(string $data): \SplFileObject
    {
        $file = new \SplFileObject('php://memory', 'w+');
        $file->fwrite($data);
        $file->rewind();
        return $file;
    }

    public function getFileObject(): \SplFileObject|null
    {
        $this->fileObject->rewind();
        return $this->fileObject;
    }

    public function unsetFileObject(): null
    {
        $this->fileObject = null;
        return null;
    }

    public function validFileObject(): bool
    {
        if ($this->fileObject) {
            return $this->fileObject->valid();
        } else {
            return false;
        }
    }

    public function recursivOff(): void
    {
        $this->recursion = false;
    }
}
