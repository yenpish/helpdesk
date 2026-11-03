<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->boolean('reminder_24h')->default(false);
            $table->boolean('reminder_1h')->default(false);
        });

        Schema::create('event_notification_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('registration_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 32);
            $table->string('notification_key', 64);
            $table->string('recipient_email');
            $table->string('status', 16)->default('pending');
            $table->unsignedInteger('attempt_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamps();
            $table->unique(
                ['event_id', 'registration_id', 'type', 'notification_key'],
                'event_notification_delivery_unique'
            );
            $table->index(['event_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_notification_deliveries');
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn(['reminder_24h', 'reminder_1h']);
        });
    }
};
