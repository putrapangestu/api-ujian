<?php

namespace App\Repositories;

use App\Models\Ebook;

class EbookRepository extends BaseRepository implements EbookRepositoryInterface
{
    public function __construct(Ebook $model)
    {
        parent::__construct($model);
    }

    /**
     * Delete multiple ebooks by IDs
     *
     * @param array $ids
     * @return int
     */
    public function bulkDelete(array $ids): int
    {
        return $this->model->whereIn('id', $ids)->delete();
    }
}