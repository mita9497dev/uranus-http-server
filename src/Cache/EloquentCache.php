<?php
namespace Mita\UranusHttpServer\Cache;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Psr\SimpleCache\CacheInterface;

class EloquentCache 
{
    /** @var CacheInterface */
    protected $cache;

    /** @var string */
    protected $prefix;

    /** @var int */
    protected $ttl;

    /**
     * @param CacheInterface $cache
     * @param int $ttl
     * @param string $prefix
     */
    public function __construct(CacheInterface $cache, int $ttl = 3600, string $prefix = '') 
    {
        $this->cache = $cache;
        $this->prefix = $prefix;
        $this->ttl = $ttl;
    }

    /**
     * @param string $key
     * @return string
     */
    protected function getCacheKey(string $key): string
    {
        $sanitizedKey = preg_replace('/[{}()\/\\@:]/', '_', $key);
        return sprintf('%s_%s', $this->prefix, $sanitizedKey);
    }

    /**
     * @param Model $model
     * @param string $key 
     * @return string
     */
    protected function getModelCacheKey(Model $model, string $key): string
    {
        $tableName = preg_replace('/[{}()\/\\@:]/', '_', $model->getTable());
        $sanitizedKey = preg_replace('/[{}()\/\\@:]/', '_', $key);
        return $this->getCacheKey(sprintf('%s_%s', $tableName, $sanitizedKey));
    }

    /**
     * Convert cached data to Model or Collection
     * 
     * @param Model $model
     * @param mixed $cached
     * @return Model|Collection|null
     */
    protected function hydrateFromCache(Model $model, $cached)
    {
        if (!$cached) {
            return null;
        }

        try {
            // Đảm bảo dữ liệu là array
            $data = $this->ensureArray($cached);

            // Nếu là array of models
            if (isset($data[0])) {
                $models = array_map(function ($item) use ($model) {
                    return $this->createModelInstance($model, $item);
                }, $data);
                return new Collection($models);
            }

            // Nếu là single model
            return $this->createModelInstance($model, $data);
            
        } catch (\Exception $e) {
            // Log error if needed
            return null;
        }
    }

    /**
     * Ensure data is array format
     * 
     * @param mixed $data
     * @return array
     */
    protected function ensureArray($data): array 
    {
        if (is_array($data)) {
            return $data;
        }
        
        if (is_string($data)) {
            $decoded = json_decode($data, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }
        
        throw new \InvalidArgumentException('Invalid cache data format');
    }

    /**
     * Create new model instance from data
     * 
     * @param Model $model
     * @param array $attributes
     * @return Model
     */
    protected function createModelInstance(Model $model, array $attributes): Model
    {
        // Tạo instance mới
        $instance = new $model;
        
        // Set các attributes
        foreach ($attributes as $key => $value) {
            $instance->setAttribute($key, $value);
        }

        // Đánh dấu là model đã tồn tại (không phải new model)
        $instance->exists = true;
        
        return $instance;
    }

    /**
     * @param array $conditions
     * @param array $columns
     * @param Model $model
     * @return Collection
     */
    public function get(Model $model, array $conditions = [], array $columns = ['*']): Collection
    {
        $key = $this->getModelCacheKey($model, 'get:' . md5(serialize($conditions) . serialize($columns)));
        
        try {
            if ($cached = $this->cache->get($key)) {
                return $this->hydrateFromCache($model, $cached) ?? new Collection();
            }
        } catch (\Exception $e) {
            // Fallback to database
        }

        $result = $model->where($conditions)->get($columns);
        $this->cache->set($key, $result->toArray(), $this->ttl);
        
        return $result;
    }

    /**
     * @param string $id
     * @param Model $model
     * @return Model|null
     */
    public function find(Model $model, string $id): ?Model
    {
        $key = $this->getModelCacheKey($model, "id:$id");
        
        try {
            if ($cached = $this->cache->get($key)) {
                return $this->hydrateFromCache($model, $cached);
            }
        } catch (\Exception $e) {
            // Fallback to database
        }

        $result = $model->find($id);
        if ($result) {
            $this->cache->set($key, $result->toArray(), $this->ttl);
        }
        
        return $result;
    }

    /**
     * @param Model $model
     * @param array $data
     * @return Model
     */
    public function create(Model $model, array $data): Model
    {
        $result = $model->create($data);
        $key = $this->getModelCacheKey($model, "id:{$result->id}");
        $this->cache->set($key, $result->toArray(), $this->ttl);
        return $result;
    }

    /**
     * @param Model $model
     * @param array $data
     * @return Model
     */
    public function update(Model $model, array $data): Model
    {
        $model->update($data);
        $key = $this->getModelCacheKey($model, "id:{$model->id}");
        $this->cache->set($key, $model->toArray(), $this->ttl);
        return $model;
    }

    /**
     * @param Model $model
     * @return bool
     */
    public function delete(Model $model): bool
    {
        $key = $this->getModelCacheKey($model, "id:{$model->id}");
        $this->cache->delete($key);
        return $model->delete();
    }

    /**
     * @param Model $model
     * @return bool
     */
    public function save(Model $model): bool
    {
        $result = $model->save();
        if ($result) {
            $key = $this->getModelCacheKey($model, "id:{$model->id}");
            $this->cache->set($key, $model->toArray(), $this->ttl);
        }
        return $result;
    }

    /**
     * Clear cache for specific model
     * @param Model $model
     */
    public function flush(Model $model): void
    {
        $key = $this->getModelCacheKey($model, "id:{$model->id}");
        $this->cache->delete($key);
        
        $listKey = $this->getModelCacheKey($model, 'list');
        $this->cache->delete($listKey);
    }
}