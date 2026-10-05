<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('attendances') || !Schema::hasColumn('attendances', 'attendance_event_id')) {
            return;
        }

        if (Schema::hasColumn('attendances', 'session_id')) {
            $legacyRows = DB::table('attendances')
                ->whereNotNull('attendance_event_id')
                ->get(['id', 'attendance_event_id', 'session_id']);

            foreach ($legacyRows as $row) {
                if ($row->session_id !== null && (int) $row->session_id !== (int) $row->attendance_event_id) {
                    throw new RuntimeException("Attendance {$row->id} has conflicting legacy and current session references; migration stopped to preserve the record.");
                }

                $sessionExists = Schema::hasTable('event_sessions')
                    && DB::table('event_sessions')->where('id', $row->attendance_event_id)->exists();

                if (!$sessionExists) {
                    throw new RuntimeException("Attendance {$row->id} references a legacy event without a matching session; migration stopped to preserve the record.");
                }

                if ($row->session_id === null) {
                    $duplicateExists = $row->email !== null && DB::table('attendances')
                        ->where('id', '!=', $row->id)
                        ->where('session_id', $row->attendance_event_id)
                        ->whereRaw('LOWER(email) = ?', [strtolower($row->email)])
                        ->exists();

                    if ($duplicateExists) {
                        throw new RuntimeException("Attendance {$row->id} would conflict with another check-in for the same session and email; migration stopped to preserve the records.");
                    }

                    DB::table('attendances')->where('id', $row->id)->update([
                        'session_id' => $row->attendance_event_id,
                    ]);
                }
            }
        } elseif (DB::table('attendances')->whereNotNull('attendance_event_id')->exists()) {
            throw new RuntimeException('Legacy attendance rows exist but session_id is missing; migration stopped to preserve the records.');
        }

        foreach (Schema::getForeignKeys('attendances') as $foreignKey) {
            if (($foreignKey['columns'] ?? []) === ['attendance_event_id']) {
                $foreignKeyToDrop = DB::connection()->getDriverName() === 'sqlite'
                    ? $foreignKey['columns']
                    : $foreignKey['name'];

                Schema::table('attendances', fn (Blueprint $table) => $table->dropForeign($foreignKeyToDrop));
            }
        }

        $legacyIndexes = collect(Schema::getIndexes('attendances'))
            ->filter(fn (array $index) => in_array('attendance_event_id', $index['columns'] ?? [], true));

        foreach ($legacyIndexes as $index) {
            Schema::table('attendances', function (Blueprint $table) use ($index): void {
                if ($index['unique'] ?? false) {
                    $table->dropUnique($index['name']);
                } else {
                    $table->dropIndex($index['name']);
                }
            });
        }

        Schema::table('attendances', fn (Blueprint $table) => $table->dropColumn('attendance_event_id'));
    }

    public function down(): void
    {
        if (Schema::hasTable('attendances') && !Schema::hasColumn('attendances', 'attendance_event_id')) {
            Schema::table('attendances', function (Blueprint $table): void {
                $table->foreignId('attendance_event_id')
                    ->nullable()
                    ->constrained('attendance_events')
                    ->nullOnDelete();
            });

            if (Schema::hasTable('attendance_events') && Schema::hasColumn('attendances', 'session_id')) {
                DB::table('attendances')
                    ->whereIn('session_id', DB::table('attendance_events')->select('id'))
                    ->update(['attendance_event_id' => DB::raw('session_id')]);
            }
        }
    }
};
