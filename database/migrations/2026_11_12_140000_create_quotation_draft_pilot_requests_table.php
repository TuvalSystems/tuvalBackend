<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_draft_pilot_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->uuid('request_id');
            $table->char('payload_hash', 64);
            $table->json('response')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'request_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_draft_pilot_requests');
    }
};
