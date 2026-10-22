<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LynxCms\Core\Support\Tables;

/**
 * The only migration Lynx ships. After install, languages, fields, templates and pages are
 * data or runtime DDL: adding a language never needs a migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create(Tables::LANGUAGES, function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->string('locale', 32)->nullable();
            $table->foreignId('fallback_id')->nullable()->constrained(Tables::LANGUAGES)->nullOnDelete();
            $table->string('url_prefix', 64)->default('');
            $table->json('provider_codes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->unique('url_prefix');
        });

        Schema::create(Tables::SETTINGS, function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->text('value')->nullable();
        });

        Schema::create(Tables::FIELDS, function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 64)->unique();
            $table->string('type', 64);
            $table->string('label');
            $table->string('scope', 16)->default('shared');
            $table->json('settings')->nullable();
            $table->unsignedInteger('schema_version')->default(1);
            $table->timestamps();
        });

        Schema::create(Tables::TEMPLATES, function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 64)->unique();
            $table->string('label');
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create(Tables::TEMPLATE_FIELDS, function (Blueprint $table): void {
            $table->foreignId('template_id')->constrained(Tables::TEMPLATES)->cascadeOnDelete();
            $table->foreignId('field_id')->constrained(Tables::FIELDS)->cascadeOnDelete();
            $table->unsignedInteger('sort')->default(0);
            $table->json('settings')->nullable();
            $table->primary(['template_id', 'field_id']);
        });

        Schema::create(Tables::PAGES, function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained(Tables::PAGES)->restrictOnDelete();
            $table->foreignId('template_id')->constrained(Tables::TEMPLATES)->restrictOnDelete();
            $table->unsignedInteger('status')->default(0);
            $table->unsignedInteger('sort')->default(0);
            $table->string('created_by')->nullable();
            $table->string('modified_by')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['parent_id', 'sort']);
            $table->index('template_id');
        });

        Schema::create(Tables::PAGE_CLOSURE, function (Blueprint $table): void {
            $table->foreignId('ancestor_id')->constrained(Tables::PAGES)->cascadeOnDelete();
            $table->foreignId('descendant_id')->constrained(Tables::PAGES)->cascadeOnDelete();
            $table->unsignedInteger('depth');
            $table->primary(['ancestor_id', 'descendant_id']);
            $table->index(['descendant_id', 'depth']);
        });

        Schema::create(Tables::PAGE_LANGUAGES, function (Blueprint $table): void {
            $table->foreignId('page_id')->constrained(Tables::PAGES)->cascadeOnDelete();
            $table->foreignId('language_id')->constrained(Tables::LANGUAGES)->cascadeOnDelete();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('name');
            $table->string('status', 16)->default('published');
            $table->timestamp('updated_at')->nullable();
            $table->string('updated_by')->nullable();
            $table->primary(['page_id', 'language_id']);
            $table->unique(['parent_id', 'language_id', 'name']);
        });

        Schema::create(Tables::PAGE_PATHS, function (Blueprint $table): void {
            $table->id();
            $table->foreignId('page_id')->constrained(Tables::PAGES)->cascadeOnDelete();
            $table->foreignId('language_id')->constrained(Tables::LANGUAGES)->cascadeOnDelete();
            $table->string('path', 2048);
            $table->boolean('active')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->index(['page_id', 'language_id', 'active']);
        });

        // One active path per page and language; lookups are case-insensitive.
        // Partial and expression indexes the schema builder cannot express. Names and the table
        // carry the connection's table prefix, like everything the schema builder creates.
        $prefix = DB::getTablePrefix();
        $paths = DB::getQueryGrammar()->wrapTable(Tables::PAGE_PATHS);
        DB::statement("CREATE UNIQUE INDEX {$prefix}lynx_page_paths_active_unique ON {$paths} (page_id, language_id) WHERE active = true");
        DB::statement("CREATE INDEX {$prefix}lynx_page_paths_lookup ON {$paths} (language_id, lower(path))");

        Schema::create(Tables::FIELD_VALUE_META, function (Blueprint $table): void {
            $table->unsignedBigInteger('page_id');
            $table->unsignedBigInteger('field_id');
            $table->unsignedBigInteger('language_id');
            $table->string('origin', 16)->default('human');
            $table->string('source_hash', 64)->nullable();
            $table->string('provider', 64)->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->primary(['page_id', 'field_id', 'language_id']);
        });

        Schema::create(Tables::ACTIVITY, function (Blueprint $table): void {
            $table->id();
            $table->string('action', 128);
            $table->string('subject_type', 64)->nullable();
            $table->string('subject_id')->nullable();
            $table->unsignedBigInteger('language_id')->nullable();
            $table->string('actor_id')->nullable();
            $table->string('actor_kind', 16);
            $table->json('changes');
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['subject_type', 'subject_id']);
            $table->index('created_at');
        });

        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'lynx_language_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->unsignedBigInteger('lynx_language_id')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'lynx_language_id')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('lynx_language_id'));
        }

        foreach ([
            Tables::ACTIVITY, Tables::FIELD_VALUE_META, Tables::PAGE_PATHS, Tables::PAGE_LANGUAGES,
            Tables::PAGE_CLOSURE, Tables::PAGES, Tables::TEMPLATE_FIELDS, Tables::TEMPLATES,
            Tables::FIELDS, Tables::SETTINGS, Tables::LANGUAGES,
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
