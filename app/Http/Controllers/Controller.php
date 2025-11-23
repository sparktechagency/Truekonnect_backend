<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponse;
use App\Traits\SendNotification;

abstract class Controller
{
    use ApiResponse, SendNotification;
}
