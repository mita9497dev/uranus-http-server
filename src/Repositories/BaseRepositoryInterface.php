<?php 

namespace Mita\UranusHttpServer\Repositories;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;

interface BaseRepositoryInterface
{
    /**
     * @return Model
     */
    public function getModel(): string;

    /**
     * @param array $conditions
     * @param array $columns
     * @return Collection
     */
    public function get(array $conditions = [], array $columns = ['*']): Collection;

    /**
     * @param string $id
     * @return Model|null
     */
    public function find(string $id): ?Model;

    /**
     * @param array $data
     * @return Model
     */
    public function create(array $data): Model;

    /**
     * @param Model|string $model
     * @param array $data
     * @return Model
     */
    public function update($model, array $data): Model;

    /**
     * @param Model|string $model
     * @return bool
     */
    public function delete($model): bool;

    /**
     * @return Collection
     */
    public function all(): Collection;
}
