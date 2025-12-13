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
        DB::statement("
            UPDATE social_accounts
            SET status = 'unverified'
            WHERE status NOT IN ('unverified','pending','verified','rejected')
               OR status IS NULL");

        Schema::table('social_accounts', function (Blueprint $table) {
            $table->enum('status',['unverified','pending','verified','rejected'])
                ->default('unverified')
                ->change();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('social_accounts', function (Blueprint $table) {
            //
        });
    }
};
