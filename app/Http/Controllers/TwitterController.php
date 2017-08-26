<?php

namespace App\Http\Controllers;

use Abraham\TwitterOAuth\TwitterOAuth as Twitter;
use App\Constants\ResponseMessages;

class TwitterController extends Controller
{

    public $connection;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $consumer_key = env("TWITTER_API_KEY");
        $consumer_secret = env("TWITTER_API_SECRET");
        $token = env("TWITTER_ACCESS_TOKEN");
        $token_secret = env("TWITTER_SECRET");

        $this->connection = new Twitter($consumer_key, $consumer_secret, $token, $token_secret);
        $this->connection->setTimeouts(60, 30);

    }

    public function uploadImage($recipient, $image_url)
    {
        $params = ['media' => $image_url, "media_category" => "dm_image", "media_type" => "image/png"];
        $media = $this->connection->upload('media/upload', $params);

        if ($this->connection->getLastHttpCode() == 200) {
            return $media->media_id;
       }
    }

    public function sendDM($recipient, $image_id)
    {
        $param = $this->buildDMParam($recipient, $image_id);
        $dm = $this->connection->post("direct_messages/events/new", $param, true);

        if ($this->connection->getLastHttpCode() == 200) {
            return true;
       }

       return false;
    }

    public function buildDMParam($recipient, $media_id)
    {
        $params = [
            "event" => [
                "type" => "message_create",
                "message_create" => [
                    "target" => [
                        "recipient_id" => $recipient
                    ],
                    "message_data" => [
                        "text" => ResponseMessages::RESIZE_COMPLETE,
                        "attachment" => [
                            "type" => "media",
                            "media" => [
                                "id" => $media_id
                            ]
                        ]
                    ]
                ]
            ]
        ];

        return $params;
    }
}
