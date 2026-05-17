<?php

namespace App\Repositories;

interface EbookRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Delete multiple ebooks by IDs
     *
     * @param array $ids
     * @return int
     */
    public function bulkDelete(array $ids): int;
}