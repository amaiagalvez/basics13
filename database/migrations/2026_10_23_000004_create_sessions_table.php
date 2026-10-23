<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * The immutable access register: one row per successful login, failed attempt or lockout.
     * `user_id` survives the permanent deletion of the account (it goes null and `login` keeps the
     * identity). Enum values stay plain strings, as in every BAS table. There is no `updated_at`:
     * rows never change after insert, except `logged_out_at`, set once from null.
     */
    public function up(): void
    {
        Schema::create('BAS_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event');
            $table->string('failure_reason')->nullable();
            $table->string('login');
            $table->string('ip', 45);
            $table->string('user_agent', 512);
            $table->unsignedSmallInteger('lockout_minutes')->nullable();
            $table->uuid('session_key')->nullable()->unique();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('logged_out_at')->nullable();

            $table->index(['created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['event', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('BAS_sessions');
    }
};
