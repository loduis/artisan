<?php namespace Illuminate\Foundation;

use ReflectionClass;
use ReflectionMethod;
use InvalidArgumentException;
use Symfony\Component\Finder\Finder;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Console\Config;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Illuminate\Console\Command;

class Console
{
    /**
     * This the custom config defined by user.
     *
     * @var \Illuminate\Foundation\Console\Config
     */
    private $config;

    /**
     *  The application instance.
     *
     * @var \Illuminate\Contracts\Foundation\Application
     */
    private $app;

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
     * @var array
     */
    private $commands = [
        'command.console.make' => 'Illuminate\Foundation\Console\ConsoleMakeCommand',
        'command.environment' => 'Illuminate\Foundation\Console\EnvironmentCommand'
    ];

    private $output;

    /**
     * Crea a new instance of console
     *
     * @param string $basePath
     */
    public function __construct($basePath)
    {
        $this->app     = new Application($basePath);
        $this->output  = new ConsoleOutput;
        $this->config  = $this->getConfig();
        $this->useCustomPaths();
    }

    /**
     * Start console listen commands
     *
     * @return void
     */
    public function start()
    {
        // Register the commands

        $this->registerCommands();

        // Register interfaces
        foreach ($this->binds as $name => $value) {
            $this->app->singleton($name, $value);
        }

        // Start
        $status = $this->app->make('Illuminate\Contracts\Console\Kernel')->handle(new ArgvInput, $this->output);

        exit($status);
    }

    /**
     * Shortcut for run console
     *
     * @param  string $basePath
     * @return void
     */
    public static function run($basePath)
    {
        (new static($basePath))->start();
    }

    /**
     * Report error and stop console
     *
     * @param  string $string
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
     * @return Illuminate\Support\Collection
     */
    private function getConfig()
    {
        $config  = new Config($this->app->basePath());
        $apps    = $config->applications();
        if ($apps->isEmpty()) {
            $this->error('There are not valid laravel configuration on: ' . PHP_EOL . $this->app->basePath() . '/composer.json');
        } elseif ($apps->count() > 1) {
            $name = $this->getAppNameFromFirstArgument();
            if (is_null($name)) {
                $config = $apps->first();
            } else {
                $config = $apps->where('name', $name);
                if ($config->isEmpty()) {
                    $this->error('This is not valid laravel application: ' . $name);
                } else {
                    $config = $config->first();
                }
            }
        } else {
            $config = $apps->first();
        }

        return $config;
    }

    /**
     * Register command in container
     *
     * @param  string $commandName
     * @param  string|Illuminate\Console\Command $commandClass
     * @return void
     */
    protected function registerCommand($commandName, $commandClass)
    {
        $this->app->singleton($commandName, function () use ($commandClass) {
            return $this->instanceCommand($commandClass);
        });

        return $commandName;
    }

    /**
     * Create one instance for command class
     *
     * @param  string $commandClass
     * @return Illuminate\Console\Command
     */
    private function instanceCommand($commandClass)
    {
        $class       = new ReflectionClass($commandClass);

        return $class->newInstanceArgs($this->resolveCommandParameters($class->getConstructor()));
    }

    /**
     * Resolve the parameters in the constructor
     *
     * @param  ReflectionMethod $constructor
     * @return array
     *
     * @throws \InvalidArgumentException
     */
    protected function resolveCommandParameters(ReflectionMethod $constructor)
    {
        $params      = [];
        foreach ($constructor->getParameters() as $parameter) {
            $paramClass = $parameter->getClass();
            if (!is_null($paramClass)) {
                $paramClass   = $paramClass->name;
                $value        = $this->app->make($paramClass);
                $pos          = $parameter->getPosition();
                $params[$pos] = $value;
            } else {
                throw new InvalidArgumentException("The parameter: '" . $parameter->name . "' there is not valid class");
            }
        }

        return $params;
    }

