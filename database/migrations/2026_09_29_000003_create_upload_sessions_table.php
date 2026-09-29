<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('upload_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('idempotency_key', 64);
            $table->string('filename');
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('next_index')->default(0);
            $table->foreignId('media_id')->nullable()->constrained('media');
            $table->boolean('cancelled')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('upload_sessions');
    }
};
