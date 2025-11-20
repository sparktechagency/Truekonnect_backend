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
     Schema::create('support_tickets', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
        $table->string('subject')->nullable();
        $table->longText('issue');
        $table->string('attachments')->nullable();
        $table->enum('status', ['pending', 'admin_review', 'answered'])->default('pending');
        $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
        $table->longText('answer')->nullable();
        $table->longText('admin_reason')->nullable();
        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};
