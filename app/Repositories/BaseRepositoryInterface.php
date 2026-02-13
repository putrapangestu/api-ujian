<?php

namespace App\Repositories;

/**
 * Base Repository Interface
 * 
 * Interface ini mendefinisikan method-method dasar yang harus dimiliki
 * oleh setiap repository dalam aplikasi
 * 
 * @package App\Repositories
 * @author Development Team
 * @version 1.0.0
 */
interface BaseRepositoryInterface
{
    /**
     * Mendapatkan semua data
     * 
     * @param array $columns Kolom yang ingin diambil
     * @param array $relations Relasi yang ingin dimuat
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function all(array $columns = ['*'], array $relations = []);

    /**
     * Mendapatkan data berdasarkan ID
     * 
     * @param int $id ID dari data yang dicari
     * @param array $relations Relasi yang ingin dimuat
     * @param array $columns Kolom yang ingin diambil
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function find(int $id, array $relations = [], array $columns = ['*']);

    /**
     * Membuat data baru
     * 
     * @param array $data Data yang akan disimpan
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function create(array $data);

    /**
     * Update data berdasarkan ID
     * 
     * @param int $id ID dari data yang akan diupdate
     * @param array $data Data yang akan diupdate
     * @return bool
     */
    public function update(int $id, array $data): bool;

    /**
     * Delete data berdasarkan ID
     * 
     * @param int $id ID dari data yang akan dihapus
     * @return bool
     */
    public function delete(int $id): bool;
}