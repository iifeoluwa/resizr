<?php

namespace App\Http\Controllers;

use Abraham\TwitterOAuth\TwitterOAuth as Twitter;

class TwitterDMController extends Controller
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
    }

    public function send($recipient, $message = "it works!")
    {
        $dm = $this->connection->post("direct_messages/new", ['screen_name' => $recipient, "text" => $message]);

        if ($this->connection->getLastHttpCode() == 200) {
            echo "It worked";
            var_dump($dm);
        }else{
            echo "didn't work";
        }
    }
}
