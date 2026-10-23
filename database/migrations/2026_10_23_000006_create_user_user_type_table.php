<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('BAS_user_user_type', function (Blueprint $table) {
            // Deleting an account for good releases its types.
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // A type cannot be deleted while an account still points at it, not even permanently,
            // which is what this foreign key enforces without any code of ours having to run.
            $table->foreignId('user_type_id')->constrained('BAS_user_types')->restrictOnDelete();
            // The account administers the users of this type, given `users.view_own` or
            // `users.manage_own`.
            $table->boolean('is_admin')->default(false);
            $table->timestamps();

            $table->primary(['user_id', 'user_type_id']);
            $table->index(['user_type_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('BAS_user_user_type');
    }
};
