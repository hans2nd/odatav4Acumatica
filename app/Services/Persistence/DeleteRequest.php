<?php

namespace App\Services\Persistence;

class DeleteRequest
{
    /**
     * @param array $records Daftar record atau ID yang diinstruksikan untuk dihapus
     */
    public function __construct(
        public readonly array $records
    ) {}
}
