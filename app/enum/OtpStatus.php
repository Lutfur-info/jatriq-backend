<?php

namespace App\enum;

enum OtpStatus
{
    case Valid;
    case Invalid;
    case Expired;
    case TooManyAttempts;
}
