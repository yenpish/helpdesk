<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feedback_invitations', function (Blueprint $table) {
            // Existing rows were committed only after the mail callback returned.
            $table->string('status')->default('accepted');
        });
    }

    public function down(): void
    {
        Schema::table('feedback_invitations', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
