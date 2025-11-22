<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
//        Schema::table('task_performers', function (Blueprint $table) {
//            $table->enum('status', ['pending', 'completed', 'rejected', 'admin_review','blocked'])->default('pending')->change();
//        });

        DB::statement("ALTER TABLE task_performers MODIFY COLUMN status ENUM('pending', 'completed', 'rejected', 'admin_review','blocked') DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_performers', function (Blueprint $table) {
            DB::statement("ALTER TABLE task_performers MODIFY COLUMN status ENUM('pending', 'completed', 'rejected', 'admin_review','blocked') DEFAULT 'pending'");
        });
    }
};
