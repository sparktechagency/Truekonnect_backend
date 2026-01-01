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
            ALTER TABLE social_accounts
            DROP CONSTRAINT social_accounts_status_check
        ");

                DB::statement("
            ALTER TABLE social_accounts
            ADD CONSTRAINT social_accounts_status_check
            CHECK (status IN ('pending', 'verified', 'rejected', 'unverified'))
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
