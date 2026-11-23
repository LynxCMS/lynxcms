<?php

declare(strict_types=1);

namespace LynxCms\Core\Console\Concerns;

use Illuminate\Support\Composer;
use Illuminate\Support\Facades\Process;
use LynxCms\Core\Install\ModuleCatalog;
use LynxCms\Core\Support\Value;

use function Laravel\Prompts\multiselect;

/**
 * lynx:install's modules: --modules names them, or a picker asks which of the free modules the
 * site lacks to add (the admin ticked). Composer requires them; the running process loaded the app
 * before they existed, so a new one applies their database changes and finishes installing.
 *
 * @internal
 */
trait AddsModules
{
    /**
     * The modules to add: none when the site has them all, or when nobody can be asked and
     * --modules is not given. Null after an unknown name.
     *
     * @return list<string>|null
     */
    private function chosenModules(ModuleCatalog $modules): ?array
    {
        $given = $this->option('modules');

        if ($given !== null) {
            [$known, $unknown] = $modules->parse(Value::string($given));

            if ($unknown !== []) {
                $this->components->error('Unknown module: '.implode(', ', $unknown).'. Choose from '.implode(', ', array_keys(ModuleCatalog::MODULES)).'.');

                return null;
            }

            return $known;
        }

        $missing = $modules->missing();

        if ($missing === [] || ! $this->canAsk()) {
            return [];
        }

        return Value::strings(multiselect('Which modules should Lynx add?', $missing, default: array_values(array_intersect([ModuleCatalog::DEFAULT], array_keys($missing))), scroll: count($missing)));
    }

    /**
     * Requires the modules, then runs lynx:migrate and lynx:install again in a new process.
     *
     * @param  list<string>  $requirements
     */
    private function addModules(array $requirements, Composer $composer): int
    {
        $this->components->info('Adding '.implode(', ', $requirements).' with Composer.');

        if (! $composer->requirePackages($requirements, output: $this->output)) {
            $this->components->error('Composer could not add the modules. Run composer require '.implode(' ', $requirements).', then php artisan lynx:install.');

            return self::FAILURE;
        }

        $migrated = $this->artisanProcess(['lynx:migrate', '--force']);

        return $migrated ? ($this->artisanProcess(array_values(array_filter([
            'lynx:install',
            '--modules=',
            $this->option('user') !== null ? '--user='.Value::string($this->option('user')) : null,
            $this->canAsk() ? null : '--no-interaction',
        ]))) ? self::SUCCESS : self::FAILURE) : self::FAILURE;
    }

    /** @param  list<string>  $arguments */
    private function artisanProcess(array $arguments): bool
    {
        $process = Process::path(base_path())->forever()->tty($this->canAsk() && \Symfony\Component\Process\Process::isTtySupported());

        return $process->run([PHP_BINARY, 'artisan', ...$arguments], fn (string $type, string $output) => $this->output->write($output))->successful();
    }
}
