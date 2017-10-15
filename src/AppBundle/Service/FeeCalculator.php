<?php

namespace AppBundle\Service;

use AppBundle\Exception\FileException;
use AppBundle\Exception\ProcessException;
use Symfony\Component\Translation\TranslatorInterface;
use AppBundle\Model\OperationRecord;

/**
 * Description of FeeCalculator
 *
 * @author aliaksei
 */
class FeeCalculator
{
    /**
     *
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * Currency exchange rates
     * @var array
     */
    private $rates;

    /**
     * Fee settings;
     * @var array
     */
    private $feeCfg;

    /**
     *
     * @var array ['client' => ['monday' => 'date', 'sunday' => 'date', 'cnt' => 'int']]
     */
    private $clientMap = [];

    /**
     *
     * @param TranslatorInterface $translator
     * @param array               $feeCfg
     * @param array               $rates
     */
    public function __construct(TranslatorInterface $translator, array $feeCfg, array $rates)
    {
        $this->translator = $translator;
        $this->feeCfg = $feeCfg;
        $this->rates = $rates;
    }

    /**
     * Main function
     * @param string $file
     * @return \Generator|flow
     */
    public function calculate($file)
    {
        foreach ($this->readFile($file) as $record) {
            yield ['amount' => $this->processRecord($record), 'record' => $record];
        }
    }

    /**
     * Read input file content
     * @param string $file File name
     * @return \Generator|OperationRecord[]
     * @throws FileException
     */
    protected function readFile($file)
    {
        if (!is_readable($file) || !($handle = fopen("$file", "r"))) {
            throw new FileException($this->translator->trans('File not found or can not be read'));
        }

        try {
            while ($line = fgetcsv($handle, 1000)) {
                yield $this->parseLine($line);
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Parse csv line to OperationRecord
     * @param array $line
     * @return OperationRecord
     * @throws FileException
     */
    protected function parseLine(array $line)
    {
        if (6 !== count($line)) {
            throw new FileException($this->translator->trans('Bad file content'));
        }

        $out = new OperationRecord();
        $out->date = new \DateTime($line[0]);
        $out->client = $line[1];
        $out->clientType = $line[2];
        $out->direction = $line[3];
        $out->amount = $line[4];
        $out->currency = $line[5];

        return $out;
    }

    /**
     * Process operation record and calculate fee
     * @param OperationRecord $record
     * @return float Calculated fee
     * @throws ProcessException
     */
    protected function processRecord(OperationRecord $record)
    {

        if (!array_key_exists($record->currency, $this->rates)) {
            throw new ProcessException($this->translator->trans("Currency is not supported"));
        }

        if (!array_key_exists($record->clientType, $this->feeCfg)) {
            throw new ProcessException($this->translator->trans("Wrong client type %type%", ['%type%' => $record->clientType]));
        }

        switch ($record->direction) {
            case OperationRecord::CASHE_DIRECTION_IN:
                return $this->processRecordIn($record);
            case OperationRecord::CASHE_DIRECTION_OUT:
                return $this->processRecordOut($record);
        }

        throw new ProcessException($this->translator->trans("Unknown cash direction %direction%", ['%direction%' => $record->direction]));
    }

    /**
     * Calculate fee for in operation
     * @param OperationRecord $record
     * @return float
     */
    protected function processRecordIn(OperationRecord $record)
    {
        $cfg = $this->feeCfg[$record->clientType];
        $raw = $record->amount * $cfg['in_percent'] / 100;
        $result = !empty($cfg['in_max']) && ($raw / $this->rates[$record->currency]['rate'] > $cfg['in_max'])
            ? $cfg['in_max'] : $raw;

        return ceil($result * $this->rates[$record->currency]['cnt']) /
            $this->rates[$record->currency]['cnt'];
    }

    /**
     * Calculate fee for out operation
     * @param OperationRecord $record
     */
    protected function processRecordOut(OperationRecord $record)
    {
        $cfg = $this->feeCfg[$record->clientType];
        $amount = $record->amount / $this->rates[$record->currency]['rate'];

        if (null === $cfg['out_max_weekly']) {
            return $this->getOutFee($record->amount, $record->currency, $cfg);
        }

        if (!isset($this->clientMap[$record->client])
            || $record->date->getTimestamp() > $this->clientMap[$record->client]['sunday']->getTimeStamp()
        ) {
            $this->clientMap[$record->client] = [
                'monday' => new \DateTime($record->date->format('Y-m-d').' monday this week'),
                'sunday' => new \DateTime($record->date->format('Y-m-d').' sunday this week'),
                'amount' => $amount,
                'cnt' => 1,
            ];

            return $amount > $cfg['out_max_weekly'] ?
                $this->getOutFee($amount - $cfg['out_max_weekly'], $record->currency, $cfg) : 0;
        }

        $this->clientMap[$record->client]['cnt']++;
        $this->clientMap[$record->client]['amount'] += $amount;

        if (empty($cfg['out_max_weekly_discount'])
            || $this->clientMap[$record->client]['cnt'] > $cfg['out_max_weekly_discount']
        ) {
            return $this->getOutFee($amount, $record->currency, $cfg);
        }

        if ($this->clientMap[$record->client]['amount'] >  $cfg['out_max_weekly']) {
            $delta = $this->clientMap[$record->client]['amount'] - $cfg['out_max_weekly'];
            $calculateFrom = $delta > $amount ? $amount : $delta;

            return $this->getOutFee($calculateFrom, $record->currency, $cfg);
        }

        return 0;
    }

    /**
     * Base out fee calculation
     * @param float  $amount
     * @param string $currency Operation currency
     * @param array  $cfg
     * @return float Fee in original currency
     */
    protected function getOutFee($amount, $currency, $cfg)
    {
        $raw = $amount * $cfg['out_percent'] / 100;
        $result = !empty($cfg['out_min']) && $raw < $cfg['out_min'] ? $cfg['out_min'] : $raw;

        return ceil($result * $this->rates[$currency]['rate'] * $this->rates[$currency]['cnt']) / $this->rates[$currency]['cnt'];
    }
}
