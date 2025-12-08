<?php 
namespace Mita\UranusHttpServer\Transformers;

use Illuminate\Database\Eloquent\Model;
use League\Fractal\TransformerAbstract as FractalTransformerAbstract;
use Mita\UranusHttpServer\Contracts\AuthenticatableInterface;

abstract class AbstractTransformer extends FractalTransformerAbstract
{
    protected string $resourceKeyCollection = 'data';
    protected AuthenticatableInterface $authenticatable;

    public function __construct(AuthenticatableInterface $authenticatable, array $defaultIncludes = [], array $availableIncludes = [])
    {
        $this->authenticatable = $authenticatable;
        $this->availableIncludes = $availableIncludes;
        $this->defaultIncludes = $defaultIncludes;
    }

    /**
     * Transform the data.
     *
     * @param $data
     * @return array
     */
    abstract public function transform($data): array;
}
