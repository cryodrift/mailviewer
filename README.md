# Mailviewer

Mailviewer provides a web UI to explore emails (domains, messages, headers and content) and supporting CLI tools to import and manage mail data.

## Routes

All `@web` routes are provided by Api.php and are accessible under `/mail/{method}`. Available routes and parameters:

- GET /mail/domains
  - params:
    - domains_id (optional, string)
    - domains_page (optional, int; default 0)
    - contact_search (optional, string)

- GET /mail/messages
  - params:
    - domains_id (required, string)
    - messages_page (optional, int; default 0)
    - messages_id (optional, string)
    - contact_search (optional, string)
    - message_search (optional, string)

- GET /mail/headers
  - params:
    - messages_id (required, string)

- GET /mail/message
  - params:
    - messages_id (required, string)
    - parts_id (optional, string) — if provided, shows that part's message

- GET /mail/partview
  - params:
    - messages_id (required if parts_id missing)
    - parts_id (optional)
    - content_type (optional, string; default "text")

- GET /mail/parts
  - params:
    - messages_id (required)

- GET /mail/message_search
  - params:
    - domains_id (optional)
    - message_search (optional)

- GET /mail/contact_search
  - params:
    - contact_search (optional)

- GET /mail/cid
  - params:
    - messages_id (required)
    - cid (required, string) — content-id reference used in HTML bodies

- GET /mail/partcontent
  - params:
    - parts_id (required)
    - messages_id (required)

- POST /mail/refresh — fetch emails from server and update index
  - params: none (triggered by POST)

Parameters are typically passed via query string (GET) or request body (POST) as used by the UI templates in `src/mailviewer/ui/*`. 

## CLI

- Show available commands:
  php index.php /mailviewer/cli -help

- Example: import from mailbox file
  php index.php /mailviewer/cli import-mbox "C:/path/to/mailbox.mbox"

- Example: parse and index from POP3
  php index.php /mailviewer/cli import-pop3 --host pop.example.com --user me --pass secret
