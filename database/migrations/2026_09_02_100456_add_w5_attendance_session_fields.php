<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_events', function (Blueprint $table) {
            $table->string('pin', 4)->unique()->after('ends_at');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();

            $table->string('full_name')->after('user_id');
            $table->string('position')->nullable()->after('full_name');
            $table->string('unit')->nullable()->after('position');
            $table->string('phone')->nullable()->after('unit');
            $table->string('email')->nullable()->after('phone');
            $table->text('signature')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_events', function (Blueprint $table) {
            $table->dropUnique(['pin']);
            $table->dropColumn('pin');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn([
                'full_name',
                'position',
                'unit',
                'phone',
                'email',
                'signature',
            ]);

            $table->foreignId('user_id')->nullable(false)->change();
        });
    }
};
