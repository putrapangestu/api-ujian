<?php

namespace App\Repositories;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;

/**
 * Base Repository Abstract Class
 * 
 * Class ini menyediakan implementasi dasar dari BaseRepositoryInterface
 * dan dapat di-extend oleh repository-repository lainnya
 * 
 * @package App\Repositories
 * @author Development Team
 * @version 1.0.0
 */
abstract class BaseRepository implements BaseRepositoryInterface
{
    /**
     * Model yang digunakan oleh repository
     * 
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected Model $model;

    /**
     * BaseRepository constructor
     * 
     * @param Model $model Model yang akan digunakan
     */
    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /**
     * Mendapatkan semua data
     * 
     * @param array $columns Kolom yang ingin diambil
     * @param array $relations Relasi yang ingin dimuat
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function all(array $columns = ['*'], array $relations = []): Collection
    {
        return $this->model->with($relations)->get($columns);
    }

    /**
     * Mendapatkan data berdasarkan ID
     * 
     * @param int $id ID dari data yang dicari
     * @param array $relations Relasi yang ingin dimuat
     * @param array $columns Kolom yang ingin diambil
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function find(int $id, array $relations = [], array $columns = ['*']): ?Model
    {
        return $this->model->with($relations)->find($id, $columns);
    }

    /**
     * Membuat data baru
     * 
     * @param array $data Data yang akan disimpan
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function create(array $data): Model
    {
        return $this->model->create($data);
    }

    /**
     * Update data berdasarkan ID
     * 
     * @param int $id ID dari data yang akan diupdate
     * @param array $data Data yang akan diupdate
     * @return bool
     */
    public function update(int $id, array $data): bool
    {
        $model = $this->find($id);
        if (!$model) {
            return false;
        }
        return $model->update($data);
    }

    /**
     * Delete data berdasarkan ID
     * 
     * @param int $id ID dari data yang akan dihapus
     * @return bool
     */
    public function delete(int $id): bool
    {
        $model = $this->find($id);
        if (!$model) {
            return false;
        }
        return $model->delete();
    }

    /**
     * Mendapatkan query builder untuk custom query
     * 
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function query(): Builder
    {
        return $this->model->newQuery();
    }

    /**
     * Mendapatkan model yang digunakan
     * 
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function getModel(): Model
    {
        return $this->model;
    }
}