    /**
     * Get the command in the command path
     *
     * @return array
     */
    private function getScanCommands()
    {
        $files = Finder::create()
                            ->in($this->app['path.commands'])
                            ->name('*Command.php');
        $commands = [];

        foreach ($files as $file) {
            $commands = array_merge($commands, $this->getCommandFromSource($file));
        }

        return $commands;
    }

    /**
     * Get the command name with command. append on start
     *
     * @param  string $name
     * @return string
     */
    private function getCommandName($name)
    {
        if (!starts_with($name, 'command.')) {
            $name = "command.$name";
        }

        return str_replace(':', '.', $name);
    }

    /**
     * Get the command class for the file
     *
     * @param  string|Symfony\Component\Finder\SplFileInfo $file
     * @return string
     */
    private function getCommandFromSource($file)
    {
        $tokens       = token_get_all($file->getContents());
        $count        = count($tokens);
        $commandClass = '';
        $namespace    = '';
        $commandName  = '';

        for ($i = 0; $i < $count; $i ++) {
            if (!$commandClass) {
                if ($tokens[$i][0] == T_NAMESPACE) {
                    for ($j = $i+1; $j < $count; ++ $j) {
                        if ($tokens[$j][0] == T_STRING) {
                            $namespace .= "\\" . $tokens[$j][1];
                        } elseif ($tokens[$j] == '{' or $tokens[$j] == ';') {
                            break;
                        }
                    }
                }
                if ($tokens[$i][0] == T_CLASS) {
                    for ($j = $i + 1; $j < $count; ++$j) {
                        if ($tokens[$j] == '{') {
                            $commandClass .= $namespace."\\".$tokens[$i+2][1];
                            break;
                        }
                    }
                }
            } else if ($tokens[$i][0] == T_PROTECTED && isset($tokens[$i + 2])) {
                $token = $tokens[$i + 2];
                if ($token[0]  == T_VARIABLE && $token[1] == '$name') {
                    for ($j = $i + 3; $j < $count; ++ $j) {
                        if ($tokens[$j][0] == T_CONSTANT_ENCAPSED_STRING) {
                            $commandName = $tokens[$j][1];
                            $commandName = trim($commandName, "'");
                            $commandName = trim($commandName, '"');
                            break;
                        } elseif ($tokens[$j] == ';') {
                            break;
                        }
                    }
                }
            }
        }

        return [$commandName => $commandClass];
    }

    /**
     * Get all commands
     *
     * @return array
     */
    private function getAllCommands()
    {

        $commands = [];

        // Merge config commands

        if ($this->config->has('commands')) {
            $commands = array_merge($commands, $this->config->get('commands'));
        }

        // Merge scan commands

        $commands = array_merge($commands, $this->getScanCommands());

        // Merge internal commands

        return array_merge($commands, $this->commands);
    }

    /**
     * Register commands in container and add artisan.start event.
     *
     * @return void
     */
    private function registerCommands()
    {

        $commands = [];

        foreach ($this->getAllCommands() as $name => $command) {
            $commands[] = $this->registerCommand($this->getCommandName($name), $command);
        }

        // To register the commands with Artisan, we will grab each of the arguments
        // passed into the method and listen for Artisan "start" event which will
        // give us the Artisan console instance which we will give commands to.

        $this->app['events']->listen('artisan.start', function ($artisan) use ($commands) {
            $artisan->resolveCommands($commands);
        });
    }

    /**
     * Extract the application name from first argument
     *
     * @return string|null
     */
    private function getAppNameFromFirstArgument()
    {
        if (count($_SERVER['argv']) >= 2) {
            $name     =  $_SERVER['argv'][1];
            unset($_SERVER['argv'][1]);

            $_SERVER['argv'] = array_values($_SERVER['argv']);
            $_SERVER['argc'] = count($_SERVER['argv']);

            return $name;
        }
    }

    /**
     * Set the custom paths
     *
     * @return void
     */
    private function useCustomPaths()
    {
        foreach ($this->config->get('paths') as $key => $path) {
            $key = $key == 'path' ? $key : 'path.' . $key;
            $this->app[$key] = realpath($this->app->basePath() . '/' . $path);
        }
    }
}
