<?php
/**
 * This is NOT a freeware, use is subject to license terms.
 */

namespace Laravel\Octane\Commands\Concerns;

use RuntimeException;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Process;
use Workerman\Worker;


trait InstallsWorkermanDependencies
{
    /**
     * Ensure the Workerman package is installed into the project.
     *
     * @return bool
     */
    protected function ensureWorkermanPackageIsInstalled()
    {
        if (class_exists(Worker::class)) {
            return true;
        }

        if (! $this->confirm('Octane requires "workerman/workerman:^5.0". Do you wish to install it as a dependency?')) {
            $this->error('Octane requires "workerman/workerman".');

            return false;
        }

        $command = $this->findComposer().' require workerman/workerman:^5.0 --with-all-dependencies';

        $process = Process::fromShellCommandline($command, null, null, null, null);

        if ('\\' !== DIRECTORY_SEPARATOR && file_exists('/dev/tty') && is_readable('/dev/tty')) {
            try {
                $process->setTty(true);
            } catch (RuntimeException $e) {
                $this->output->writeln('Warning: '.$e->getMessage());
            }
        }

        try {
            $process->run(function ($type, $line) {
                $this->output->write($line);
            });
        } catch (ProcessSignaledException $e) {
            if (extension_loaded('pcntl') && $e->getSignal() !== SIGINT) {
                throw $e;
            }
        }

        return true;
    }
}
