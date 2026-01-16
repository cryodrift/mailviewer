<?php

//declare(strict_types=1);

namespace cryodrift\mailviewer\db;

use cryodrift\mailviewer\lib\Parser;
use cryodrift\fw\cli\Colors;
use cryodrift\fw\Context;
use cryodrift\fw\Core;
use cryodrift\fw\trait\DbHelper;
use cryodrift\fw\trait\DbHelperFnkDate;
use PDO;
use Exception;
use cryodrift\fw\trait\DbHelperMigrate;

class Repository
{
    use DbHelper;
    use DbHelperFnkDate;
    use DbHelperMigrate;

    public function __construct(Context $ctx, string $storagedir)
    {
        $this->connect('sqlite:' . $storagedir . $ctx->user() . '/mails' . '.sqlite');
        $this->attachFunction('compareDateName');
    }

    public function domain(string $email, string $search = ''): array
    {
        return Core::cleanData($this->getContact($email, $this->getSpecialSearch($search)));
    }

    protected function getSpecialSearch(string $search): string
    {
        $parts = explode('=', $search, 2);
        if (count($parts) > 1) {
            if (in_array($parts[1], $this::DATENAMES)) {
                return $parts[1];
            }
        }
        return '';
    }

    public function domains(int $domains_page = 0, string $domain_search = ''): array|null
    {
        if ($domain_search) {
            switch (true) {
                case $test = $this->getSpecialSearch($domain_search):
                    $domains = $this->searchDomainsByDateName($test, $domains_page);
                    break;
                default:
                    $domains = $this->searchDomains($domain_search, $domains_page);
            }
        } else {
            $domains = $this->getDomains($domains_page);
        }
        return Core::cleanData($domains);
    }

    public function messages(string $domains_id, int $message_page = 0, string $domain_search = '', string $message_search = ''): array|null
    {
        $message = $this->getContact($domains_id, $domain_search);
        $ids = Core::getValue('ids', $message);
        if ($message_search) {
            $messages = $this->searchMessages($message_search, explode(',', $ids), $message_page);
        } else {
            $messages = $this->getMessages(explode(',', $ids), $message_page);
        }

        $messages = Core::cleanData($messages);
        return $messages;
    }

    public function message(string|null $id): array
    {
        $out = $this->getMessages([$id]);
        $out = Core::cleanData($out);
        if ($out) {
            return array_pop($out);
        } else {
            return [];
        }
    }

    public function content(string|null $id): array
    {
        $out = $this->getContent($id);
        $out = Core::cleanData($out, [$this::TYPE_BLOBCONTENT]);
        if ($out) {
            $data = array_pop($out);
            $content = $data[$this::TYPE_BLOBCONTENT];
            if (strlen($content) != $data['contentlen']) {
                throw new \Exception(Core::toLog('size wrong', strlen($content), $data['contentlen'], 'problem'));
            }
            $content = gzuncompress($content);
            $data[$this::TYPE_BLOBCONTENT] = $content;

            return $data;
        } else {
            return [];
        }
    }

    public function addresses(string $message_id): array
    {
        $from = Core::cleanData($this->getFromAddressesForMessage($message_id));
        $to = Core::cleanData($this->getToAddressesForMessage($message_id));
        array_unshift($to, $from);
        return $to;
    }

    public function parts(string $message_id): array
    {
        return Core::cleanData($this->getParts($message_id));
    }

    public function headers(string $message_id): array
    {
        return Core::cleanData($this->getHeaders($message_id));
    }

    public function hasMail(string $uid)
    {
        return $this->query('select id from mail where ' . Parser::TYPE_UID . '=:uid', ['uid' => $uid]);
    }

    public function saveEmail(array $email, string|null $mailId = null): void
    {
        $mailId = $this->saveMail($email, $mailId);

        foreach (Core::getValue(Parser::TYPE_HEADERS, $email, []) as $header) {
            $this->saveHeader($header, $mailId);
        }

        $this->saveAddress(Core::getValue(Parser::TYPE_FROM, $email, []), $mailId);

        foreach (Core::getValue(Parser::TYPE_TO, $email, []) as $addr) {
            $this->saveAddress($addr, $mailId);
        }

        $content = Core::getValue($this::TYPE_BLOBCONTENT, $email);

        if ($content) {
            $content = gzcompress($content);
            $this->saveContent($content, $mailId);
        }

        foreach (Core::getValue(Parser::TYPE_PARTS, $email, []) as $part) {
            $this->saveEmail($part, $mailId);
        }
    }

