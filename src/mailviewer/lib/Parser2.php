<?php 
//declare(strict_types=1);

namespace cryodrift\mailviewer\lib;

/**
 * this is gpt generated
 */
class Parser2
{


    public static function parseFile(string $pathname): array
    {
        // Load the email file

        $rawEmail = file_get_contents($pathname);

        if ($rawEmail === false) {
            die('Unable to open file');
        }

        // Parse the email
        return self::parseEmail($rawEmail);
    }

    public static function parseEmail(string $rawEmail): array
    {
        $parts = ['parts' => [], 'content' => ''];

        // Split headers and body
        list($headerText, $bodyText) = explode("\r\n\r\n", $rawEmail, 2);

        // Parse headers
        $headers = self::parseHeaders($headerText);
        $parts['headers'] = $headers;

        // Check if the email is multipart
        if (isset($headers['content-type']) && str_starts_with($headers['content-type'], 'multipart/')) {
            // Find the boundary
            preg_match('/boundary="([^"]+)"/', $headers['content-type'], $matches);
            $boundary = $matches[1];

            // Split the body text into parts
            $bodyParts = explode("--$boundary", trim($bodyText));
            array_pop($bodyParts); // Remove the last part (boundary end)

            foreach ($bodyParts as $bodyPart) {
                $parts['parts'][] = self::parseEmail(trim($bodyPart));
            }
        } else {
            // Decode the content if necessary
            $encoding = isset($headers['content-transfer-encoding']) ? $headers['content-transfer-encoding'] : '7bit';
            $decodedContent = self::decodeContent($bodyText, $encoding);


            $parts['content'] = $decodedContent;
        }

        return $parts;
    }

    public static function parseHeaders($headerText)
    {
        $headers = [];
        $lines = explode("\r\n", $headerText);
        $currentHeader = '';

        foreach ($lines as $line) {
            if (!str_contains($line, ':')) {
                // Continued header
                $currentHeader .= ' ' . trim($line);
            } else {
                // New header
                if ($currentHeader) {
                    list($name, $value) = explode(':', $currentHeader, 2);
                    $headers[strtolower(trim($name))] = trim($value);
                }
                $currentHeader = $line;
            }
        }

        if ($currentHeader) {
            list($name, $value) = explode(':', $currentHeader, 2);
            $headers[strtolower(trim($name))] = trim($value);
        }

        return $headers;
    }

    public static function decodeContent($content, $encoding)
    {
        switch (strtolower($encoding)) {
            case '7bit':
            case '8bit':
                return $content;
            case 'base64':
                return base64_decode($content);
            case 'quoted-printable':
                return quoted_printable_decode($content);
            default:
                return $content;
        }
    }


}
