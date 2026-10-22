<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->string('description')->nullable();
            $table->boolean('is_system')->default(false);

            addCommonColumns($table);
            addAuditColumns($table);
        });

        // Archived roles keep their name and slug: only the trash frees them for reuse.
        addUniqueActiveNameIndex('roles');
        addUniqueActiveColumnIndex('roles', 'slug');
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
