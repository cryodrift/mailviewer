<?php

//declare(strict_types=1);

namespace cryodrift\mailviewer\lib;


use cryodrift\fw\cli\CliUi;

class Parser
{


    const HEADER_DELIVEREDTO = 'delivered-to';
    const HEADER_DATE = 'date';
    const HEADER_DELIVERYDATE = 'delivery-date';
    const HEADER_CONTENTID = 'content-id';
    const HEADER_FROM = 'from';
    const HEADER_ENVELOPETO = 'envelope-to';
    const HEADER_TO = 'to';
    const HEADER_SUBJECT = 'subject';
    const HEADER_CONTENTTYPE = 'content-type';
    const HEADER_RECEIVED = 'received';
    const HEADER_CONTENTTRANSFERENCODING = 'content-transfer-encoding';

    const CONTENTTYPE_MULTIPARTMIXED = 'multipart/mixed';
    const CONTENTTYPE_MULTIPARTALTERNATIVE = 'multipart/alternative';
    const CONTENTTYPE_MULTIPARTRELATED = 'multipart/related';
    const CONTENTTYPE_MESSAGERFC822 = 'message/rfc822';
    const CONTENTTYPE_MULTIPARTREPORT = 'multipart/report';
    const CONTENTTYPE_TEXT = 'text/';

    const CONTENTDISPOSITION_ATTACHMENT = 'attachment';

    const TYPE_FILENAME = 'filename';
    const TYPE_CHARSET = 'charset';
    const TYPE_BOUNDARY = 'boundary';
    const TYPE_NAME = 'name';
    const TYPE_TYPE = 'type';
    const TYPE_ENCODING = 'encoding';
    const TYPE_PARTS = 'parts';
    const TYPE_HEADERS = 'headers';
    const TYPE_CONTENT = 'contentblob';
    const TYPE_RECEIVED = 'received';
    const TYPE_HEADER_CONTENT = 'header_content';
    const TYPE_OFILENAME = 'ofilename';
    const TYPE_FROM = 'from';
    const TYPE_TO = 'to';
    const TYPE_DATE = 'date';
    const TYPE_SUBJECT = 'subject';
    const TYPE_EMAIL = 'email';
    const TYPE_UID = 'mail_uid';
    const TYPE_CONTENTID = 'cid';

    const ENCODING_QUOTEDPRINTABLE = 'quoted-printable';
    const ENCODING_BASE64 = 'base64';
    const ENCODING_8BIT = '8bit';
    const ENCODING_7BIT = '7bit';
    const ENCODING_BIN = 'binary';

    const CHARSET_ISO88591 = 'ISO-8859-1';

    const LINE = "\r\n";

    public static function splitHeader($headerline): array
    {
        $parts = explode(':', $headerline);
        $out[array_shift($parts)] = trim(implode(':', $parts));
        return $out;
    }

    public static function isHeader($line): bool
    {
        if ($line) {
            switch (true) {
                case ($line[0] !== "\t" && $line[0] !== ' '):
                    if (strstr($line, ':') === false) {
                        return false;
                    }
                    $parts = explode(':', $line);
                    if (strstr($parts[0], ' ') !== false) {
                        return false;
                    }
                    return true;
            }
        }
        return false;
    }

    public static function isHeaderContent($line): bool
    {
        if ($line && ($line[0] === "\t" || $line[0] === " ")) {
            return true;
        }
        return false;
    }

    public static function isEmptyLine($line): bool
    {
        return $line === self::LINE;
    }

