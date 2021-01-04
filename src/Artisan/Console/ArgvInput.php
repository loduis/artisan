<?php

namespace Artisan\Console;

use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\ArgvInput as ConsoleArgvInput;

class ArgvInput extends ConsoleArgvInput
{
    private $argv = array();
    /**
     * Constructor.
     *
     * @param array           $argv       An array of parameters from the CLI (in the argv format)
     * @param InputDefinition $definition A InputDefinition instance
     */
    public function __construct(array $argv = null, InputDefinition $definition = null)
    {
        if (null === $argv) {
            $argv = $_SERVER['argv'];
        }

        $this->argv = $argv;

        // strip the application name
        array_shift($this->argv);

        parent::__construct($argv, $definition);
    }

    public function removeParameterOption($values, $onlyParams = false)
    {
        $index = $this->indexParameterOption($values, $onlyParams);
        if ($index !== false) {
            unset($this->argv[$index]);
            $this->setTokens($this->argv = array_values($this->argv));
        }
    }

    /**
     * Returns true if the raw parameters (not parsed) contain a value.
     *
     * This method is to be used to introspect the input parameters
     * before they have been validated. It must be used carefully.
     *
     * @param string|array $values     The value(s) to look for in the raw parameters (can be an array)
     * @param bool         $onlyParams Only check real parameters, skip those following an end of options (--) signal
     *
     * @return bool true if the value is contained in the raw parameters
     */
    public function indexParameterOption($values, $onlyParams = false)
    {
        $values = (array) $values;

        foreach ($this->argv as $index => $token) {
            if ($onlyParams && $token === '--') {
                return false;
            }
            foreach ($values as $value) {
                if ($token === $value || 0 === strpos($token, $value.'=')) {
                    return $index;
                }
            }
        }

        return false;
    }
}
