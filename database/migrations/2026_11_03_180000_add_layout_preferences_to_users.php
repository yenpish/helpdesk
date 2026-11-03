<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('appearance_density', 20)->default('comfortable');
            $table->string('appearance_sidebar', 20)->default('standard');
            $table->boolean('appearance_gradients')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['appearance_density', 'appearance_sidebar', 'appearance_gradients']);
        });
    }
};
