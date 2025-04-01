<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSectionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('parent_id')->nullable()->unsigned();
            $table->string('to', 40)->nullable();
            $table->string('url', 60)->nullable();
            $table->string('name', 100);
            $table->string('icon', 30)->nullable();
            $table->integer('order')->nullable();
            $table->timestamps();
        });
        Schema::table('sections', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('sections')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sections');
    }
}
