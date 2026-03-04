<?php
/**
 * This is NOT a freeware, use is subject to license terms.
 */

namespace Laravel\Octane\Commands;

use Illuminate\Support\Str;
use Laravel\Octane\Workerman\ServerProcessInspector;
use Laravel\Octane\Workerman\ServerStateFile;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

#[AsCommand(name: 'octane:workerman')]
class StartWorkermanCommand extends Command implements SignalableCommandInterface
{
    use Concerns\InteractsWithEnvironmentVariables, Concerns\InteractsWithServers;

    /**
     * The command's signature.
     *
     * @var string
     */
    public $signature = 'octane:workerman
                    {--host=127.0.0.1 : The IP address the server should bind to}
                    {--port= : The port the server should be available on}
                    {--workers=auto : The number of workers that should be available to handle requests}
                    {--task-workers=auto : The number of task workers that should be available to handle tasks}
                    {--max-requests=500 : The number of requests to process before reloading the server}
                    {--watch : Automatically reload the server when the application is modified}
                    {--poll : Use file system polling while watching in order to watch files over a network}';

    /**
     * The command's description.
     *
     * @var string
     */
    public $description = 'Start the Octane Workerman server';

    /**
     * Indicates whether the command should be shown in the Artisan command list.
     *
     * @var bool
     */
    protected $hidden = true;

    /**
     * Handle the command.
     *
     * @return int
     */
    public function handle(ServerProcessInspector $inspector, ServerStateFile $serverStateFile) {
        $this->ensurePortIsAvailable();
        if ($inspector->serverIsRunning()) {
            $this->components->error('Server is already running.');

            return 1;
        }
        // 写入服务状态文件
        $this->writeServerStateFile($serverStateFile);

        $this->forgetEnvironmentVariables();

        $process = tap(new Process([
            (new PhpExecutableFinder)->find(),
            ...config('octane.workerman.php_options', []),
            config('octane.workerman.command', 'workerman-server'),
            $serverStateFile->path(),
        ], realpath(__DIR__.'/../../bin'), [
            'APP_ENV' => app()->environment(),
            'APP_BASE_PATH' => base_path(),
            'LARAVEL_OCTANE' => 1,
            'MAX_REQUESTS' => $this->option('max-requests'),
        ]));

        $process->start();
        $serverStateFile->writeProcessId($server->getPid());
        return $this->runServer($server, $inspector, 'workerman');
    }

    public function isDaemon()
    {
        return $this->argument('mode') === 'daemon';
    }

    /**
     * Write the Swoole server state file.
     *
     * @return void
     */
    protected function writeServerStateFile(ServerStateFile $serverStateFile) {
        $serverStateFile->writeState([
            'appName' => config('app.name', 'Laravel'),
            'host' => $this->getHost(),
            'port' => $this->getPort(),
            'workers' => 1,
            'taskWorkers' => 1,
            'maxRequests' => $this->option('max-requests'),
            'publicPath' => public_path(),
            'storagePath' => storage_path(),
            'defaultServerOptions' => $this->defaultServerOptions(),
            'octaneConfig' => config('octane'),
        ]);
    }

    /**
     * Get the default Swoole server options.
     *
     * @return array
     */
    protected function defaultServerOptions(SwooleExtension $extension)
    {
        return [
            'event_loop' => '',
            'stop_timeout' => 2,
            'pid_file' => storage_path('webman.pid'),
            'status_file' => storage_path('webman.status'),
            'stdout_file' => storage_path('logs/stdout.log'),
            'log_file' => storage_path('logs/worker.log'),
            'max_package_size' => 10 * 1024 * 1024
        ];
    }

    /**
     * Write the server process output ot the console.
     *
     * @param  \Symfony\Component\Process\Process  $server
     * @return void
     */
    protected function writeServerOutput($server)
    {
        [$output, $errorOutput] = $this->getServerOutput($server);

        Str::of($output)
            ->explode("\n")
            ->filter()
            ->each(fn ($output) => is_array($stream = json_decode($output, true))
                ? $this->handleStream($stream)
                : $this->components->info($output)
            );

        Str::of($errorOutput)
            ->explode("\n")
            ->filter()
            ->groupBy(fn ($output) => $output)
            ->each(function ($group) {
                is_array($stream = json_decode($output = $group->first(), true)) && isset($stream['type'])
                    ? $this->handleStream($stream)
                    : $this->raw($output);
            });
    }

    /**
     * Stop the server.
     *
     * @return void
     */
    protected function stopServer()
    {
        $this->callSilent('octane:stop', [
            '--server' => 'workerman',
        ]);
    }
}
