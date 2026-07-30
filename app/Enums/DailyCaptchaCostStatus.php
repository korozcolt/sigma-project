<?php

declare(strict_types=1);

namespace App\Enums;

enum DailyCaptchaCostStatus: string
{
    case Computed = 'computed';
    case NoData = 'no_data';
    case RechargeDetected = 'recharge_detected';
}
