<?php

//declare(strict_types=1);

/**
 * @env MAILVIEWER_POP3STORAGE="G_ROOTDIR.cryodrift/users/"
 * @env USER_STORAGEDIRS="G_ROOTDIR.cryodrift/users/"
 * @env USER_USEAUTH=true
 * @env MAILVIEWER_CACHEDIR="G_ROOTDIR.cryodrift/cache/mailviewer/"
 */

use cryodrift\fw\Core;

if (!isset($ctx)) {
    $ctx = Core::newContext(new \cryodrift\fw\Config());
}

$cfg = $ctx->config();

if (Core::env('USER_USEAUTH')) {
    \cryodrift\user\Auth::addConfigs($ctx, [
      'mail',
    ]);
}

$cfg[\cryodrift\mailviewer\Pop3Cli::class] = [
  'storagedir' => Core::env('MAILVIEWER_POP3STORAGE'),
];
$cfg[\cryodrift\mailviewer\Cache::class] = [
  'cachedir' => Core::env('MAILVIEWER_CACHEDIR'),
];

$cfg[\cryodrift\mailviewer\Page::class] = \cryodrift\mailviewer\Api::class;
$cfg[\cryodrift\mailviewer\Api::class] = [
  'templatepath' => __DIR__ . '/ui/base/main.html',
  'title' => 'Mail',
  'description' => 'Personal e-Mail Viewer',
  'langcode' => 'de',
  'cacheDuration' => 60 * 60 * 24 * 7,
  'testdata' => 'mailviewer/test.json',
    // uistate parameters for a sessionless user experience
  'getvar_uistate' => ['theme', 'content_type', 'tabs'],
  'getvar_defaults' => [
    'messages_id' => '',
    'parts_id' => '',
    'domains_id' => '',
    'cid' => '',
    'messages_page' => 0,
    'domains_page' => 0,
    'domain_search' => '',
    'message_search' => '',
    'theme' => '',
    'content_type' => '',
    'tabs' => '',
  ],
  'componenthandler' => \cryodrift\mailviewer\Api::class,
  'components' => [
    'domains',
    'messages',
    'headers',
    'message',
    'partview',
    'parts',
    'message_search',
    'domain_search',
//      'cid',
//      'partcontent',
  ]
];
$cfg[\cryodrift\mailviewer\DbCli::class] = [
  'db' => \cryodrift\mailviewer\db\Repository::class,
];
$cfg[\cryodrift\mailviewer\db\Repository::class] = [
  'storagedir' => Core::env('USER_STORAGEDIRS')
];

\cryodrift\fw\Router::addConfigs($ctx, [
  'mail/cli' => \cryodrift\mailviewer\Cli::class,
], \cryodrift\fw\Router::TYP_CLI);

\cryodrift\fw\Router::addConfigs($ctx, [
  'mail' => \cryodrift\mailviewer\Page::class,
  'mail/api' => \cryodrift\mailviewer\Api::class,
], \cryodrift\fw\Router::TYP_WEB);

\cryodrift\fw\FileHandler::addConfigs($ctx, [
  'mailviewer/mobile.js' => 'mailviewer/ui/base/mobile.js'
]);
