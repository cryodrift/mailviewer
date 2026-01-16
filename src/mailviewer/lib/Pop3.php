<?php

//declare(strict_types=1);

namespace cryodrift\mailviewer\lib;


use cryodrift\fw\Core;

class Pop3
{
    const EOL_DOT = "\r\n.\r\n";
    const EOL_ONE = "\r\n";
    const LEN_ONE = 1000000;
    private $connection;
    private $error;
    private $errornr;
    private $user;
    private $pass;
    private $host;
    private $eol = '';

    public function __construct($host, $user, $pass)
    {
        $this->user = $user;
        $this->pass = $pass;
        $this->host = $host;
        $this->connect($host);
        if (!$this->connection) {
            throw new \Exception('not connected');
        }
        $this->receive();
        $this->auth();
    }

    /**
     * @return mixed
     */
    public function getHost(): string
    {
        return $this->host;
    }

    protected function connect($server = ''): void
    {
        $ctx = stream_context_create([
          'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
          ]
        ]);

        $hostname = "tls://$server:995";
        $this->connection = stream_socket_client($hostname, $this->errornr, $this->error, 10, STREAM_CLIENT_CONNECT, $ctx);
        if ($this->connection) {
            stream_set_timeout($this->connection, 2);
        }
        else{
            Core::echo(__METHOD__,$hostname, $this->errornr, $this->error, 10, STREAM_CLIENT_CONNECT, $ctx);
            die();
        }
    }

    protected function auth(): void
    {
        $this->sendcmd('USER ' . $this->user);
        $this->receive();
        $this->sendcmd('PASS ' . $this->pass);
        $this->receive();
        $this->receive();
    }

    protected function sendcmd($cmd): void
    {
        $cmd = trim($cmd);
        $cpart = explode(' ', $cmd);
        switch ($cpart[0]) {
            case 'STAT':
            case 'USER':
            case 'PASS':
                $this->eol = self::EOL_ONE;
                break;
            default:
                $this->eol = self::EOL_DOT;
        }
        $cmd = trim($cmd) . Parser::LINE;
        $bytes = fputs($this->connection, $cmd);
        if ($bytes != strlen($cmd)) {
            throw new \Exception('Command not Sent! ' . $cmd);
        }
    }

    protected function receive(string|int|null $len = null): string
    {
        if ($len) {
            $len = (int)$len * 10;
        } else {
            $len = self::LEN_ONE;
        }
        $out = stream_get_line($this->connection, $len, $this->eol);
        if (is_string($out)) {
            return $out;
        } else {
            return '';
        }
    }

    public function meta(): array
    {
        return stream_get_meta_data($this->connection);
    }

    public function stat(): array
    {
        $this->sendcmd('STAT');
        $parts = explode(' ', $this->receive());
        array_shift($parts);
        return $parts;
    }

    public function msgnums(): array
    {
        $this->sendcmd('LIST');
        $list = $this->receive();
        $parts = explode(Parser::LINE, $list);
        $out = [];
        foreach ($parts as $part) {
            $info = explode(' ', $part);
            $num = $info[0];
            if (is_numeric($num)) {
                $out[] = new Mailinfo((int)$num, (int)trim($info[1]));
            }
        }
        return $out;
    }


    public function fetchmail(Mailinfo $info): string
    {
        $this->sendcmd('RETR ' . $info->id);
        $len = (int)$info->len + 100;

        $mail = trim($this->receive($len));
        $parts = explode("\n", $mail, 2);
        if (strpos($parts[0], '+OK ') !== false) {
            $mail = $parts[1];
        }
        return $mail;
    }

    public function quit(): void
    {
        if (is_resource($this->connection)) {
            fclose($this->connection);
        }
    }

    public function __destruct()
    {
        $this->quit();
    }


}
