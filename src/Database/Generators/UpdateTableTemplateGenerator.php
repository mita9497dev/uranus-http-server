<?php
namespace Mita\UranusHttpServer\Database\Generators;
use Illuminate\Support\Str;

class UpdateTableTemplateGenerator extends AbstractTemplateGenerator
{
    public function getMigrationTemplate(): string
    {
        $name = $this->input->getArgument('name');
        $template = file_get_contents(static::TEMPLATE_DIRECTORY . 'updateTable.php.dist');
        
        if (!preg_match('/^Update[A-Z][a-zA-Z]+Table/', $name)) {
            throw new \InvalidArgumentException('Migration name must follow the pattern "Update[TableName]Table...".');
        }

        $name = Str::after($name, 'Update');
        $name = Str::before($name, 'Table');
        $name = Str::snake($name);
        
        $connectionName = getenv('DB_CONNECTION') ?: 'default';
        
        $template = str_replace('$tableName', $name, $template);
        $template = str_replace('$connectionName', $connectionName, $template);

        return $template;
    }
}
