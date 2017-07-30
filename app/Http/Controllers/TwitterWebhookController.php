<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TwitterWebhookController extends Controller
{
    
    const TWITTER_SECRET = env("TWITTER_API_SECRET");

    /**
     * Handles requests to the Twitter Webhook
     * 
     * @param Request $request 
     * @return Object
     */
    public function verifyCrcToken(Request $request)
    {
        if ($request->isMethod('get') && $request->has('crc_token')) {
            
            $crc = $request->input('crc_token');
            $hashDigest = base64_encode(hash_hmac('sha256', $crc, TWITTER_SECRET, true));
            $response = ['response_token' => 'sha256=' . $hashDigest];

            return json_encode($response);
        }

        $message = ['status' => 'error', 'message' => 'Missing required params or method not allowed'];
        return json_encode($message);
    }

    /**
     * Handles DM events
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function handleDMEvents(Request $request)
    {
        if ($this->validateHeader($request)) {
            # code...
        }
    }

    /**
     * Validate request header. This is to ensure that the request originated from a trusted source.
     * According to the Twitter API Validation rules.
     * @param  [type] $request
     * @return [bool] 
     */
    public function validateHeader($request)
    {
        $signature = $request->header('x-twitter-webhooks-signature');
        $hashAlgo = explode('=', $signature)[0];

        if ($request->secure() && $hashAlgo === 'sha256') {
            
            $payload = $request->getContent();
            $payloadHashDigest = hash_hmac('sha256', $payload, TWITTER_SECRET);

            if (hash_equals($payloadHashDigest, base64_encode($signature))) {
                return true;
            }
        }else{
            return false;
        }
    }
}
