<?php

namespace App\Http\Controllers;

use App\ApiResponse;
use App\PublicException;

abstract class Controller
{
    use ApiResponse;
    
    use PublicException;
}
