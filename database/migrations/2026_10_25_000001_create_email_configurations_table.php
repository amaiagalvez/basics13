<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('BAS_email_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sender_name');
            $table->string('sender_email');
            $table->string('host');
            $table->unsignedInteger('port');
            $table->string('username')->nullable();
            // Encrypted at rest, so the ciphertext outgrows a varchar.
            $table->text('password')->nullable();
            $table->string('encryption')->default('tls');
            $table->boolean('default')->default(false);

            addCommonColumns($table);
            addAuditColumns($table);
        });

        addUniqueActiveNameIndex('BAS_email_configurations');
    }

    public function down(): void
    {
        Schema::dropIfExists('BAS_email_configurations');
    }
};
