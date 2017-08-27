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

        if ($request->isJson()) {
            $data = $request->json()->all();
            //fetch dm event id
            $event_id = (int) $data['direct_message_events'][0]['id'];
            $event_record = DMEvents::where('dm_event_id', $event_id)->first();
            $sender_id = $data['direct_message_events'][0]['message_create']['sender_id'];          
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
                        $uploaded_img_url = $this->uploadToCloudinary($sender_id, $image_url);
                        $twitter->uploadImage($sender_id, $uploaded_img_url);
                    } catch (\Exception $e) {
                        $error_info = array_merge($log_info, ["message" => $e->getMessage()]);
                        error_log(Messages::UNABLE_TO_UPLOAD_IMAGE . "|| User: $sender_id || error:" . $e->getMessage());
                        Log::info(Messages::UNABLE_TO_UPLOAD_IMAGE, $error_info);
                        $twitter->sendDM($sender_id, null, ResponseMessages::UNABLE_TO_COMPLETE);
                        die;
                    }

                    $twitter_image_id = $twitter->uploadImage($sender_id, $uploaded_img_url);

                    if ($twitter->sendDM($sender_id, $twitter_image_id)) {
                        DMEvents::updateStatus($event_id, 'Success');
                        Log::info(Messages::DM_SEND_SUCCESS, $log_info);
                    }else{
                        DMEvents::updateStatus($event_id, 'Failed');
                        Log::info(Messages::DM_SEND_FAILURE, $log_info);
                    }

                    $this->cleanUp($sender_id);
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
        $oauth = new OAuth($this->consumer_key, $this->api_secret, OAUTH_SIG_METHOD_HMACSHA1, OAUTH_AUTH_TYPE_AUTHORIZATION);
        $oauth->setToken($this->token, $this->token_secret);

        $oauth->disableSSLChecks();
        $oauth->enableDebug();
        $oauth->fetch($imgUrl);

        if (file_exists($filename)) {
            unlink($filename);
        }

        file_put_contents($filename, $oauth->getLastResponse());
    }

    public function uploadToCloudinary($public_id, $image_url)
    {
        $cloud = new CloudinaryField;
        $admin = new CloudinaryAdmin;

        $mod = [
                "width" => 400, 
                "height" => 400, 
                "crop" => "fill", 
                "public_id" => $public_id, 
                "quality" => 60
            ];

        $result = $admin->resource($public_id);
        error_log(json_encode($result));
        //remove existing resource with that public id
        if ($result->rate_limit_allowed) {
            $remove_resouce = $admin->delete_resources($public_id);
            error_log(json_encode($remove_resouce));
        }
        
       $cloud->upload("$this->temp_location$public_id.png", $mod);
       
        return Cloudinary::cloudinary_url("$public_id.jpg");
    }
}
