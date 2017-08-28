<?php

namespace App\Http\Controllers;

use Log;
use OAuth;
use App\DM as DMEvents;
use Cloudinary;
use CloudinaryField;
use Cloudinary\Api as CloudinaryAdmin;
use Illuminate\Http\Request;
use App\Constants\Messages;
use App\Constants\ResponseMessages;
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
        $base = base_path() . 'public';

        $this->consumer_key = env('TWITTER_API_KEY');
        $this->api_secret = env("TWITTER_API_SECRET");
        $this->token_secret = env("TWITTER_SECRET");
        $this->token = env("TWITTER_ACCESS_TOKEN");
        $this->temp_location = "$base/tmp/";
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
        if (is_dir($this->temp_location)) {
            error_log('issa directory');
        }
        $root = base_path();
        error_log("document root is $root");
        if ($request->isJson()) {
            $data = $request->json()->all();            
            //fetch dm event id
            $event_id = (int) $data['direct_message_events'][0]['id'];
            $event_record = DMEvents::where('dm_event_id', $event_id)->first();
            $sender_id = (int) $data['direct_message_events'][0]['message_create']['sender_id'];            
            $twitter_id = (int) env("TWITTER_ID");
            $log_info = ["twitter_user" => $sender_id, "event_id" => $event_id];
            
            if ($sender_id !== $twitter_id && (!$event_record || $event_record->status == 'Failed')) {
                
                $twitter = new Twitter;

                $attachment = $data['direct_message_events'][0]['message_create']['message_data']['attachment'];

                //check if incoming event contains an image
                if ($this->imageIsPresent($attachment)) {
                    $image_url = $attachment['media']['media_url'];
          
                    try {
                        $this->saveProtectedImgToTemp($image_url, $sender_id);
                        $uploaded_img_url = $this->uploadToCloudinary($sender_id);
                        error_log("got url $uploaded_img_url");
                        $twitter_image_id = $twitter->uploadImage($sender_id, $uploaded_img_url);
                        error_log("image: $uploaded_img_url uploaded to twitter $twitter_image_id");
                    } catch (\Exception $e) {
                        $error_info = array_merge($log_info, ["message" => $e->getMessage()]);
                        error_log(Messages::UNABLE_TO_UPLOAD_IMAGE . "|| User: $sender_id || error:" . $e->getMessage());
                        Log::info(Messages::UNABLE_TO_UPLOAD_IMAGE, $error_info);
                        $twitter->sendDM($sender_id, null, ResponseMessages::UNABLE_TO_COMPLETE);
                        die;
                    }
                    error_log("sending image to user $sender_id and image: $twitter_image_id");
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
        $media_type = $attachment['media']['type'];
        return ($attachment_type == 'media' && $media_type == 'photo') ? true : false;
    }

    /**
     * Make authenticated request to fetch Twitter DM image and store temporarily
     * @param  [type] $imgUrl [description]
     * @return [type]         [description]
     */
    public function saveProtectedImgToTemp($imgUrl, $sender_id)
    {    
        $filename = "$this->temp_location$sender_id.png";
        error_log("image location is $filename");
        $oauth = new OAuth($this->consumer_key, $this->api_secret, OAUTH_SIG_METHOD_HMACSHA1, OAUTH_AUTH_TYPE_AUTHORIZATION);
        $oauth->setToken($this->token, $this->token_secret);

        $oauth->disableSSLChecks();
        $oauth->enableDebug();
        $oauth->fetch($imgUrl);

        file_put_contents($filename, $oauth->getLastResponse());
        error_log("image is stored at $filename");
    }

    public function uploadToCloudinary($public_id)
    {
        $cloud = new CloudinaryField;
        $admin = new CloudinaryAdmin;

        $mod = [
                "width" => 400, 
                "height" => 400, 
                "crop" => "fill", 
                "public_id" => $public_id, 
                "quality" => 80
            ];
        
        try {
            $result = $admin->resource($public_id);

            if ($result->rate_limit_allowed) {
                $remove_resouce = $admin->delete_resources($public_id);
                error_log(json_encode($remove_resouce));
            }
            $cloud->upload("$this->temp_location$public_id.png", $mod);
        } catch (\Exception $e) {
            if ($e instanceof NotFound) {
                $cloud->upload("$this->temp_location$public_id.png", $mod);
            }
        }

        return Cloudinary::cloudinary_url("$public_id.jpg");
    }
}
