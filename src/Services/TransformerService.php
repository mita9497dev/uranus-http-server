<?php 
namespace Mita\UranusHttpServer\Services;

use Illuminate\Support\Collection as SupportCollection;
use League\Fractal\Manager;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\Item;
use Mita\UranusHttpServer\Transformers\AbstractTransformer;
use Mita\UranusHttpServer\Transformers\TransformSerializer;

class TransformerService 
{
    protected Manager $fractal;

    public function __construct(Manager $fractal)
    {
        $fractal->setSerializer(new TransformSerializer());
        $this->fractal = $fractal;
    }

    /**
     * Transform a single item.
     *
     * @param mixed $data
     * @param AbstractTransformer $transformer
     * @return array
     */
    public function transformItem($data, AbstractTransformer $transformer): array
    {
        $resource = new Item($data, $transformer);
        return $this->fractal->createData($resource)->toArray();
    }

    /**
     * Transform a collection of items.
     *
     * @param SupportCollection $data
     * @param AbstractTransformer $transformer
     * @return array
     */
    public function transformCollection(SupportCollection $data, AbstractTransformer $transformer): array
    {
        $resource = new Collection($data, $transformer);
        return $this->fractal->createData($resource)->toArray();
    }

    /**
     * Transform an array of items.
     *
     * @param array $data
     * @param AbstractTransformer $transformer
     * @return array
     */
    public function transformArray(array $data, AbstractTransformer $transformer): array
    {
        $resource = new Collection($data, $transformer);
        return $this->fractal->createData($resource)->toArray();
    }
}