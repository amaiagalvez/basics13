<?php

use Basics13\Support\Database\Helpers;
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

            Helpers::addCommonColumns($table);
            Helpers::addAuditColumns($table);
        });

        Helpers::addUniqueActiveNameIndex('audited_records');

        Schema::create('plain_audited_records', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();

            Helpers::addAuditColumns($table);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plain_audited_records');
        Schema::dropIfExists('audited_records');
        Schema::dropIfExists('users');
    }
};
