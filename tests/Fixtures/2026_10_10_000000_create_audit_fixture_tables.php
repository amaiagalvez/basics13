<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audited_records', function (Blueprint $table): void {
            $table->id();
            $table->string('name');

            // A plain integer column for the select filter tests. No related table exists here on
            // purpose: the package has no domain model.
            $table->unsignedBigInteger('project_id')->nullable();

            // Columns for filter testing
            $table->integer('priority')->nullable();
            $table->date('published_at')->nullable();

            addCommonColumns($table);
            addAuditColumns($table);
        });

        addUniqueActiveNameIndex('audited_records');

        Schema::create('plain_audited_records', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();

            addAuditColumns($table);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plain_audited_records');
        Schema::dropIfExists('audited_records');
    }
};
