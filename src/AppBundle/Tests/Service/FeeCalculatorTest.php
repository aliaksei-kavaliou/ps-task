<?php

namespace AppBundle\Tests\Service;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use AppBundle\Exception\FileException;
use AppBundle\Service\FeeCalculator;
use AppBundle\Model\OperationRecord;
use \Symfony\Component\Translation\TranslatorInterface;

/**
 * FeeCalculatorTest
 *
 * @author aliaksei
 */
class FeeCalculatorTest extends KernelTestCase
{
    /**
     *
     * @var Symfony\Component\DependencyInjection\ContainerInterface
     */
    protected $container;

    public function testReadFileNoFileException()
    {
        $badFile = "not_existing_file.csv";

        $refl = new \ReflectionClass(FeeCalculator::class);
        $readFile = $refl->getMethod('readFile');
        $readFile->setAccessible(true);

        $calc = new FeeCalculator($this->container->get('translator'), [], []);

        $this->expectException(FileException::class);
        $this->expectExceptionMessage('File not found or can not be read');

        foreach ($readFile->invoke($calc, $badFile) as $line) {
            break;
        }
    }

    public function testReadFile()
    {
        $file = static::$kernel->getRootDir()."/data/tests/input_ok.csv";
        $out = new OperationRecord();
        $out->date = new \DateTime('2015-01-01');
        $out->client = 1;
        $out->clientType = 'natural';
        $out->direction = 'cash_out';
        $out->amount = 1200;
        $out->currency = 'EUR';

        $refl = new \ReflectionClass(FeeCalculator::class);
        $readFile = $refl->getMethod('readFile');
        $readFile->setAccessible(true);
        $calc = new FeeCalculator($this->createMock(TranslatorInterface::class), [], []);

        foreach ($readFile->invoke($calc, $file) as $record) {
            $this->assertEquals($record, $out);
            break;
        }
    }

    public function testParseLineException()
    {
        $in = ['2017-01-01', 1];
        $refl = new \ReflectionClass(FeeCalculator::class);
        $method = $refl->getMethod('parseLine');
        $method->setAccessible(true);

        $this->expectException(FileException::class);
        $this->expectExceptionMessage('Bad file content');

        $method->invoke(new FeeCalculator($this->container->get('translator'), [], []), $in);
    }

    public function testParseLine()
    {
        $in = ['2017-01-01', 1, 'natural', 'cash_in', 500, 'EUR'];
        $out = new OperationRecord();
        $out->date = new \DateTime('2017-01-01');
        $out->client = 1;
        $out->clientType = 'natural';
        $out->direction = 'cash_in';
        $out->amount = 500;
        $out->currency = 'EUR';

        $refl = new \ReflectionClass(FeeCalculator::class);
        $method = $refl->getMethod('parseLine');
        $method->setAccessible(true);
        $result = $method->invoke(new FeeCalculator($this->createMock(TranslatorInterface::class), [], []), $in);
        $this->assertEquals($out, $result);
    }

    public function testProcessRecord()
    {
        $calc = $this->getMockBuilder(FeeCalculator::class)
            ->setConstructorArgs([$this->container->get('translator'), $this->getFeeCfg(), $this->getRates()])
            ->setMethods(['processRecordIn', 'processRecordOut'])
            ->getMock();
        $calc->method('processRecordIn')->willReturn(1.0);
        $calc->method('processRecordOut')->willReturn(10.0);

        $refl = new \ReflectionClass(FeeCalculator::class);
        $method = $refl->getMethod('processRecord');
        $method->setAccessible(true);

        $in = new OperationRecord();
        $in->currency = 'EUR';
        $in->clientType = 'legal';
        $in->direction = 'cash_in';
        $this->assertEquals(1.0, $method->invoke($calc, $in));

        $in->direction = 'cash_out';
        $this->assertEquals(10.0, $method->invoke($calc, $in));

        $in->direction = 'unknown';
        $this->expectExceptionMessage('Unknown cash direction unknown');
        $this->assertEquals(1.0, $method->invoke($calc, $in));

        $in->direction = 'cash_in';
        $in->currency = 'BYR';
        $this->expectExceptionMessage('Currency is not supported');
        $method->invoke($calc, $in);

        $in->currency = 'EU';
        $in->clientType = 'unknown';
        $this->expectExceptionMessage('Wrong client type unknown');
        $method->invoke($calc, $in);
    }