    public function dropSchema(): void
    {
        $this->transaction();
        $this->query('PRAGMA writable_schema = ON;');
        $this->query('PRAGMA foreign_keys = OFF;');
        $this->runQueriesFromFile(__DIR__ . '/d_views.sql', '--END;');
        foreach (['mail_header_mail', 'mail_address_mail','mail_header', 'mail_address', 'mail_mail', 'mail_content', 'mail'] as $table) {
            try {
                $sql = 'drop table if exists ' . $table;
                $stmt = $this->getStmt($sql);
                $stmt->execute();
            } catch (Exception $ex) {
                Core::echo(__METHOD__, Colors::get('[ERROR]', Colors::FG_red),$sql, $ex->getMessage());
            }
        }
        $this->query('PRAGMA writable_schema = OFF;');
        $this->query('PRAGMA foreign_keys = ON;');
        $this->commit();
        $this->vacuum();
    }

    public function throwOnExisting(): void
    {
        $this->skipexisting = true;
    }

    public function mailcount(string $host, string $count = ''): array
    {
        if ($count) {
            $this->runInsert('host', 'name,mailcount', ['name' => $host, 'mailcount' => $count]);
        }
        return Core::pop($this->query('select mailcount from host where name=:name', ['name' => $host]));
    }

    public function saveMail(array $data, string|null $mailId = null): string
    {
        $types = [
          Parser::TYPE_DATE,
          Parser::TYPE_SUBJECT,
          Parser::TYPE_TYPE,
          Parser::TYPE_CHARSET,
          Parser::TYPE_ENCODING,
          Parser::TYPE_BOUNDARY,
          Parser::TYPE_FILENAME,
          Parser::TYPE_OFILENAME,
          Parser::TYPE_UID,
          Parser::TYPE_CONTENTID,
        ];
        $id = $this->runInsert('mail', '"' . implode('","', $types) . '"', $data);
        if ($mailId) {
            $this->runInsert('mail_mail', 'mail_id1,mail_id2', ['mail_id1' => $mailId, 'mail_id2' => $id]);
        }
        return $id;
    }


