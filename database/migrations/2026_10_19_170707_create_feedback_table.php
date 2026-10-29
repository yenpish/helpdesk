
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_id')
                ->constrained('events')
                ->cascadeOnDelete();

            $table->string('participant_name');
            $table->string('participant_email');

            $table->unsignedTinyInteger('rating');
            $table->text('comments')->nullable();

            $table->timestamps();

            $table->unique(
                ['event_id', 'participant_email'],
                'feedback_event_email_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback');
    }
};
