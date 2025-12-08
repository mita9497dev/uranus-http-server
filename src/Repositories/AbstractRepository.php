<?php 
namespace Mita\UranusHttpServer\Repositories;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;
use Mita\UranusHttpServer\Cache\EloquentCache;

abstract class AbstractRepository implements BaseRepositoryInterface
{
    /** @var Model */
    protected $model;
    
    /** @var EloquentCache|null */
    protected $cache;
    
    /**
     * @param Model $model
     * @param EloquentCache|null $cache
     */
    public function __construct(Model $model, ?EloquentCache $cache = null)
    {
        $this->model = $model;
        $this->cache = $cache;
    }

    abstract public function getModel(): string;

    /**
     * Check if repository uses cache
     */
    protected function hasCache(): bool 
    {
        return $this->cache !== null;
    }

    /**
     * @return Collection
     */
    public function all(): Collection
    {
        // if ($this->hasCache()) {
        //     return $this->cache->get($this->model);
        // }
        return $this->model->newQuery()->get();
    }

    /**
     * @param array $conditions
     * @param array $columns
     * @return Collection
     */
    public function get(array $conditions = [], array $columns = ['*']): Collection
    {
        // if ($this->hasCache()) {
        //     return $this->cache->get($this->model, $conditions, $columns);
        // }
        return $this->model->newQuery()->where($conditions)->get($columns);
    }

    /**
     * @param string $id
     * @return Model|null
     */
    public function find(string $id): ?Model
    {
        if ($this->hasCache()) {
            return $this->cache->find($this->model, $id);
        }
        return $this->model->newQuery()->find($id);
    }

    /**
     * @param array $data
     * @return Model
     */
    public function create(array $data): Model
    {
        if ($this->hasCache()) {
            return $this->cache->create($this->model, $data);
        }
        return $this->model->create($data);
    }

    /**
     * @param Model|string $model
     * @param array $data
     * @return Model
     */
    public function update($model, array $data): Model
    {
        if (is_string($model)) {
            $model = $this->find($model);
        }

        if ($this->hasCache()) {
            return $this->cache->update($model, $data);
        }

        $model->update($data);
        return $model;
    }

    /**
     * @param Model|string $model
     * @return bool
     */
    public function delete($model): bool
    {
        if (is_string($model)) {
            $model = $this->find($model);
        }

        if ($this->hasCache()) {
            return $this->cache->delete($model);
        }

        return $model->delete();
    }

    /**
     * @param Model $model
     * @return bool
     */
    public function save(Model $model): bool
    {
        if ($this->hasCache()) {
            return $this->cache->save($model);
        }
        return $model->save();
    }

    /**
     * @param string|array $relation
     * @return Builder
     */
    public function with($relation): Builder
    {
        return $this->model->newQuery()->with($relation);
    }

    /**
     * Clear cache if exists
     */
    public function flush(): void
    {
        if ($this->hasCache()) {
            $this->cache->flush($this->model);
        }
    }
}