<?php namespace Illuminate\Foundation;

use Illuminate\Console\Application as ConsoleApplication;

class Artisan {

	/**
	 * The application instance.
	 *
	 * @var \Illuminate\Foundation\Application
	 */
	protected $app;

	/**
	 * The Artisan console instance.
	 *
	 * @var  \Illuminate\Console\Application
	 */
	protected $artisan;

	/**
	 * Create a new Artisan command runner instance.
	 *
	 * @param  \Illuminate\Foundation\Application  $app
	 * @return void
	 */
	public function __construct(Application $app)
	{
		$this->app = $app;
	}

	/**
	 * Get the Artisan console instance.
	 *
	 * @return \Illuminate\Console\Application
	 */
	protected function getArtisan()
	{
		if ( ! is_null($this->artisan)) return $this->artisan;

		$this->app->loadDeferredProviders();

		$this->artisan = ConsoleApplication::make($this->app);

		return $this->artisan->boot();
	}

	/**
	 * Dynamically pass all missing methods to console Artisan.
	 *
	 * @param  string  $method
	 * @param  array   $parameters
	 * @return mixed
	 */
	public function __call($method, $parameters)
	{
		return call_user_func_array(array($this->getArtisan(), $method), $parameters);
	}

	public static function make($paths, $environment = array(

			'local' => array('your-machine-name'),

		))
	{
		/*
		|--------------------------------------------------------------------------
		| Setup Patchwork UTF-8 Handling
		|--------------------------------------------------------------------------
		|
		| The Patchwork library provides solid handling of UTF-8 strings as well
		| as provides replacements for all mb_* and iconv type functions that
		| are not available by default in PHP. We'll setup this stuff here.
		|
		*/

		\Patchwork\Utf8\Bootup::initMbstring();

		/*
		|--------------------------------------------------------------------------
		| Create The Application
		|--------------------------------------------------------------------------
		|
		| The first thing we will do is create a new Laravel application instance
		| which serves as the "glue" for all the components of Laravel, and is
		| the IoC container for the system binding all of the various parts.
		|
		*/

		$app = new Application;

		/*
		|--------------------------------------------------------------------------
		| Detect The Application Environment
		|--------------------------------------------------------------------------
		|
		| Laravel takes a dead simple approach to your application environments
		| so you can just specify a machine name for the host that matches a
		| given environment, then we will automatically detect it for you.
		|
		*/

		$env = $app->detectEnvironment($environment);

		/*
		|--------------------------------------------------------------------------
		| Bind Paths
		|--------------------------------------------------------------------------
		|
		| Here we are binding the paths configured in paths.php to the app. You
		| should not be changing these here. If you need to change these you
		| may do so within the paths.php file and they will be bound here.
		|
		*/

		if (!is_array($paths)) {
			$paths = require $paths;
		}

		$app->bindInstallPaths($paths);

		/*
		|--------------------------------------------------------------------------
		| Load The Application
		|--------------------------------------------------------------------------
		|
		| Here we will load this Illuminate application. We will keep this in a
		| separate location so we can isolate the creation of an application
		| from the actual running of the application with a given request.
		|
		*/

		require $app->getBootstrapFile();

		/*
		|--------------------------------------------------------------------------
		| Return The Artisan
		|--------------------------------------------------------------------------
		|
		| This script returns the artisan instance.
		|
		*/
		return new static($app);
	}

	public function shutdown($status)
	{
		$this->app->shutdown();

		exit($status);
	}
}
