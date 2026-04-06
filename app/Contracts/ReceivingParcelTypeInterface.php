<?php

namespace App\Contracts;

interface ReceivingParcelTypeInterface
{
    const FAILED_DELIVERY = 'FAILED_DELIVERY';
    const CUSTOMER_RETURN = 'CUSTOMER_RETURN';
}