    public static function getHeaderParts($headercontent, array &$mail)
    {
        $out = [];
        $headercontent = str_replace("\r\n", '', $headercontent);
        $trimmed = explode(';', trim($headercontent));
        foreach ($trimmed as $item) {
            $item = trim($item);
            switch (true) {
                case stripos($item, self::TYPE_BOUNDARY) === 0:
                    $mail[self::TYPE_BOUNDARY] = self::getTypeValue($item);
                    break;
                case stripos($item, self::TYPE_CHARSET) === 0:
                    $mail[self::TYPE_CHARSET] = self::getTypeValue($item);
                    break;
                case stripos($item, self::TYPE_FILENAME) === 0:
                case stripos($item, self::TYPE_NAME) === 0:
                    $mail[self::TYPE_FILENAME] = mb_decode_mimeheader(self::getTypeValue($item));
                    break;
                default:
                    if ($item) {
                        $out[] = $item;
                    }
            }
        }
        return $out;
    }

    protected static function getTypeValue(string $typecontent)
    {
        $parts = explode('=', $typecontent);
        array_shift($parts);
        return trim(trim(implode('=', $parts), '"'));
    }

    public static function parseMail(Collector $collector, bool $withcontent = true)
    {
        $file = $collector->getFileObject();
        while ($file && !$file->eof()) {
            $line = $file->getCurrentLine();
            switch (true) {
                case $collector->headermode && self::isHeader($line):
                    $parts = self::splitHeader($line);
                    $collector->addHeader((string)key($parts), (string)current($parts));
                    break;
                case $collector->headermode && self::isHeaderContent($line):
                    $collector->addToCurrentHeader($line);
                    break;
                case self::isEmptyLine($line):
                default:
                    $collector->headermode = false;
                    if ($withcontent) {
                        $collector->addContent($line);
                    } else {
                        $file = $collector->unsetFileObject();
                    }
            }
        }
        $collector->extractHeaderData();
//        $collector->removeSpecialHeaders();
    }

    public static function isMultiMode($mode)
    {
        return in_array($mode, [self::CONTENTTYPE_MULTIPARTMIXED, self::CONTENTTYPE_MULTIPARTALTERNATIVE, self::CONTENTTYPE_MULTIPARTRELATED, self::CONTENTTYPE_MULTIPARTREPORT]);
    }

    public static function splitAddress(string $content, string $type)
    {
        $s = [];
        $r = [];
        $s[] = "<";
        $r[] = '';
        $s[] = ">";
        $r[] = '';
        $s[] = "\r\n\t";
        $r[] = ' ';
        $s[] = "\r";
        $r[] = '';
        $s[] = "\n";
        $r[] = '';
        $s[] = "\t";
        $r[] = ' ';
        $s[] = "'";
        $r[] = '';
        $s[] = '"';
        $r[] = '';
        $s[] = "\0";
        $r[] = '';
        $s[] = "\x0B";
        $r[] = '';
        $s[] = '  ';
        $r[] = ' ';
        $s[] = ',';
        $r[] = '';
        $parts = explode(' ', trim(str_replace($s, $r, $content)));
        $email = array_pop($parts);
        $name = trim(str_replace($s, $r, implode(' ', $parts)));
        if ($name == "" && strpos($email, "@") === false) {
            $name = $email;
            $email = "";
        }
        return [Parser::TYPE_TYPE => $type, Parser::TYPE_EMAIL => $email, Parser::TYPE_NAME => mb_decode_mimeheader($name)];
    }

    public static function splitAddresses(string $content)
    {
        $out = [];
        $in = false;
        $part = '';
        for ($a = 0; $a < strlen($content); $a++) {
            $char = $content[$a];
            if ($char === '"') {
                if ($in === false) {
                    $in = true;
                } else {
                    $in = false;
                }
            }
            if (!$in) {
                if ($char === ',') {
                    $out[] = trim($part);
                    $part = '';
                }
            }
            $part .= $char;
        }
        $out[] = trim($part);
        return $out;
    }

    public static function extractAddresses(string $content, string $type)
    {
        $out = [];
        $content = str_replace("\r\n", ' ', $content);
        foreach (self::splitAddresses($content) as $address) {
            $out[] = self::splitAddress($address, $type);
        }
        return $out;
    }


}
