<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('BAS_permission_role', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained('BAS_permissions')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('BAS_roles')->cascadeOnDelete();

            $table->primary(['permission_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('BAS_permission_role');
    }
};
