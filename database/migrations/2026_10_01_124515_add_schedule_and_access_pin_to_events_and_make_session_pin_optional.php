<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dateTime('starts_at')->nullable()->after('description');
            $table->dateTime('ends_at')->nullable()->after('starts_at');
            $table->string('pin', 4)->nullable()->unique()->after('status');
        });

        /*
         * Existing legacy Events came from AttendanceEvents,
         * so copy their first Session's schedule/PIN into the
         * new Event-level fields.
         */
        $events = DB::table('events')->get();

        foreach ($events as $event) {
            $session = DB::table('event_sessions')
                ->where('event_id', $event->id)
                ->orderBy('id')
                ->first();

            if ($session) {
                DB::table('events')
                    ->where('id', $event->id)
                    ->update([
                        'starts_at' => $session->starts_at,
                        'ends_at' => $session->ends_at,
                        'pin' => $session->pin,
                    ]);
            }
        }

        /*
         * Session PIN is no longer the main attendance access mechanism.
         * Keep the old column temporarily for legacy data, but new
         * sessions are allowed to have no PIN.
         */
        Schema::table('event_sessions', function (Blueprint $table) {
            $table->string('pin', 4)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('event_sessions', function (Blueprint $table) {
            $table->string('pin', 4)->nullable(false)->change();
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropUnique(['pin']);
            $table->dropColumn([
                'starts_at',
                'ends_at',
                'pin',
            ]);
        });
    }
};
