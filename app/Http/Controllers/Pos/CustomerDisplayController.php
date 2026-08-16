<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * The second screen, the one facing the customer.
 *
 * It renders an empty shell: everything on it arrives from the cashier's
 * window over a BroadcastChannel. There is no order id in the URL and no
 * polling, because the cashier screen already holds the order exactly as the
 * server returned it.
 *
 * It is session-authenticated like every other POS screen, so it can only be
 * opened on a terminal somebody has already signed in on.
 */
class CustomerDisplayController extends Controller
{
    public function __invoke(): View
    {
        return view('pos.display');
    }
}
