<?php

namespace Artisan;

use Exception;
use RuntimeException;
use Illuminate\Support\Str;
use Artisan\Console\Config;
use Artisan\Console\ArgvInput;
use Illuminate\Support\Collection;
use Illuminate\Foundation\Console as BaseConsole;
use Illuminate\Foundation\Http;
use Illuminate\Foundation\Exceptions;
use Illuminate\Support\ServiceProvider;
use Artisan\Command\Resolve as ResolveCommands;
use Symfony\Component\Console\Output\ConsoleOutput;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;

class Console extends ServiceProvider
{
    use ResolveCommands;

    /**
     * This the custom config defined by user.
     *
     * @var \Illuminate\Console\Config
     */
    private $config;

    /**
     * The main interface for run console application.
     *
     * @var array
     */
    private $interfaces = [
        'Illuminate\Contracts\Http\Kernel'            => Http\Kernel::class,
        'Illuminate\Contracts\Console\Kernel'         => Console\Kernel::class,
        'Illuminate\Contracts\Debug\ExceptionHandler' => Exceptions\Handler::class
    ];

    /**
     * The main commands for console.
     *
     * @var array
     */
    protected $defaultCommands = [
        'command.console.make' => BaseConsole\ConsoleMakeCommand::class,
        'command.environment'  => BaseConsole\EnvironmentCommand::class
    ];

    /**
     * This configuration error
     *
     * @var \RuntimeException
     */
    private $error;

    /**
     * This is the console input
     *
     * @var \Illuminate\Console\ArgvInput
     */
    private $input;

    /**
     * This is the console output
     *
     * @var \Symfony\Component\Console\Output\ConsoleOutput
     */
    private $output;

    /**
     * Crea a new instance of console
     *
     * @param string $basePath
     */
    public function __construct($basePath)
    {
        parent::__construct(new Application($basePath));
        $this->input  = new ArgvInput;
        $this->output = new ConsoleOutput;
        $this->config = $this->getConfiguration($basePath);
    }

    /**
     * Start console listen commands
     *
     * @param  callable|null $beforeHandle
     * @return null|int
     */
    public function start(callable $beforeHandle = null)
    {
        // If has configuration error this report by kernel to console
        if ($this->hasError()) {
            return $this->reportError();
        }
        $this->useCustomPaths();
        $this->resolveInterfaces();
        $this->registerCommands();

        $kernel = $this->bootstrapKernel();

        if (is_callable($beforeHandle)) {
            $this->app->call($beforeHandle);
        }

        $status = $kernel->handle($this->input, $this->output);

        $kernel->terminate($this->input, $status);

        return $status;
    }

    /**
     * Register commands in container and add artisan.
     *
     * @return void
     */
    public function registerCommands()
    {

        $commands = [];

        foreach ($this->getAllCommands() as $name => $command) {
            $commands[] = $this->registerCommand($name, $command);
        }

        $this->commands($commands);
    }

    /**
     * Shortcut for run console
     *
     * @param  string $basePath
     * @param callable $beforeHandler
     * @return int
     */
    public static function run($basePath, callable $beforeHandler = null)
    {
        return (new static($basePath))->start($beforeHandler);
    }

    /**
     * Report error and stop console
     *
     * @param  string $string
     *
     * @return null
     */
    private function registerError($string)
    {
        $this->error = new RuntimeException($string);

        return;
    }

    private function hasError()
    {
        return !is_null($this->error);
    }

    /**
     * Bootstrap the kernel
     *
     * @return \Illuminate\Contracts\Console\Kernel
     */
    private function bootstrapKernel()
    {
        $kernel = $this->app->make(ConsoleKernel::class);
        $kernel->bootstrap();

        return $kernel;
    }

    /**
     * Get the config store on composer file in basePath
     *
     * @param string $basePath
     * @return null|\Illuminate\Support\Collection
     */
    private function getConfiguration($basePath)
    {
        $config       = new Config($basePath);
        $applications = $config->applications();

        if ($applications->isEmpty()) {
            return $this->registerError(
                'There are not valid laravel configuration on: ' . PHP_EOL . $config->filePath()
            );
        }

        if ($applications->count() > 1 &&
            ($appName = $this->findApplicationFromArgvInput($applications))
        ) {
            return $applications->get($appName);
        }

        return $applications->first();
    }

    /**
     * @param Collection $applications
     * @return null|string
     */
    private function findApplicationFromArgvInput(Collection $applications)
    {
        foreach ($applications->keys() as $appName) {
            if ($this->input->hasParameterOption($appName)) {
                $this->input->removeParameterOption($appName);
                return $appName;
            }
        }

        return null;
    }

    /**
     * Set the custom paths
     *
     * @return void
     */
    private function useCustomPaths()
    {
        foreach ($this->config->get('paths', []) as $key => $path) {
            $key             = $key == 'path' ? $key : 'path.' . $key;
            $path            = realpath($this->app->basePath() . '/' . $path);
            $this->app->setPath($key, $path);
        }
    }

    /**
     * Resolve application interfaces
     *
     * @return array
     */
    private function applicationInterfaces()
    {
        $interfaces     = [];
        $namespace = $this->app->getNamespace();
        foreach ($this->interfaces as $contract => $foundationClass) {
            $appClass = $this->resolveApplicationClassName($namespace, $foundationClass);
            $classPath = $this->getClassPath($appClass);
            $interfaces[$contract] = file_exists($classPath) ? $appClass : $foundationClass;
        }

        return $interfaces;
    }

    /**
     * Resolve interface for custom app or base laravel application
     *
     * @return void
     */
    private function resolveInterfaces()
    {
        foreach ($this->applicationInterfaces() as $name => $value) {
            $this->app->singleton($name, $value);
        }
    }

    private function getClassPath($className)
    {
        $className = str_replace('\\', '/', Str::camel($className));

        return $this->app->basePath() . '/' . $className . '.php';
    }

    private function resolveApplicationClassName($namespace, $className)
    {
        return str_replace('Illuminate\\Foundation\\', $namespace, $className);
    }

    /**
     * Report the exception to the exception handler.
     *
     * @param  \Exception  $e
     * @return int
     */
    protected function reportError(Exception $e = null)
    {
        $e = $e ?: $this->error;
        $this->bootstrapKernel();

        $handler = $this->app->make(ExceptionHandlerContract::class);

        $handler->report($e);
        $handler->renderForConsole($this->output, $e);

        return 1;
    }
}
