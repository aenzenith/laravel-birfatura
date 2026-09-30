<?php

declare(strict_types=1);

namespace Aenzenith\BirFatura\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Generates a fresh GUID for the "API Şifresi" field. Prints it only —
 * where it is stored (env, encrypted settings) is the application's choice.
 */
final class TokenCommand extends Command
{
    protected $signature = 'birfatura:token';

    protected $description = 'Generate a new BirFatura integration token (GUID)';

    public function handle(): int
    {
        $this->line((string) Str::uuid());

        return self::SUCCESS;
    }
}
