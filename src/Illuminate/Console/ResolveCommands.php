<?php

namespace Illuminate\Console;

use Closure;
use ReflectionClass;
use ReflectionMethod;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\Finder\Finder;
use Illuminate\Console\Command\Source;

trait ResolveCommands
{
    /**
     * Register command in container
     *
     * @param  string                             $commandName
     * @param  string|\Illuminate\Console\Command $commandClass
     *
     * @return string
     */
    protected function registerCommand($commandName, $commandClass)
    {
        $this->app->singleton($commandName, function () use ($commandClass) {
            return $this->newInstanceCommand($commandClass);
        });

        return $commandName;
    }

    /**
     * Create one instance for command class
     *
     * @param  string $commandClass
     *
     * @return \Illuminate\Console\Command
     */
    protected function newInstanceCommand($commandClass)
    {
        $class = new ReflectionClass($commandClass);

        return $class->newInstanceArgs($this->resolveCommandParameters($class));
    }

    /**
     * Resolve the parameters in the constructor
     *
     * @param  ReflectionClass $class
     *
     * @return array
     *
     * @throws \InvalidArgumentException
     */
    protected function resolveCommandParameters(ReflectionClass $class)
    {
        $parameters = [];
        foreach ($class->getConstructor()->getParameters() as $parameter) {
            $paramClass = $parameter->getClass();
            if (is_null($paramClass)) {
                throw new InvalidArgumentException("The parameter: '" . $parameter->name . "' there is not valid class");
            }
            $paramClass       = $paramClass->name;
            $value            = $this->app->make($paramClass);
            $pos              = $parameter->getPosition();
            $parameters[$pos] = $value;

        }

        return $parameters;
    }

    /**
     * Get the command in the command path
     *
     * @return array
     */
    protected function getScanCommands()
    {
        $files = Finder::create()
            ->in($this->app['path.commands'])
            ->name('*Command.php');

        $commands = [];

        foreach ($files as $file) {
            if ($command  = Source::getCommand($file)) {
                $commands = array_merge($commands, $command);
            }
        }

        return $commands;
    }

    /**
     * Get the command name with command. append on start
     *
     * @param  string $name
     *
     * @return string
     */
    protected function getCommandName($name)
    {
        if (!Str::startsWith($name, 'command.')) {
            $name = "command.$name";
        }

        return str_replace(':', '.', $name);
    }

    /**
     * Get all commands
     *
     * @return array
     */
    protected function getAllCommands()
    {

        $commands = [];

        // Merge config commands

        $commands = array_merge($commands, (array) $this->config->get('commands'));

        // Merge scan commands

        $commands = array_merge($commands, $this->getScanCommands());

        // Merge internal commands

        return array_unique(array_filter(array_merge($commands, $this->defaultCommands)));
    }

    /**
     * Register commands in container and add artisan.start event.
     *
     * @return void
     */
    protected function registerCommands()
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
}
