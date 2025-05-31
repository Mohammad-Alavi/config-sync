<?php

namespace MohammadAlavi\ConfigSync;

use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
use MohammadAlavi\ConfigSync\Commands\Init;
use MohammadAlavi\ConfigSync\Commands\Sync;

final class CommandProvider implements CommandProviderCapability
{
    public function getCommands(): array
    {
        return [
            new Sync(),
            new Init(),
        ];
    }
}
