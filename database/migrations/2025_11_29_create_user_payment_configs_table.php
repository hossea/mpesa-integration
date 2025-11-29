<?php
// database/migrations/2025_11_29_create_user_payment_configs_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_payment_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('config_name'); // e.g., "My Shop Till", "Main Paybill"
            $table->enum('type', ['till', 'paybill']); // Payment type
            $table->string('shortcode'); // Till number or Paybill number
            $table->string('account_number')->nullable(); // For Paybill only
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false); // One default per user
            $table->json('meta')->nullable(); // For additional settings
            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'type']);
            $table->index(['shortcode', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_payment_configs');
    }
};
