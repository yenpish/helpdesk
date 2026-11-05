<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table): void {
            $table->string('decision_email_status')->nullable();
            $table->string('decision_email_for')->nullable();
            $table->timestamp('decision_email_attempted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table): void {
            $table->dropColumn([
                'decision_email_status',
                'decision_email_for',
                'decision_email_attempted_at',
            ]);
        });
    }
};
