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
     * Operation date
     * @var \DateTime
     */
    public $date;

    /**
     * Clent identificator
     * @var int
     */
    public $client;

    /**
     * Client type
     * @var string
     */
    public $clientType;

    /**
     * Operation direction
     * @var string
     */
    public $direction;

    /**
     * Operation Amount
     * @var float
     */
    public $amount;

    /**
     * Currency code
     * @var string
     */
    public $currency;
}
