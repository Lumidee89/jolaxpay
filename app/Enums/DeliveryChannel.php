<?php

namespace App\Enums;

enum DeliveryChannel: string
{
    case Sms = 'sms';
    case Email = 'email';
    case Whatsapp = 'whatsapp';
    case InApp = 'in_app';
}
