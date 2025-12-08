<?php 
namespace Mita\UranusHttpServer\Transformers;

use League\Fractal\Serializer\ArraySerializer;

class TransformSerializer extends ArraySerializer
{
    public function collection($resourceKey, array $data): array
    {
        return $data;
    }

    public function item($resourceKey, array $data): array
    {
        return $data;
    }
}
