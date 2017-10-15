<?php

namespace AppBundle\Model;

/**
 * Description of OperationRecord
 *
 * @author aliaksei
 */
class OperationRecord
{
    const CLIENT_TYPE_NATURAL = "natural";
    const CLIENT_TYPE_LEGAL = "legal";
    const CASHE_DIRECTION_IN = "cash_in";
    const CASHE_DIRECTION_OUT = "cash_out";
    const CURRENCY_EUR = "EUR";
    const CURRENCY_USD = "USD";
    const CURRENCY_JPY = "JPY";

    /**
     *
     * @var \DateTime
     */
    public $date;

    /**
     *
     * @var int
     */
    public $client;

    /**
     *
     * @var string
     */
    public $clientType;

    /**
     *
     * @var string
     */
    public $direction;

    /**
     *
     * @var float
     */
    public $amount;

    /**
     *
     * @var string
     */
    public $currency;
}
