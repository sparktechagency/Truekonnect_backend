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
        // Fix invalid statuses first
        DB::statement("
            UPDATE social_accounts
            SET status = 'unverified'
            WHERE status NOT IN ('unverified','pending','verified','rejected')
               OR status IS NULL
        ");

        Schema::table('social_accounts', function (Blueprint $table) {
            $table->string('status')->default('unverified')->change();
        });
    }

    public function down(): void
    {
        Schema::table('social_accounts', function (Blueprint $table) {
            $table->string('status')->nullable()->change();
        });
    }
};
