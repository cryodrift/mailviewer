<?php

//declare(strict_types=1);

namespace cryodrift\mailviewer;

use cryodrift\mailviewer\db\Repository;
use cryodrift\quicklinks\Web;
use cryodrift\fw\Config;
use cryodrift\fw\Context;
use cryodrift\fw\Core;
use cryodrift\fw\HtmlUi;
use cryodrift\fw\interface\Handler;
use cryodrift\fw\trait\ComponentHelper;
use cryodrift\fw\trait\PageHandler;

class Page implements Handler
{

    use PageHandler;
    use ComponentHelper;

    public function __construct(protected Repository $db, protected Web $ql, protected Config $config)
    {
    }

    public function handle(Context $ctx): Context
    {
        $ctx = $this->handlePage($ctx, $this->config);
        $ui = $ctx->response()->getContent();
        HtmlUi::setUistate(Core::getValue('getvar_uistate', $this->config, []));
        if (Core::getValue('cache_templates', $this->config, false)) {
            HtmlUi::cache();
            $ctx->response()->addAfterRunner(fn() => HtmlUi::cache());
        }
        $ui->setAttributes($this->componentHelper($ctx, $this->config));
        $theme = Core::getValue($ctx->request()->vars('theme', 'light'), ['light' => 'light', 'dark' => 'dark'], 'light');
        $ui->setAttributes(['themename' => $theme]);
        $newtheme = Core::getValue($theme, ['light' => 'Dark', 'dark' => 'Light']);
        $ui->setAttributes(['themetoggle' => $newtheme]);
        $ctx->request()->setVar('theme', $newtheme);
        $ui->setAttributes(['url' => '?' . http_build_query(Core::removeKeys(['theme'], $ctx->request()->vars()))]);
        $ui->setAttributes(['quicklinks' => str_replace('id="quicklinks" class="{{g-cont}}', 'id="quicklinks" class="{{g-cont}} g-phc', (string)$this->ql->show(clone $ctx)->response()->getContent())], false, false);

        return $this->outHelper($ctx, $ctx);
    }

}
