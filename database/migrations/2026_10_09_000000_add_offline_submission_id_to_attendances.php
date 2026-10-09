<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table): void {
            $table->uuid('offline_submission_id')
                ->nullable()
                ->unique('attendances_offline_submission_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table): void {
            $table->dropUnique('attendances_offline_submission_id_unique');
            $table->dropColumn('offline_submission_id');
        });
    }
};
