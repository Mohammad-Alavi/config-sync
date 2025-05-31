<?php

namespace MohammadAlavi\ConfigSync;

use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;

final class CommandProvider implements CommandProviderCapability
{
    public function getCommands(): array
    {
        return [
            new Command(),
        ];
    }
}
