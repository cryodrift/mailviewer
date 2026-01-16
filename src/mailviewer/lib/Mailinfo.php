<?php 
//declare(strict_types=1);

namespace cryodrift\mailviewer\lib;


class Mailinfo
{
    public $id = 0;
    public $len = 0;

    /**
     * Mailinfo constructor.
     * @param int $id
     * @param int $len
     */
    public function __construct(int $id, int $len)
    {
        $this->id = $id;
        $this->len = $len;
    }

}
