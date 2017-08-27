<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeDmEventsIdColumnToBigInt extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('dm', function (Blueprint $table) {
            $table->bigInteger('dm_event_id')->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dm', function (Blueprint $table) {
            $table->integer('dm_event_id')->change();
        });
    }
}
