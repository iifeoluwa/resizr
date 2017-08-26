<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DM extends Model
{

    protected $table;

    public function __construct()
    {
    	$this->table = env("DB_DATABASE");
    }

    public static function updateStatus($event_id, $status)
    {
    	$dm = new DM;
    	$dm->dm_event_id = $event_id;
        $dm->status = $status;
        $dm->save();
    }
}