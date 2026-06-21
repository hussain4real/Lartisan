<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminHostRedirectController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            return redirect('/admin');
        }

        abort(404);
    }
}
