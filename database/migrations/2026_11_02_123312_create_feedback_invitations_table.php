
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback_invitations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_id')
                ->constrained('events')
                ->cascadeOnDelete();

            $table->foreignId('attendance_id')
                ->unique()
                ->constrained('attendances')
                ->cascadeOnDelete();

            $table->string('participant_name');
            $table->string('participant_email');

            // Store only the SHA-256 hash, never the raw token.
            $table->char('token_hash', 64)->unique();

            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();

            $table->timestamps();

            // One invitation per attendee email per event.
            $table->unique(
                ['event_id', 'participant_email'],
                'feedback_invitation_event_email_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_invitations');
    }
};
