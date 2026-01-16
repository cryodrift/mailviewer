<?php

namespace cryodrift\mailviewer\ui\shared\search;

use cryodrift\shared\ui\search\Cmp;
use cryodrift\fw\Context;

class Comp extends Cmp
{
    public function __construct(Context $ctx, string $name, string $target, array $query_vars = [], array $dropdown = [])
    {
        $this->templatefile_html = __DIR__ . '/_.html';
        $this->templatefile_dropdown = __DIR__ . '/dropdown.html';
        parent::__construct($ctx, $name, $target, $query_vars, $dropdown);
    }

}
