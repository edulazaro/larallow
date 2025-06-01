<?php

namespace EduLazaro\Larallow\Tests\Support\Enums;

enum ClientPermissions: string
{
    case ViewAccount = 'view-account';
    case MakePayment = 'make-payment';
}
