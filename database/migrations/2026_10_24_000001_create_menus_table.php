<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Menu items are seeded by the package that owns their routes, like permissions: no trash, no
     * active flag and no audit columns. A group has no route; a link hangs from a group through
     * `parent_key`, without a foreign key, so packages may seed in any order.
     */
    public function up(): void
    {
        Schema::create('BAS_menus', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('parent_key')->nullable()->index();
            $table->string('package');
            $table->string('label');
            $table->string('icon')->nullable();
            $table->string('route')->nullable();
            $table->string('active_pattern')->nullable();
            $table->string('active_except')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('BAS_menus');
    }
};
