<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DM extends Model
{

    protected $table = 'dm';

    public static function updateStatus($event_id, $status)
    {
    	$dm = new DM;
    	$dm->dm_event_id = $event_id;
        $dm->status = $status;
        $dm->save();
    }
}