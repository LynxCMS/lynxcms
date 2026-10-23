<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

/**
 * The starter's demo: an owner account, Lynx installed with that owner as superuser, and Atlas
 * Training's schema and content. php artisan migrate:fresh --seed starts over.
 */
final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::query()->firstOrCreate(
            ['email' => 'owner@atlas-training.example'],
            ['name' => 'Olga Owner', 'password' => Hash::make((string) env('ATLAS_OWNER_PASSWORD', 'change-me-now'))],
        );

        Artisan::call('lynx:install', ['--user' => (string) $owner->id]);

        $this->call([AtlasSchemaSeeder::class, AtlasContentSeeder::class, AtlasEditorsSeeder::class, AtlasSeoSeeder::class, AtlasSurveySeeder::class, AtlasCacheSeeder::class]);
    }
}
