<?php

declare(strict_types=1);

namespace Aenzenith\BirFatura\Console;

use Aenzenith\BirFatura\BirFatura;
use Illuminate\Console\Command;
use Throwable;

/**
 * What to enter in the BirFatura panel and what currently answers. Never
 * prints the token, only whether one resolves.
 */
final class AboutCommand extends Command
{
    protected $signature = 'birfatura:about';

    protected $description = 'Show the BirFatura site address, endpoints and configuration status';

    public function handle(BirFatura $birFatura): int
    {
        try {
            $endpoints = $birFatura->endpoints();
            $enabled = $birFatura->enabled();
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('<fg=green;options=bold>BirFatura</>');
        $this->components->twoColumnDetail('Site address (enter in the panel)', $birFatura->baseUrl());
        $this->components->twoColumnDetail('Token', $enabled ? '<fg=green>resolved</>' : '<fg=yellow>missing — integration OFF</>');
        $this->components->twoColumnDetail('Routes registered', $birFatura->routesRegistered() ? 'yes' : '<fg=yellow>no</>');
        $this->components->twoColumnDetail('HTTPS required', config('birfatura.security.require_https') ? 'yes' : '<fg=yellow>no</>');
        $ips = (array) config('birfatura.security.allowed_ips', []);
        $this->components->twoColumnDetail('IP allow-list', $ips === [] ? 'any' : implode(', ', array_map('strval', $ips)));

        $this->newLine();
        $this->components->twoColumnDetail('<fg=green;options=bold>Endpoints</>');

        foreach ($endpoints as $name => $endpoint) {
            $this->components->twoColumnDetail($name.' <fg=gray>'.$endpoint['url'].'</>', $endpoint['open'] ? '<fg=green>open</>' : '<fg=yellow>closed</>');
        }

        return self::SUCCESS;
    }
}
