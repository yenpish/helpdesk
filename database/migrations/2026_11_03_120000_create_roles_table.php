<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name')->unique();
            $table->json('permissions')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        $now = now();
        $builtIn = [
            [
                'slug' => 'admin',
                'name' => 'System Admin',
                'permissions' => [],
                'is_system' => true,
            ],
            [
                'slug' => 'organizer',
                'name' => 'Organizer',
                'permissions' => [
                    'dashboard.view',
                    'events.manage',
                    'events.publish',
                ],
                'is_system' => true,
            ],
            [
                'slug' => 'user',
                'name' => 'User',
                'permissions' => [],
                'is_system' => true,
            ],
        ];

        foreach ($builtIn as $role) {
            DB::table('roles')->insert([
                'slug' => $role['slug'],
                'name' => $role['name'],
                'permissions' => json_encode($role['permissions']),
                'is_system' => $role['is_system'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $existingRoleSlugs = DB::table('users')
            ->select('role')
            ->distinct()
            ->pluck('role');

        foreach ($existingRoleSlugs as $existingSlug) {
            $slug = (string) $existingSlug;

            if (in_array($slug, ['admin', 'organizer', 'user'], true)) {
                continue;
            }

            $name = trim($slug) !== '' ? trim($slug) : 'Legacy role';
            $candidate = $name;
            $suffix = 2;
            while (DB::table('roles')->where('name', $candidate)->exists()) {
                $candidate = $name . ' (legacy ' . $suffix++ . ')';
            }

            DB::table('roles')->insert([
                'slug' => $slug,
                'name' => $candidate,
                'permissions' => json_encode([]),
                'is_system' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
