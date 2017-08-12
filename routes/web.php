<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$app->get('/', function () use ($app) {
	$res = $app->version();
    return "Resizer Bot running on $res";
});

$app->get('/ya', function () use ($app) {
	$dbh = new PDO("pgsql:dbname=dms;host=127.0.0.1", "postgres", "admin");

if($dbh) {
   echo 'connected';
} else {
    echo 'there has been an error connecting';
} 
});

$app->get('webhook/twitter', 'TwitterWebhookController@verifyCrcToken');
$app->post('webhook/twitter', 'TwitterWebhookController@handleDMEvents');
$app->get('dm[/{message}]', 'TwitterDMController@send');
