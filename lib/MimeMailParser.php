<?php
//declare(strict_types=1);

namespace cryodrift\mailviewer\lib;


use eXorus\PhpMimeMailParser\Attachment;


/**
 * this needs mimeparser pear extension to work
 * its not tested
 */
class MimeMailParser
{
    function decode_content($content, $encoding)
    {
        switch (strtolower($encoding)) {
            case 'base64':
                return base64_decode($content);
            case 'quoted-printable':
                return quoted_printable_decode($content);
            case '7bit':
            case '8bit':
            default:
                return $content;
        }
    }

    function parse_part($part)
    {
        $parts = [];
        if ($part instanceof Attachment) {
            // Handle attachment
            $parts[] = [
              'mime_type' => $part->getContentType(),
              'content' => $this->decode_content($part->getContent(), $part->getEncoding()),
              'filename' => $part->getFilename(),
            ];
        } else {
            // Handle nested parts
            foreach ($part->getParts() as $nestedPart) {
                $parts = array_merge($parts, $this->parse_part($nestedPart));
            }
        }
        return $parts;
    }

    public function parse(string $pathname)
    {
        // Load the email file
        $parser = new \eXorus\PhpMimeMailParser\Parser();
        $parser->setPath($pathname);

        // Get all headers
        $headers = $parser->getHeaders();

        // Get all parts recursively
        $parts = $this->parse_part($parser);

        // Display headers
        echo "Headers:\n";
        print_r($headers);

        // Display parts
        echo "\nParts:\n";
        print_r($parts);
    }
}



