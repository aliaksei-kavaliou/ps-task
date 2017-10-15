<?php

namespace AppBundle\Tests\Command;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Description of CalculateCommandTest
 *
 * @author aliaksei
 */
class CalculateCommandTest extends KernelTestCase
{
    /**
     *
     * @var \Symfony\Component\DependencyInjection\ContainerInterface
     */
    private $container;

    /**
     * Test command execution
     */
    public function testCommand()
    {
        $command = new \AppBundle\Command\CalculateCommand();
        $command->setContainer($this->container);
        $file = 'no_file.csv';

        $input = new ArrayInput(['file' => &$file]);

        $output = new BufferedOutput();
        $this->assertEquals(1, $command->run($input, $output));
        $this->assertContains('File not found', $output->fetch());

        $file = static::$kernel->getRootDir()."/data/tests/input_ok.csv";
        $this->assertEquals(0, $command->run($input, $output));
        $o = $output->fetch();
        $this->assertContains('0.90', $o);
        $this->assertContains('8728', $o);

    }

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        parent::setUp();
        static::bootKernel();
        $this->container = static::$kernel->getContainer();
    }
}