    public function testProcessRecordIn()
    {
        $calc = new FeeCalculator(
            $this->createMock(TranslatorInterface::class),
            $this->getFeeCfg(),
            $this->getRates()
        );
        $refl = new \ReflectionClass(FeeCalculator::class);
        $method = $refl->getMethod('processRecord');
        $method->setAccessible(true);

        $in = new OperationRecord();
        $in->date = new \DateTime('2017-01-01');
        $in->client = 1;
        $in->clientType = 'legal';
        $in->direction = 'cash_in';
        $in->amount = 500;
        $in->currency = 'EUR';
        $this->assertEquals(0.15, $method->invoke($calc, $in));

        $in->amount = 50000;
        $this->assertEquals(5, $method->invoke($calc, $in));

        $in->amount = 5000;
        $in->currency = 'USD';
        $this->assertEquals(1.5, $method->invoke($calc, $in));

        $in->currency = 'JPY';
        $this->assertEquals(2, $method->invoke($calc, $in));
    }

    public function testProcessRecordOut()
    {
        $calc = new FeeCalculator(
            $this->createMock(TranslatorInterface::class),
            $this->getFeeCfg(),
            $this->getRates()
        );
        $refl = new \ReflectionClass(FeeCalculator::class);
        $method = $refl->getMethod('processRecordOut');
        $method->setAccessible(true);
        $map = $refl->getProperty('clientMap');
        $map->setAccessible(true);
        $map->setValue(
            $calc,
            [
                1 => [
                    'monday' => new \DateTime('2017-01-02'),
                    'sunday' => new \DateTime('2017-01-08'),
                    'cnt' => 1,
                    'amount' => 300,
                ],
            ]
        );

        $in = new OperationRecord();
        $in->date = new \DateTime('2017-01-03');
        $in->client = 1;
        $in->clientType = 'natural';
        $in->direction = 'cash_out';
        $in->amount = 500;
        $in->currency = 'EUR';

        $this->assertEquals(0, $method->invoke($calc, $in));

        $in->amount = 2000;
        $in->date = new \DateTime('2017-01-05');
        $this->assertEquals(5.4, $method->invoke($calc, $in));

        $map->setValue(
            $calc,
            [
                1 => [
                    'monday' => new \DateTime('2017-01-02'),
                    'sunday' => new \DateTime('2017-01-08'),
                    'amount' => 600,
                    'cnt' => 3,
                ],
            ]
        );
        $this->assertEquals(6, $method->invoke($calc, $in));

        $in->date = new \DateTime('2017-01-09');
        $this->assertEquals(3, $method->invoke($calc, $in));
        $this->assertEquals(1, $map->getValue($calc)[1]['cnt']);

        $map->setValue(
            $calc,
            [
                1 => [
                    'monday' => new \DateTime('2017-01-09'),
                    'sunday' => new \DateTime('2017-01-15'),
                    'amount' => 1300,
                    'cnt' => 2,
                ],
            ]
        );
        $in->date = new \DateTime('2017-01-10');
        $in->amount = 100;
        $this->assertEquals(0.3, $method->invoke($calc, $in));
    }

    public function testGetOutFee()
    {
        $calc = new FeeCalculator(
            $this->createMock(TranslatorInterface::class),
            $this->getFeeCfg(),
            $this->getRates()
        );
        $refl = new \ReflectionClass(FeeCalculator::class);
        $method = $refl->getMethod('getOutFee');
        $method->setAccessible(true);

        $cfg = $this->getFeeCfg();
        $this->assertEquals(1.5, $method->invoke($calc, 500, 'EUR', $cfg['legal']));
        $this->assertEquals(0.5, $method->invoke($calc, 50, 'EUR', $cfg['legal']));
        $this->assertEquals(2.7, $method->invoke($calc, 900, 'EUR', $cfg['natural']));
        $this->assertEquals(0.27, $method->invoke($calc, 90, 'EUR', $cfg['natural']));
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

    /**
     * Get rate config
     * @return array
     */
    private function getRates()
    {
        return [
            'EUR' => ['rate' => 1, 'cnt' => 100],
            'USD' => ['rate' => 1.1497, 'cnt' => 100],
            'JPY' => ['rate' => 129.53, 'cnt' => 1],
        ];
    }

    /**
     * Get fee config
     * @return array
     */
    private function getFeeCfg()
    {
        return [
            'legal'   => [
                'in_percent' => 0.03,
                'in_max' => 5,
                'out_percent' => 0.3,
                'out_min' => 0.5,
                'out_max_weekly' => null,
                'out_max_weekly_discount' => null,
            ],
            'natural' => [
                'in_percent' => 0.03,
                'in_max' => 5,
                'out_percent' => 0.3,
                'out_min' => null,
                'out_max_weekly' => 1000,
                'out_max_weekly_discount' => 3,
            ],
        ];
    }
}
