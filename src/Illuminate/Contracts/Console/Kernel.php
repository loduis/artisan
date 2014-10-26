<?php namespace Illuminate\Contracts\Console;

interface Kernel {

	/**
	 * Handle an incoming console command.
	 *
	 * @param  \Symfony\Component\Console\Input\InputInterface  $input
	 * @param  \Symfony\Component\Console\Output\OutputInterface  $output
	 * @return int
	 */
	public function handle($input, $output = null);

	/**
	 * Run an Artisan console command by name.
	 *
	 * @param  string  $command
	 * @param  array  $parameters
	 * @return int
	 */
	public function call($command, array $parameters = array());

	/**
	 * Get the output for the last run command.
	 *
	 * @return string
	 */
	public function output();

}
