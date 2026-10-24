<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('BAS_menu_permission', function (Blueprint $table) {
            $table->foreignId('menu_id')->constrained('BAS_menus')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('BAS_permissions')->cascadeOnDelete();

            $table->primary(['menu_id', 'permission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('BAS_menu_permission');
    }
};
