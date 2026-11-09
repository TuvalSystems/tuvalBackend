<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_item_draft_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('gp_usuarios')->restrictOnDelete(); // Authenticated actor / key namespace.
            $table->foreignId('owner_id')->constrained('gp_usuarios')->restrictOnDelete();
            $table->uuid('request_id');
            $table->char('payload_hash', 64);
            $table->foreignId('cotizacion_id')->constrained('gp_cotizacions')->restrictOnDelete();
            $table->json('response');
            $table->timestamps();
            $table->unique(['user_id', 'request_id'], 'quotation_item_draft_user_request_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_item_draft_requests');
    }
};
