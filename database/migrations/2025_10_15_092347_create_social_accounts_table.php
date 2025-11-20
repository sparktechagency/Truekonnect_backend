<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('sm_id')->constrained('social_media')->onDelete('cascade');
            $table->string('profile_name')->nullable();
            $table->string('profile_image')->nullable();
            $table->string('note')->nullable();
            $table->foreignId('verification_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('verified_at')->nullable();
            $table->enum('status',['pending','verified','rejected'])->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     * social_accounts
     */
    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
