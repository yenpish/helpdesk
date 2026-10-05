<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * 1. Event types
         */
        Schema::create('event_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        /*
         * 2. Events
         *
         * This becomes the parent/container for sessions.
         */
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();

            $table->foreignId('event_type_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('location_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('organizer_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('status')->default('draft');

            $table->timestamps();
        });

        /*
         * 3. Sessions
         *
         * The old AttendanceEvent becomes an Event + one Session.
         */
        Schema::create('event_sessions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name');
            $table->text('description')->nullable();

            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();

            $table->dateTime('attendance_opens_at')->nullable();
            $table->dateTime('attendance_closes_at')->nullable();

            $table->string('pin', 4)->unique();

            $table->timestamps();
        });

        /*
         * 4. Registrations
         */
        Schema::create('registrations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('status')->default('pending');

            $table->timestamp('registered_at')->nullable();

            $table->timestamps();

            $table->unique(['event_id', 'user_id']);
        });

        /*
         * 5. Preserve existing Attendance data.
         *
         * Add the new session relationship while keeping the old
         * attendance_event_id temporarily for compatibility.
         */
        Schema::table('attendances', function (Blueprint $table) {
            $table->foreignId('session_id')
                ->nullable()
                ->constrained('event_sessions')
                ->nullOnDelete();
        });

        /*
         * 6. Convert every existing AttendanceEvent into:
         *
         * AttendanceEvent
         *       ↓
         *     Event
         *       +
         *     Session
         *
         * We preserve the original IDs so existing attendance rows
         * can be connected safely.
         */
        $oldEvents = DB::table('attendance_events')->get();

        foreach ($oldEvents as $oldEvent) {
            DB::table('events')->insert([
                'id' => $oldEvent->id,
                'name' => $oldEvent->name,
                'description' => null,
                'event_type_id' => null,
                'location_id' => $oldEvent->location_id,
                'organizer_id' => null,
                'status' => 'published',
                'created_at' => $oldEvent->created_at,
                'updated_at' => $oldEvent->updated_at,
            ]);

            DB::table('event_sessions')->insert([
                'id' => $oldEvent->id,
                'event_id' => $oldEvent->id,
                'name' => $oldEvent->name,
                'description' => null,
                'starts_at' => $oldEvent->starts_at,
                'ends_at' => $oldEvent->ends_at,
                'attendance_opens_at' => $oldEvent->starts_at,
                'attendance_closes_at' => $oldEvent->ends_at,
                'pin' => $oldEvent->pin,
                'created_at' => $oldEvent->created_at,
                'updated_at' => $oldEvent->updated_at,
            ]);
        }

        /*
         * 7. Connect existing attendance records to their new session.
         */
        DB::statement('
            UPDATE attendances
            SET session_id = attendance_event_id
            WHERE attendance_event_id IS NOT NULL
        ');
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropForeign(['session_id']);
            $table->dropColumn('session_id');
        });

        Schema::dropIfExists('registrations');
        Schema::dropIfExists('event_sessions');
        Schema::dropIfExists('events');
        Schema::dropIfExists('event_types');
    }
};
