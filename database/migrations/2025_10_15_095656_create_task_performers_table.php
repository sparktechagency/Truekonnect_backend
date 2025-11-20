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
       Schema::create('task_performers', function (Blueprint $table) {
        $table->id();
        $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
        $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
        $table->decimal('token_earned', 10, 2)->default(0);
        $table->enum('status', ['pending', 'completed', 'rejected', 'admin_review'])->default('pending');
        $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
        $table->text('rejection_reason')->nullable();
        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_performers');
    }
};