    protected function getDomains(int $page = 0, int $limit = 20): array
    {
        $sql = Core::fileReadOnce(__DIR__ . '/s_domains.sql');
        $stmt = $this->getStmt($sql);
        self::bindPage($stmt, $page, $limit);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    protected function getFromAddressesForMessage(string $id): array
    {
        $stmt = $this->getStmt('SELECT * FROM v_allfrom where ","||ids||"," like :id');
        $id = self::prepareLike(',' . $id . ',');
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($data) {
            return $data;
        } else {
            return [];
        }
    }

    protected function getToAddressesForMessage(string $id): array
    {
        $stmt = $this->getStmt(Core::fileReadOnce(__DIR__ . '/s_toaddresses.sql'));
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($data) {
            return $data;
        } else {
            return [];
        }
    }

    protected function getContact(string $email, string $search = ''): array
    {
        if (!str_starts_with('=', $search)) {
            $search = '';
        }
        if ($search) {
            $stmt = $this->getStmt(Core::fileReadOnce(__DIR__ . '/s_domainbydatename.sql'));
            $stmt->bindParam(':thedate', $search);
        } else {
            $stmt = $this->getStmt(Core::fileReadOnce(__DIR__ . '/s_domain.sql'));
            self::bindPage($stmt, 0, 50000);
        }
        $email1 = self::prepareLike('@' . $email);
        $email2 = self::prepareLike('.' . $email);
        $stmt->bindParam(':email', $email1);
        $stmt->bindParam(':email2', $email2);
        $stmt->execute();
        $data = $stmt->fetchAll();
        if ($data && ($email || $search)) {
            $out = [];
            foreach ($data as $key => $row) {
                if ($key > 0) {
                    $out['ids'] = $out['ids'] . ',' . $row['ids'];
                    $out['anz'] = $out['anz'] + $row['anz'];
                } else {
                    $out = $row;
                }
            }
            return $out;
        } else {
            return [];
        }
    }

    protected function searchDomains(string $search, int $page = 0, int $limit = 20): array
    {
        $sql = Core::fileReadOnce(__DIR__ . '/s_domain.sql');
        $stmt = $this->getStmt($sql);
        self::bindPage($stmt, $page, $limit);
        $search = self::prepareLike($search);
        $stmt->bindParam(':email', $search);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    protected function searchDomainsByDateName(string $search, int $page = 0, int $limit = 20): array
    {
        $sql = Core::fileReadOnce(__DIR__ . '/s_domainsbydatename.sql');
        $stmt = $this->getStmt($sql);
        self::bindPage($stmt, $page, $limit);
        $stmt->bindParam(':thedate', $search);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    protected function getParts(string $id): array
    {
        $stmt = $this->getStmt(Core::fileReadOnce(__DIR__ . '/s_parts.sql'));
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    protected function getHeaders(string $id): array
    {
        $stmt = $this->getStmt(Core::fileReadOnce(__DIR__ . '/s_headers.sql'));
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    protected function getContent(string $id): array
    {
        $stmt = $this->getStmt(Core::fileReadOnce(__DIR__ . '/s_content.sql'));
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    protected function getMessages(array $ids, int $page = 0, int $limit = 20): array
    {
        $idstr = implode(',', array_fill(0, count($ids), '?'));
        $sql = Core::fileReadOnce(__DIR__ . '/s_messagesbyids.sql');
        $sql = str_replace(':ids', $idstr, $sql);
        $stmt = $this->getStmt($sql);

        foreach ($ids as $k => $id) {
            $stmt->bindValue($k + 1, $id);
        }
        self::bindPage($stmt, $page, $limit);
//        Core::echo(__METHOD__, $stmt);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    protected function searchMessages(string $subject, array $ids, int $page = 0, int $limit = 20): array
    {
        if (count($ids) && Core::getValue(0, $ids)) {
            $idstr = implode(',', array_fill(0, count($ids), '?'));
            $sql = Core::fileReadOnce(__DIR__ . '/s_messagesbyidssearch.sql');
            $sql = str_replace(':ids', $idstr, $sql);
            $stmt = $this->getStmt($sql);

            foreach ($ids as $k => $id) {
                $stmt->bindValue($k + 1, $id);
            }
        } else {
            $stmt = $this->getStmt(Core::fileReadOnce(__DIR__ . '/s_messagessearch.sql'));
        }
        $subject = self::prepareLike($subject);
        self::bindPage($stmt, $page, $limit);
        $stmt->bindParam(':subject', $subject);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    protected function getContactByMessageId(string $id): array
    {
        $stmt = $this->getStmt("SELECT * FROM v_allfrom where ids like '%:id%' ");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    protected function saveHeader(array $data, string $mailId): void
    {
        // mail_header name content
        // mail_header_mail mail_id mail_header_id
        if (count($data)) {
            $id = $this->runInsert('mail_header', Parser::TYPE_NAME . ',' . Parser::TYPE_HEADER_CONTENT, $data);
            $this->runInsert('mail_header_mail', 'mail_id,mail_header_id', ['mail_id' => $mailId, 'mail_header_id' => $id]);
        }
    }

    protected function saveAddress(array $data, string $mailId): void
    {
        // mail_address email name
        // mail_address_mail mail_id mail_address_id
        if (count($data)) {
            $id = $this->runInsert('mail_address', Parser::TYPE_EMAIL . ',' . Parser::TYPE_NAME, $data);
            $this->runInsert('mail_address_mail', 'mail_id,mail_address_id,type', ['mail_id' => $mailId, 'mail_address_id' => $id, 'type' => Core::getValue('type', $data)]);
        }
    }

    protected function saveContent(string|null $content, string $mailId): void
    {
        //mail_content content mail_id
        if ($content) {
//            Core::echo(__METHOD__, strlen($content));
            $id = $this->runInsert('mail_content', self::TYPE_BLOBCONTENT . ',contentlen,contentmd5', [self::TYPE_BLOBCONTENT => $content, 'contentlen' => strlen($content), 'contentmd5' => md5($content)]);
            $this->runUpdate($mailId, 'mail', ['mail_content_id'], ['mail_content_id' => $id]);
        }
    }

}
