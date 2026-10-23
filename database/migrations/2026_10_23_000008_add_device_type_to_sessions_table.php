<?php

use Basics13\Enums\DeviceType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * The device each row comes from, classified from its user agent. Existing rows are filled
     * through the query builder: the model only lets `logged_out_at` change.
     */
    public function up(): void
    {
        Schema::table('BAS_sessions', function (Blueprint $table) {
            $table->string('device_type')->default(DeviceType::Desktop->value)->after('user_agent');
        });

        DB::table('BAS_sessions')->select(['id', 'user_agent'])->chunkById(500, static function ($rows): void {
            foreach ($rows as $row) {
                $device = DeviceType::fromUserAgent((string) $row->user_agent);

                if ($device !== DeviceType::Desktop) {
                    DB::table('BAS_sessions')->where('id', $row->id)->update(['device_type' => $device->value]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('BAS_sessions', function (Blueprint $table) {
            $table->dropColumn('device_type');
        });
    }
};
