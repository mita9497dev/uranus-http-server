<?php 
namespace Mita\UranusHttpServer\Database\Migrations;

use Phinx\Migration\AbstractMigration;
use Illuminate\Database\Capsule\Manager as Capsule;

class BaseMigration extends AbstractMigration
{
    /**
     * @var \Illuminate\Database\Schema\MySqlBuilder
     */
    protected $schema;

    protected $connection = 'default';
    
    public function init()
    {
        $this->schema = (new Capsule)->schema($this->connection);
    }
}
