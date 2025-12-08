<?php
namespace Mita\UranusHttpServer\Database\Generators;

use Phinx\Migration\CreationInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Migration
 *
 * @package App\Database\Migrations
 */
abstract class AbstractTemplateGenerator implements CreationInterface
{
    
    const TEMPLATE_DIRECTORY = __DIR__ . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR;
    
    /**
     * @var null|\Symfony\Component\Console\Input\InputInterface
     */
    protected $input;
    /**
     * @var null|\Symfony\Component\Console\Output\OutputInterface
     */
    protected $output;
    
    protected $table;
    
    /**
     * CreationInterface constructor.
     *
     * @param \Symfony\Component\Console\Input\InputInterface|null   $input
     * @param \Symfony\Component\Console\Output\OutputInterface|null $output
     */
    public function __construct(InputInterface $input = null, OutputInterface $output = null)
    {
        $this->input = $input;
        $this->output = $output;
        $this->getParams();
    }
    
    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     *
     * @return \Phinx\Migration\CreationInterface
     */
    public function setInput(InputInterface $input)
    {
        // TODO: Implement setInput() method.
    }
    
    /**
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return \Phinx\Migration\CreationInterface
     */
    public function setOutput(OutputInterface $output)
    {
        // TODO: Implement setOutput() method.
    }
    
    /**
     * @return \Symfony\Component\Console\Input\InputInterface
     */
    public function getInput(): InputInterface
    {
        return $this->input;
    }
    
    /**
     * @return \Symfony\Component\Console\Output\OutputInterface
     */
    public function getOutput(): OutputInterface
    {
        // TODO: Implement getOutput() method.
        return $this->output;
    }
    
    /**
     * Get the migration template.
     *
     * This will be the content that Phinx will amend to generate the migration file.
     *
     * @return string The content of the template for Phinx to amend.
     */
    public function getMigrationTemplate(): string
    {
        // TODO: Implement getMigrationTemplate() method.
        return '';
    }
    
    /**
     * Post Migration Creation.
     *
     * Once the migration file has been created, this method will be called, allowing any additional
     * processing, specific to the template to be performed.
     *
     * @param string $migrationFilename The name of the newly created migration.
     * @param string $className         The class name.
     * @param string $baseClassName     The name of the base class.
     *
     * @return void
     */
    public function postMigrationCreation($migrationFilename, $className, $baseClassName): void
    {
        // TODO: Implement postMigrationCreation() method.
    }
    
    private function getParams()
    {

    }
}