<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    /** Lets controllers call $this->authorize() against the order policy. */
    use AuthorizesRequests;
}
