<?php

namespace App\Services\Persistence\Contracts;

use App\Services\Persistence\DeleteExecutionContext;
use App\Services\Persistence\DeleteRequest;
use App\Services\Persistence\DeleteResult;

interface DeleteStrategyInterface
{
    /**
     * Mengembalikan nama kebijakan strategi, sesuai dengan App\Services\Persistence\DeletePolicy
     */
    public function getName(): string;

    /**
     * Menjalankan strategi penghapusan. 
     */
    public function execute(DeleteRequest $request, DeleteExecutionContext $context): DeleteResult;
}
