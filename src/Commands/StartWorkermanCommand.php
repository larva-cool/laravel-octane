<?php
/**
 * This is NOT a freeware, use is subject to license terms.
 */

namespace Laravel\Octane\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\SignalableCommandInterface;

#[AsCommand(name: 'octane:workerman')]
class StartWorkermanCommand extends Command implements SignalableCommandInterface
{
    use Concerns\InteractsWithEnvironmentVariables, Concerns\InteractsWithServers, Concerns\InstallsWorkermanDependencies;



}
