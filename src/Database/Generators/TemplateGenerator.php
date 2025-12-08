<?php
namespace Mita\UranusHttpServer\Database\Generators;

class TemplateGenerator extends AbstractTemplateGenerator
{
    public function getMigrationTemplate(): string
    {
        return file_get_contents(static::TEMPLATE_DIRECTORY . 'createTable.php.dist');
    }
}