<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->replaceEventReference('event_type_id', true);
        $this->replaceEventReference('location_id', true);
    }

    public function down(): void
    {
        $this->replaceEventReference('event_type_id', false);
        $this->replaceEventReference('location_id', false);
    }

    private function replaceEventReference(string $column, bool $restrict): void
    {
        $foreignKey = collect(Schema::getForeignKeys('events'))
            ->first(fn (array $key): bool => ($key['columns'] ?? []) === [$column]);

        if ($foreignKey !== null) {
            $name = DB::connection()->getDriverName() === 'sqlite'
                ? [$column]
                : $foreignKey['name'];

            Schema::table('events', fn (Blueprint $table) => $table->dropForeign($name));
        }

        Schema::table('events', function (Blueprint $table) use ($column, $restrict): void {
            $foreign = $table->foreign($column)
                ->references('id')
                ->on($column === 'event_type_id' ? 'event_types' : 'locations');

            if ($restrict) {
                $foreign->restrictOnDelete();
            } else {
                $foreign->nullOnDelete();
            }
        });
    }
};
