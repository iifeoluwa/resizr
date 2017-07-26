<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AuthController extends Controller
{
    /**
     * Handles requests to the Twitter Webhook
     * 
     * @param Request $request 
     * @return Object
     */
    public function twitter(Request $request)
    {
        print_r($request->all());

        return 'Twitter, Yaay!';
    }
}
