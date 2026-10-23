<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Who was impersonating when the login row was written; null for a normal sign-in. It
     * survives the permanent deletion of that account the same way `user_id` does.
     */
    public function up(): void
    {
        Schema::table('BAS_sessions', function (Blueprint $table) {
            $table->foreignId('impersonator_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('BAS_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('impersonator_id');
        });
    }
};
