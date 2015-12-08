<?php

namespace Illuminate\Foundation;

use Illuminate\Support\Str;
use Illuminate\Console\Config;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Console\Input\ArgvInput;
use Illuminate\Console\ResolveCommands;
use Symfony\Component\Console\Output\ConsoleOutput;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;

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
    private $binds = [
        'Illuminate\Contracts\Http\Kernel'            => 'Illuminate\Foundation\Http\Kernel',
        'Illuminate\Contracts\Console\Kernel'         => 'Illuminate\Foundation\Console\Kernel',
        'Illuminate\Contracts\Debug\ExceptionHandler' => 'Illuminate\Foundation\Exceptions\Handler'
    ];

    /**
     * The main commands for console.
     *
     * @var array
     */
    protected $defaultCommands = [
        'command.console.make' => 'Illuminate\Foundation\Console\ConsoleMakeCommand',
        'command.environment'  => 'Illuminate\Foundation\Console\EnvironmentCommand'
    ];

    private $output;

    /**
     * Crea a new instance of console
     *
     * @param string $basePath
     */
    public function __construct($basePath)
    {
        $this->output    = new ConsoleOutput;
        $this->config    = $this->getConfig($basePath);
        parent::__construct(new Application($basePath));
        $this->useCustomPaths();
        $this->resolveInterfaces($this->useNamespace());
    }

    /**
     * Start console listen commands
     *
     * @return void
     */
    public function start()
    {
        $this->register();

        $this->bindInterfaces();

        $kernel = $this->app->make(ConsoleKernel::class);

        $status = $kernel->handle($input = new ArgvInput, $this->output);

        $kernel->terminate($input, $status);

        return $status;
    }

    /**
     * Shortcut for run console
     *
     * @param  string $basePath
     *
     * @return void
     */
    public static function run($basePath)
    {
        return (new static($basePath))->start();
    }

    /**
     * Report error and stop console
     *
     * @param  string $string
     *
     * @return void
     */
    private function error($string)
    {
        $this->output->writeln("<error>$string</error>");
        exit();
    }

    /**
     * Get the config store on composer file in basePath
     *
     * @param string $basePath
     * @return \Illuminate\Support\Collection
     */
    private function getConfig($basePath)
    {
        $config       = new Config($basePath);
        $applications = $config->applications();
        if ($applications->isEmpty()) {
            $this->error(
                'There are not valid laravel configuration on: ' . PHP_EOL . $config->filePath()
            );
        }

        if ($applications->count() > 1) {
            $name = $this->getAppNameFromConsoleArgs();
            if (!is_null($name)) {
                if (!$applications->has($name)) {
                    $this->error('This is not valid laravel application: ' . $name);
                }

                return $applications->get($name);
            }
        }

        return $applications->first();
    }

    /**
     * Extract the application name from first argument
     *
     * @return string|null
     */
    private function getAppNameFromConsoleArgs()
    {
        $name = null;
        if (count($_SERVER['argv']) >= 2) {
            foreach ($_SERVER['argv'] as $i => $arg) {
                if ($i && strpos($arg, '--') === false) {
                    $name = $arg;
                    unset($_SERVER['argv'][$i]);
                    break;
                }
            }
            $_SERVER['argv'] = array_values($_SERVER['argv']);
            $_SERVER['argc'] = count($_SERVER['argv']);
        }

        return $name;
    }

    private function useNamespace()
    {
        $namespace = $this->config->get('namespace');
        $this->app->useNamespace($namespace);

        return $namespace;
    }

    /**
     * Set the custom paths
     *
     * @return void
     */
    private function useCustomPaths()
    {
        foreach ($this->config->get('paths') as $key => $path) {
            $key             = $key == 'path' ? $key : 'path.' . $key;
            $path            = realpath($this->app->basePath() . '/' . $path);
            $this->app[$key] = $path;
        }
    }

    private function resolveInterfaces($namespace)
    {
        $binds = [];
        foreach ($this->binds as $contract => $foundationClass) {
            $appClass = str_replace('Illuminate\\Foundation\\', $namespace, $foundationClass);
            $classPath = base_path(str_replace('\\', '/', Str::camel($appClass))) . '.php';
            $binds[$contract] = file_exists($classPath) ? $appClass : $foundationClass;
        }

        $this->binds  = $binds;
    }

    private function bindInterfaces()
    {
        // Register interfaces
        foreach ($this->binds as $name => $value) {
            $this->app->singleton($name, $value);
        }
    }
}
