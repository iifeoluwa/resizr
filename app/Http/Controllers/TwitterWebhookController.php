<?php

namespace App\Http\Controllers;

use Cloudinary;
use CloudinaryField;
use App\Http\Controllers\TwitterDMController as DMS;
use Illuminate\Http\Request;
use App\DM;

class TwitterWebhookController extends Controller
{

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
            $twitterSecret = env("TWITTER_API_SECRET");
            $hashDigest = base64_encode(hash_hmac('sha256', $crc, $twitterSecret, true));

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
        if ($request->isJson()) {

            $data = $request->json()->all();
            //fetch dm event id
            $event_id = (int) $data['direct_message_events'][0]['id'];
            $event_record = DM::where('dm_event_id', $event_id)->first();
            $sender_id = $data['direct_message_events'][0]['message_create']['sender_id'];          
            $twitter_id = (int) env("TWITTER_ID");

            if ($sender_id !== $twitter_id && (!$event_record || $event_record->status == 'Failed')) {
                
                $attachment = $data['direct_message_events'][0]['message_create']['message_data']['attachment'];

                //check if incoming event contains an image
                if ($this->imageIsPresent($attachment)) {
                    $image_url = $attachment['media'][0]['media_url'];
                    $cloud = new CloudinaryField;
                    $mod = ["width" => 400, "height" => 400, "crop" => "fill", "public_id" => $sender_id];
                    $cloud->upload('https://pbs.twimg.com/media/DHg-CKWUIAEMW3I.jpg', $mod);
                    var_dump(Cloudinary::cloudinary_url("$sender_id.jpg"));
                }
                
            }
            
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
        
        $dm = new DMS();
        //fetch header value
        $signature = $request->header('x-twitter-webhooks-signature');
        //split signature to get hash algorithm used
        $signatureSplit = explode('=', $signature);
        $hashAlgo = $signatureSplit[0];
        
        if ($hashAlgo == 'sha256') {
            $payload = $request->getContent();
            $twitterSecret = env("TWITTER_API_SECRET");
            
            $payloadHashDigest = hash_hmac('sha256', $payload, $twitterSecret);
            $encodedSignature = base64_encode($signature);
                        
            if (hash_equals($payloadHashDigest, $encodedSignature)) {
                return true;
            }else{
                return false;
            }
        }else{
            return false;
        }
    }

    /**
     * [imageIsPresent description]
     * @param  [type] $mediaObject [description]
     * @return [type]              [description]
     */
    public function imageIsPresent($attachment)
    {
        $attachment_type = $attachment['type'];
        $media_type = $attachment['media'][0]['type'];

        return ($attachment_type == 'media' && $media_type == 'photo') ? true : false;
    }
}
