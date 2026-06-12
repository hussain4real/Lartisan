<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class WaitlistHostRedirectController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        return redirect('/waitlist');
    }
}
