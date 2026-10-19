<?php

namespace App\Enums;

enum AuctionStatus: string
{
    case Active = 'active';
    case Closed = 'closed';
    case Cancelled = 'cancelled';
}
