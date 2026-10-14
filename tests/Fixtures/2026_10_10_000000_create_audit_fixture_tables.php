<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('audited_records', function (Blueprint $table): void {
            $table->id();
            $table->string('name');

            // The scope ListQueryBase::findTrashedByName() filters on. No project table exists here
            // on purpose: the package has no domain model, only the column the query looks for.
            $table->unsignedBigInteger('project_id')->nullable();

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
        Schema::dropIfExists('users');
    }
};
