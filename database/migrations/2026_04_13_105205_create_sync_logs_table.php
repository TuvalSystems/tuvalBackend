<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSyncLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('module')->nullable()->index();
            $table->string('action')->nullable();
            $table->string('method')->nullable();
            $table->longText('message');
            $table->string('external_id')->nullable()->index();
            $table->unsignedBigInteger('local_id')->nullable()->index();
            $table->json('payload')->nullable();
            $table->integer('attempts')->default(0);
            $table->string('status')->default('failed')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sync_logs');
    }
}
