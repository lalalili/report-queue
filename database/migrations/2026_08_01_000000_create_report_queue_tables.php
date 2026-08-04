<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Lalalili\ReportQueue\Support\Config;

/**
 * Idempotent by design: every host adopting this package already owns a
 * `reports` table, with small differences in constraints and in which columns
 * were added later. Recreating it would be destructive, so an existing table is
 * only topped up with the columns this package needs.
 */
return new class () extends Migration {
    public function up(): void
    {
        $table = $this->table();

        if (! Schema::hasTable($table)) {
            $this->create($table);

            return;
        }

        $this->addMissingColumns($table);
    }

    public function down(): void
    {
        // Intentionally not dropping the table: in every current host it
        // predates this package and holds host-owned history.
    }

    private function create(string $table): void
    {
        $userTable = Config::nullableString('user.table');
        $foreignKey = Config::string('user.foreign_key', 'user_id');

        Schema::create($table, function (Blueprint $blueprint) use ($userTable, $foreignKey): void {
            $blueprint->id();

            $blueprint->unsignedBigInteger($foreignKey)->nullable()->index();

            if ($userTable !== null) {
                $blueprint->foreign($foreignKey)->references('id')->on($userTable)->nullOnDelete();
            }

            $blueprint->string('type');
            $blueprint->json('params')->nullable();
            $blueprint->tinyInteger('status')->default(1)->index();
            $blueprint->tinyInteger('progress')->default(0);
            $blueprint->unsignedInteger('count')->default(0);
            $blueprint->string('file_path')->nullable();
            $blueprint->string('file_disk')->nullable();
            $blueprint->text('error')->nullable();
            $blueprint->timestamp('heartbeat_at')->nullable();
            $blueprint->timestamp('started_at')->nullable();
            $blueprint->timestamp('finished_at')->nullable();
            $blueprint->timestamps();

            $blueprint->index([$foreignKey, 'created_at']);
        });
    }

    private function addMissingColumns(string $table): void
    {
        $additions = [
            'file_disk' => static fn (Blueprint $blueprint): mixed => $blueprint->string('file_disk')->nullable(),
            'heartbeat_at' => static fn (Blueprint $blueprint): mixed => $blueprint->timestamp('heartbeat_at')->nullable(),
        ];

        $missing = array_filter(
            $additions,
            static fn (string $column): bool => ! Schema::hasColumn($table, $column),
            ARRAY_FILTER_USE_KEY,
        );

        if ($missing === []) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($missing): void {
            foreach ($missing as $add) {
                $add($blueprint);
            }
        });
    }

    private function table(): string
    {
        return Config::string('table', 'reports');
    }
};
