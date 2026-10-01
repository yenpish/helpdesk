<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | EVENTS
        |--------------------------------------------------------------------------
        |
        | Add current-record metadata separately from the audit log.
        |
        | organizer_id = person responsible for the event
        | created_by   = person who created the database record
        | updated_by   = person who last modified the record
        |
        */

        if (Schema::hasTable('events')) {
            Schema::table('events', function (Blueprint $table) {
                if (!Schema::hasColumn('events', 'organizer_id')) {
                    $table->foreignId('organizer_id')
                        ->nullable()
                        ->after('id')
                        ->constrained('users')
                        ->nullOnDelete();
                }

                if (!Schema::hasColumn('events', 'created_by')) {
                    $table->foreignId('created_by')
                        ->nullable()
                        ->after('organizer_id')
                        ->constrained('users')
                        ->nullOnDelete();
                }

                if (!Schema::hasColumn('events', 'updated_by')) {
                    $table->foreignId('updated_by')
                        ->nullable()
                        ->after('created_by')
                        ->constrained('users')
                        ->nullOnDelete();
                }
            });
        }


        /*
        |--------------------------------------------------------------------------
        | EVENT SESSIONS
        |--------------------------------------------------------------------------
        |
        | Each event can contain multiple sessions.
        | Attendance belongs to the session, not directly to the event.
        |
        */

        if (Schema::hasTable('event_sessions')) {
            Schema::table('event_sessions', function (Blueprint $table) {
                if (!Schema::hasColumn('event_sessions', 'created_by')) {
                    $table->foreignId('created_by')
                        ->nullable()
                        ->constrained('users')
                        ->nullOnDelete();
                }

                if (!Schema::hasColumn('event_sessions', 'updated_by')) {
                    $table->foreignId('updated_by')
                        ->nullable()
                        ->constrained('users')
                        ->nullOnDelete();
                }

                if (!Schema::hasColumn('event_sessions', 'attendance_opens_at')) {
                    $table->dateTime('attendance_opens_at')->nullable();
                }

                if (!Schema::hasColumn('event_sessions', 'attendance_closes_at')) {
                    $table->dateTime('attendance_closes_at')->nullable();
                }

                if (!Schema::hasColumn('event_sessions', 'pin')) {
                    $table->string('pin', 4)->nullable()->unique();
                }
            });
        }


        /*
        |--------------------------------------------------------------------------
        | REGISTRATIONS
        |--------------------------------------------------------------------------
        |
        | Registration must support guests.
        | A guest does not necessarily have a users record.
        |
        */

        if (Schema::hasTable('registrations')) {
            Schema::table('registrations', function (Blueprint $table) {
                if (!Schema::hasColumn('registrations', 'user_id')) {
                    $table->foreignId('user_id')
                        ->nullable()
                        ->constrained('users')
                        ->nullOnDelete();
                }

                if (!Schema::hasColumn('registrations', 'guest_name')) {
                    $table->string('guest_name')->nullable();
                }

                if (!Schema::hasColumn('registrations', 'guest_email')) {
                    $table->string('guest_email')->nullable();
                }

                if (!Schema::hasColumn('registrations', 'guest_phone')) {
                    $table->string('guest_phone')->nullable();
                }

                if (!Schema::hasColumn('registrations', 'organisation')) {
                    $table->string('organisation')->nullable();
                }

                if (!Schema::hasColumn('registrations', 'position')) {
                    $table->string('position')->nullable();
                }

                if (!Schema::hasColumn('registrations', 'status')) {
                    $table->string('status')->default('pending');
                }

                if (!Schema::hasColumn('registrations', 'registered_at')) {
                    $table->timestamp('registered_at')->nullable();
                }
            });
        }


        /*
        |--------------------------------------------------------------------------
        | ATTENDANCE
        |--------------------------------------------------------------------------
        |
        | New attendance architecture:
        |
        | Event
        |   └── EventSession
        |          └── Attendance
        |
        | Keep the old attendance_event_id temporarily so the legacy system
        | can coexist while the new architecture is being completed.
        |
        */

        if (Schema::hasTable('attendances')) {
            Schema::table('attendances', function (Blueprint $table) {
                if (!Schema::hasColumn('attendances', 'session_id')) {
                    $table->foreignId('session_id')
                        ->nullable()
                        ->constrained('event_sessions')
                        ->nullOnDelete();
                }
            });
        }


        /*
        |--------------------------------------------------------------------------
        | AUDIT LOGS
        |--------------------------------------------------------------------------
        |
        | This is intentionally separate from created_by / updated_by.
        |
        | created_by / updated_by tell us the CURRENT metadata.
        |
        | audit_logs tells us the HISTORICAL sequence of changes.
        |
        */

        if (!Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table) {
                $table->id();

                $table->foreignId('user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->string('action');

                $table->string('auditable_type')->nullable();
                $table->unsignedBigInteger('auditable_id')->nullable();

                $table->text('description')->nullable();

                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();

                $table->timestamps();

                $table->index([
                    'auditable_type',
                    'auditable_id',
                ]);
            });
        }
    }

    public function down(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Do not aggressively destroy the old Attendance system here.
        |--------------------------------------------------------------------------
        |
        | This migration is corrective and sits on top of the existing
        | foundation. We intentionally leave the existing architecture intact
        | rather than risking the legacy attendance data.
        |
        */

        if (Schema::hasTable('audit_logs')) {
            Schema::dropIfExists('audit_logs');
        }

        if (Schema::hasTable('attendances')) {
            Schema::table('attendances', function (Blueprint $table) {
                if (Schema::hasColumn('attendances', 'session_id')) {
                    $table->dropForeign(['session_id']);
                    $table->dropColumn('session_id');
                }
            });
        }

        if (Schema::hasTable('registrations')) {
            Schema::table('registrations', function (Blueprint $table) {
                $columns = [
                    'guest_name',
                    'guest_email',
                    'guest_phone',
                    'organisation',
                    'position',
                    'status',
                    'registered_at',
                ];

                foreach ($columns as $column) {
                    if (Schema::hasColumn('registrations', $column)) {
                        $table->dropColumn($column);
                    }
                }

                if (Schema::hasColumn('registrations', 'user_id')) {
                    $table->dropForeign(['user_id']);
                    $table->dropColumn('user_id');
                }
            });
        }

        if (Schema::hasTable('event_sessions')) {
            Schema::table('event_sessions', function (Blueprint $table) {
                foreach ([
                             'attendance_opens_at',
                             'attendance_closes_at',
                             'pin',
                             'created_by',
                             'updated_by',
                         ] as $column) {
                    if (Schema::hasColumn('event_sessions', $column)) {
                        if (in_array($column, ['created_by', 'updated_by'])) {
                            $table->dropForeign([$column]);
                        }

                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('events')) {
            Schema::table('events', function (Blueprint $table) {
                foreach ([
                             'organizer_id',
                             'created_by',
                             'updated_by',
                         ] as $column) {
                    if (Schema::hasColumn('events', $column)) {
                        $table->dropForeign([$column]);
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
