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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sm_id')->constrained('social_media')->onDelete('cascade');
            $table->foreignId('sms_id')->constrained('social_media_services')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('country_id')->constrained('countries')->onDelete('cascade');
            $table->integer('quantity');
            $table->integer('performed')->default(0);

            $table->integer('per_perform')->default(0);
            $table->integer('total_token')->default(0);
            $table->integer('token_distributed')->nullable()->default(0);
            
            $table->decimal('unite_price',10,3);
            $table->decimal('total_price',10,3);

            $table->text('link');
            $table->text('description');
            $table->enum('status',['pending','verifyed','rejected','completed','admin_review']);
            $table->foreignId('verified_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('rejection_reason')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
