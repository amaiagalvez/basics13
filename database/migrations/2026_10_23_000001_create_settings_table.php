<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Runtime configuration each package seeds for itself and only `value` is ever edited from
     * the interface, so there is no trash, no active flag and no audit columns. `class` is
     * informative: it names the class that reads the variable. The visible name and description
     * live in the four interface languages.
     */
    public function up(): void
    {
        Schema::create('BAS_settings', function (Blueprint $table) {
            $table->id();
            $table->string('package');
            $table->string('class');
            $table->string('variable');
            $table->string('type');
            $table->text('value')->nullable();
            $table->string('name_eu');
            $table->string('name_es');
            $table->string('name_fr');
            $table->string('name_en');
            $table->text('description_eu');
            $table->text('description_es');
            $table->text('description_fr');
            $table->text('description_en');
            $table->timestamps();

            $table->unique(['package', 'variable']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('BAS_settings');
    }
};
