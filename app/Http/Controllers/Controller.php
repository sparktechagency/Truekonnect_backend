<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponse;
use App\Traits\SendNotification;
use App\Traits\UploadFile;

abstract class Controller
{
    use ApiResponse, SendNotification, UploadFile;
}
