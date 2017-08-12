<?php

namespace App;

use Jenssegers\Mongodb\Model as Eloquent;

class DM extends Eloquent 
{
    protected $collection = 'dms';
    protected $primaryKey = '_id';
}