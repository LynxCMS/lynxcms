<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use LynxCms\Core\Query\Sql;
use LynxCms\Core\Support\Tables;

/**
 * The search index (ADR-0054): folded words per page and language, with the database's own full
 * text search on top: an FTS5 table on SQLite, a generated tsvector with a GIN index on PostgreSQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create(Tables::SEARCH, function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('page_id');
            $table->unsignedBigInteger('language_id');
            $table->text('content');
            $table->unique(['page_id', 'language_id']);
            $table->index('language_id');
        });

        $connection = Schema::getConnection();
        $grammar = $connection->getQueryGrammar();
        $search = $grammar->wrapTable(Tables::SEARCH);

        match ($connection->getDriverName()) {
            'sqlite' => $connection->statement(Sql::trusted('CREATE VIRTUAL TABLE '.$grammar->wrapTable(Tables::SEARCH_FTS)
                ." USING fts5(content, content='".$connection->getTablePrefix().Tables::SEARCH."', content_rowid='id')")),
            'pgsql' => $this->postgres($search, $grammar->wrap($connection->getTablePrefix().Tables::SEARCH.'_document')),
            default => null,
        };
    }

    public function down(): void
    {
        Schema::dropIfExists(Tables::SEARCH_FTS);
        Schema::dropIfExists(Tables::SEARCH);
    }

    private function postgres(string $table, string $index): void
    {
        $connection = Schema::getConnection();
        $connection->statement(Sql::trusted("ALTER TABLE {$table} ADD COLUMN document tsvector GENERATED ALWAYS AS (to_tsvector('simple', content)) STORED"));
        $connection->statement(Sql::trusted("CREATE INDEX {$index} ON {$table} USING GIN (document)"));
    }
};
