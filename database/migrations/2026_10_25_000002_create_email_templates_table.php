<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * English is the fallback every other language falls back to, so its columns are the only ones
     * that are not nullable.
     *
     * @var list<string>
     */
    private const LOCALES = ['eu', 'es', 'fr', 'en'];

    public function up(): void
    {
        Schema::create('BAS_email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('banner')->nullable();
            $table->longText('legal_text')->nullable();

            foreach (self::LOCALES as $locale) {
                $locale === 'en'
                    ? $table->string('subject_en')
                    : $table->string('subject_'.$locale)->nullable();
                $locale === 'en'
                    ? $table->longText('body_en')
                    : $table->longText('body_'.$locale)->nullable();
            }

            $table->boolean('default')->default(false);

            addCommonColumns($table);
            addAuditColumns($table);
        });

        addUniqueActiveNameIndex('BAS_email_templates');
    }

    public function down(): void
    {
        Schema::dropIfExists('BAS_email_templates');
    }
};
