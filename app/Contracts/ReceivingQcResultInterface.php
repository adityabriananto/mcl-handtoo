<?php

namespace App\Contracts;

interface ReceivingQcResultInterface
{
    const GOOD       = 'GOOD';
    const DEFFECTIVE = 'DEFFECTIVE';
    const REJECT_3PL = 'REJECT_TO_3PL';
}
