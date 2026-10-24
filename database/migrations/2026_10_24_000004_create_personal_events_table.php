<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * The events a user writes on their own calendar. A plain CRUD table on purpose: an event is not
     * a domain record, so it has no trash, no `active` flag and no audit columns, and it goes away
     * with its owner. Dates are plain dates read in the application timezone; a missing end date
     * means a one-day event.
     */
    public function up(): void
    {
        Schema::create('BAS_personal_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('notes')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('BAS_personal_events');
    }
};
