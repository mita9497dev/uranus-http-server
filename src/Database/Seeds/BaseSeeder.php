<?php

namespace Mita\UranusHttpServer\Database\Seeds;

use Phinx\Seed\AbstractSeed;
use Illuminate\Database\Capsule\Manager as DB;

abstract class BaseSeeder extends AbstractSeed
{
    /**
     * Get the database connection instance.
     *
     * @return \Illuminate\Database\Connection
     */
    protected function getConnection()
    {
        return DB::connection();
    }

    /**
     * Run the seeder.
     */
    public function run(): void
    {
        
    }

    /**
     * Truncate the given table.
     *
     * @param string $table
     * @return void
     */
    protected function truncate($table)
    {
        $this->getConnection()->table($table)->truncate();
        $this->output->writeln("<info>Truncated table:</info> $table");
    }

    /**
     * Insert data into a table.
     *
     * @param string $table
     * @param array $data
     * @return void
     */
    protected function insertData($table, array $data)
    {
        $this->getConnection()->table($table)->insert($data);
        $this->output->writeln("<info>Inserted data into table:</info> $table");
    }

    /**
     * Get the current timestamp.
     *
     * @return string
     */
    protected function getCurrentTimestamp()
    {
        return time();
    }
}
