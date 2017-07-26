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
        if ($request->isMethod('get') && $request->has('crc_token')) {
            
            $crc = $request->input('crc_token');
            $secret = env("TWITTER_API_SECRET");
            $hashDigest = base64_encode(hash_hmac('sha256', $crc, $secret, true));
            $response = ['response_token' => $hashDigest];

            return json_encode($response);
        }

        $message = ['status' => 'error', 'message' => 'Missing required params'];
        return json_encode($message);
    }
}
