<?php

namespace App\Http\Controllers;

use Log;
use OAuth;
use App\DM as DMEvents;
use Cloudinary;
use CloudinaryField;
use Illuminate\Http\Request;
use App\Constants\Messages;
use App\Http\Controllers\TwitterController as Twitter;

class TwitterWebhookController extends Controller
{

    protected $consumer_key;
    protected $api_secret;
    protected $token_secret;
    protected $token;
    protected $image_location;

    function __construct($foo = null)
    {
        $base = base_path();

        $this->consumer_key = env('TWITTER_API_KEY');
        $this->api_secret = env("TWITTER_API_SECRET");
        $this->token_secret = env("TWITTER_SECRET");
        $this->token = env("TWITTER_ACCESS_TOKEN");
        $this->temp_location = "$base/tmp/images/";
    }

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
        error_log('Request Received');
        if ($request->isJson()) {
            error_log('Fetching Json Request as array');
            $data = $request->json()->all();
            //fetch dm event id
            $event_id = (int) $data['direct_message_events'][0]['id'];
            error_log("Succfully fetched event_id $event_id");
            echo(Messages::STARTING_TRANSFORMATION);
            error_log("Checking for event in db");
            $event_record = DMEvents::where('dm_event_id', $event_id)->first();
            $sender_id = $data['direct_message_events'][0]['message_create']['sender_id'];          
            $twitter_id = (int) env("TWITTER_ID");
            $log_info = $log_info;
            error_log("pre db check for sender $sender_id");
            if ($sender_id !== $twitter_id && (!$event_record || $event_record->status == 'Failed')) {
                error_log("event doesnt exist in db; sender is $sender_id");
                $cloud = new CloudinaryField;
                $twitter = new Twitter;

                $attachment = $data['direct_message_events'][0]['message_create']['message_data']['attachment'];

                //check if incoming event contains an image
                error_log("checking for image attachment");
                if ($this->imageIsPresent($attachment)) {
                    $image_url = $attachment['media'][0]['media_url'];
                    error_log("image url is $image_url");
                    $mod = [
                        "width" => 400, 
                        "height" => 400, 
                        "crop" => "fill", 
                        "public_id" => $sender_id, 
                        "quality" => 60
                    ];

                    try {
                        $this->saveProtectedImgToTemp($image_url, $sender_id);
                        $cloud->upload("$this->temp_location$sender_id.png", $mod);
                        $uploaded_img_url = Cloudinary::cloudinary_url("$sender_id.jpg");
                        $twitter->uploadImage($sender_id, $uploaded_img_url);
                    } catch (\Exception $e) {
                        $error_info = ["message" => $e->getMessage(), "twitter_user" => $sender_id, "event_id" => $event_id];
                        Log::info(Messages::UNABLE_TO_UPLOAD_IMAGE, $error_info);
                    }

                    $twitter_image_id = $twitter->uploadImage($sender_id, $uploaded_img_url);

                    if ($twitter->sendDM($sender_id, $twitter_image_id)) {
                        DMEvents::updateStatus($event_id, 'Success');
                        Log::info(Messages::DM_SEND_SUCCESS, $log_info);
                    }else{
                        DMEvents::updateStatus($event_id, 'Failed');
                        Log::info(Messages::DM_SEND_FAILURE, $log_info);
                    }

                    
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
        
        //$dm = new DMS();
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

    /**
     * Make authenticated request to fetch Twitter DM image and store temporarily
     * @param  [type] $imgUrl [description]
     * @return [type]         [description]
     */
    public function saveProtectedImgToTemp($imgUrl, $sender_id)
    {                
        $oauth = new OAuth($this->consumer_key, $this->api_secret, OAUTH_SIG_METHOD_HMACSHA1, OAUTH_AUTH_TYPE_AUTHORIZATION);
        $oauth->setToken($this->token, $this->token_secret);

        $oauth->disableSSLChecks();
        $oauth->enableDebug();
        $oauth->fetch($imgUrl);

        file_put_contents("$this->temp_location$sender_id.png", $oauth->getLastResponse());
    }
}
