<?php namespace Illuminate\Foundation;

use Illuminate\Config\FileLoader;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Events\EventServiceProvider;
use Illuminate\Exception\ExceptionServiceProvider;
use Illuminate\Config\FileEnvironmentVariablesLoader;
use Illuminate\Support\Contracts\ResponsePreparerInterface;


class Application extends Container implements ResponsePreparerInterface {

	/**
	 * The Laravel framework version.
	 *
	 * @var string
	 */
	const VERSION = '4.3-dev';

	/**
	 * Indicates if the application has "booted".
	 *
	 * @var bool
	 */
	protected $booted = false;

	/**
	 * The array of booting callbacks.
	 *
	 * @var array
	 */
	protected $bootingCallbacks = array();

	/**
	 * The array of booted callbacks.
	 *
	 * @var array
	 */
	protected $bootedCallbacks = array();

	/**
	 * The array of finish callbacks.
	 *
	 * @var array
	 */
	protected $finishCallbacks = array();

	/**
	 * The array of shutdown callbacks.
	 *
	 * @var array
	 */
	protected $shutdownCallbacks = array();

	/**
	 * All of the developer defined middlewares.
	 *
	 * @var array
	 */
	protected $middlewares = array();

	/**
	 * All of the registered service providers.
	 *
	 * @var array
	 */
	protected $serviceProviders = array();

	/**
	 * The names of the loaded service providers.
	 *
	 * @var array
	 */
	protected $loadedProviders = array();

	/**
	 * The deferred services and their providers.
	 *
	 * @var array
	 */
	protected $deferredServices = array();


	/**
	 * Create a new Illuminate application instance.
	 *
	 * @param  \Illuminate\Http\Request
	 * @return void
	 */
	public function __construct()
	{
		$this->registerBaseBindings();

		$this->registerBaseServiceProviders();
	}

	/**
	 * Register the basic bindings into the container.
	 *
	 * @return void
	 */
	protected function registerBaseBindings()
	{
		$this->instance('Illuminate\Container\Container', $this);
	}

	/**
	 * Register all of the base service providers.
	 *
	 * @return void
	 */
	protected function registerBaseServiceProviders()
	{
		foreach (array('Event', 'Exception') as $name)
		{
			$this->{"register{$name}Provider"}();
		}
	}

	/**
	 * Register the exception service provider.
	 *
	 * @return void
	 */
	protected function registerExceptionProvider()
	{
		$this->register(new ExceptionServiceProvider($this));
	}

	/**
	 * Register the event service provider.
	 *
	 * @return void
	 */
	protected function registerEventProvider()
	{
		$this->register(new EventServiceProvider($this));
	}

	/**
	 * Bind the installation paths to the application.
	 *
	 * @param  array  $paths
	 * @return void
	 */
	public function bindInstallPaths(array $paths)
	{
		$this->instance('path', realpath($paths['app']));

		// Here we will bind the install paths into the container as strings that can be
		// accessed from any point in the system. Each path key is prefixed with path
		// so that they have the consistent naming convention inside the container.
		foreach (array_except($paths, array('app')) as $key => $value)
		{
			$this->instance("path.{$key}", realpath($value));
		}
	}

	/**
	 * Get the application bootstrap file.
	 *
	 * @return string
	 */
	public static function getBootstrapFile()
	{
		return __DIR__.'/start.php';
	}

	/**
	 * Start the exception handling for the request.
	 *
	 * @return void
	 */
	public function startExceptionHandling()
	{
		$this['exception']->register($this->environment());

		$this['exception']->setDebug($this['config']['app.debug']);
	}

	/**
	 * Get or check the current application environment.
	 *
	 * @param  dynamic
	 * @return string
	 */
	public function environment()
	{
		if (count(func_get_args()) > 0)
		{
			return in_array($this['env'], func_get_args());
		}
		else
		{
			return $this['env'];
		}
	}

	/**
	 * Detect the application's current environment.
	 *
	 * @param  array|string  $envs
	 * @return string
	 */
	public function detectEnvironment($envs)
	{
		$args = isset($_SERVER['argv']) ? $_SERVER['argv'] : null;

		return $this['env'] = with(new EnvironmentDetector())->detect($envs, $args);
	}

	/**
	 * Determine if we are running in the console.
	 *
	 * @return bool
	 */
	public function runningInConsole()
	{
		return php_sapi_name() == 'cli';
	}

	/**
	 * Register a service provider with the application.
	 *
	 * @param  \Illuminate\Support\ServiceProvider|string  $provider
	 * @param  array  $options
	 * @param  bool   $force
	 * @return \Illuminate\Support\ServiceProvider
	 */
	public function register($provider, $options = array(), $force = false)
	{
		if ($registered = $this->getRegistered($provider) && ! $force)
									 return $registered;

		// If the given "provider" is a string, we will resolve it, passing in the
		// application instance automatically for the developer. This is simply
		// a more convenient way of specifying your service provider classes.
		if (is_string($provider))
		{
			$provider = $this->resolveProviderClass($provider);
		}

		$provider->register();

		// Once we have registered the service we will iterate through the options
		// and set each of them on the application so they will be available on
		// the actual loading of the service objects and for developer usage.
		foreach ($options as $key => $value)
		{
			$this[$key] = $value;
		}

		$this->markAsRegistered($provider);

		// If the application has already booted, we will call this boot method on
		// the provider class so it has an opportunity to do its boot logic and
		// will be ready for any usage by the developer's application logics.
		if ($this->booted) $provider->boot();

		return $provider;
	}

	/**
	 * Get the registered service provider instance if it exists.
	 *
	 * @param  \Illuminate\Support\ServiceProvider|string  $provider
	 * @return \Illuminate\Support\ServiceProvider|null
	 */
	public function getRegistered($provider)
	{
		$name = is_string($provider) ? $provider : get_class($provider);

		if (array_key_exists($name, $this->loadedProviders))
		{
			return array_first($this->serviceProviders, function($key, $value) use ($name)
			{
				return get_class($value) == $name;
			});
		}
	}

	/**
	 * Resolve a service provider instance from the class name.
	 *
	 * @param  string  $provider
	 * @return \Illuminate\Support\ServiceProvider
	 */
	public function resolveProviderClass($provider)
	{
		return new $provider($this);
	}

	/**
	 * Mark the given provider as registered.
	 *
	 * @param  \Illuminate\Support\ServiceProvider
	 * @return void
	 */
	protected function markAsRegistered($provider)
	{
		$this['events']->fire($class = get_class($provider), array($provider));

		$this->serviceProviders[] = $provider;

		$this->loadedProviders[$class] = true;
	}

	/**
	 * Load and boot all of the remaining deferred providers.
	 *
	 * @return void
	 */
	public function loadDeferredProviders()
	{
		// We will simply spin through each of the deferred providers and register each
		// one and boot them if the application has booted. This should make each of
		// the remaining services available to this application for immediate use.
		foreach ($this->deferredServices as $service => $provider)
		{
			$this->loadDeferredProvider($service);
		}

		$this->deferredServices = array();
	}

	/**
	 * Load the provider for a deferred service.
	 *
	 * @param  string  $service
	 * @return void
	 */
	protected function loadDeferredProvider($service)
	{
		$provider = $this->deferredServices[$service];

		// If the service provider has not already been loaded and registered we can
		// register it with the application and remove the service from this list
		// of deferred services, since it will already be loaded on subsequent.
		if ( ! isset($this->loadedProviders[$provider]))
		{
			$this->registerDeferredProvider($provider, $service);
		}
	}

	/**
	 * Register a deferred provider and service.
	 *
	 * @param  string  $provider
	 * @param  string  $service
	 * @return void
	 */
	public function registerDeferredProvider($provider, $service = null)
	{
		// Once the provider that provides the deferred service has been registered we
		// will remove it from our local list of the deferred services with related
		// providers so that this container does not try to resolve it out again.
		if ($service) unset($this->deferredServices[$service]);

		$this->register($instance = new $provider($this));

		if ( ! $this->booted)
		{
			$this->booting(function() use ($instance)
			{
				$instance->boot();
			});
		}
	}

	/**
	 * Register a "shutdown" callback.
	 *
	 * @param  callable  $callback
	 * @return void
	 */
	public function shutdown($callback = null)
	{
		if (is_null($callback))
		{
			$this->fireAppCallbacks($this->shutdownCallbacks);
		}
		else
		{
			$this->shutdownCallbacks[] = $callback;
		}
	}

	/**
	 * Determine if the application has booted.
	 *
	 * @return bool
	 */
	public function isBooted()
	{
		return $this->booted;
	}

	/**
	 * Boot the application's service providers.
	 *
	 * @return void
	 */
	public function boot()
	{
		if ($this->booted) return;

		array_walk($this->serviceProviders, function($p) { $p->boot(); });

		$this->bootApplication();
	}

	/**
	 * Boot the application and fire app callbacks.
	 *
	 * @return void
	 */
	protected function bootApplication()
	{
		// Once the application has booted we will also fire some "booted" callbacks
		// for any listeners that need to do work after this initial booting gets
		// finished. This is useful when ordering the boot-up processes we run.
		$this->fireAppCallbacks($this->bootingCallbacks);

		$this->booted = true;

		$this->fireAppCallbacks($this->bootedCallbacks);
	}


	/**
	 * Register a new "booted" listener.
	 *
	 * @param  mixed  $callback
	 * @return void
	 */
	public function booted($callback)
	{
		$this->bootedCallbacks[] = $callback;

		if ($this->isBooted()) $this->fireAppCallbacks(array($callback));
	}


	/**
	 * Call the booting callbacks for the application.
	 *
	 * @return void
	 */
	protected function fireAppCallbacks(array $callbacks)
	{
		foreach ($callbacks as $callback)
		{
			call_user_func($callback, $this);
		}
	}


	/**
	 * Prepare the given value as a Response object.
	 *
	 * @param  mixed  $value
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function prepareResponse($value)
	{
		if ( ! $value instanceof SymfonyResponse) $value = new Response($value);

		return $value->prepare($this['request']);
	}

	/**
	 * Determine if the application is ready for responses.
	 *
	 * @return bool
	 */
	public function readyForResponses()
	{
		return $this->booted;
	}

	/**
	 * Get the configuration loader instance.
	 *
	 * @return \Illuminate\Config\LoaderInterface
	 */
	public function getConfigLoader()
	{
		return new FileLoader(new Filesystem, $this['path'].'/config');
	}

	/**
	 * Get the environment variables loader instance.
	 *
	 * @return \Illuminate\Config\EnvironmentVariablesLoaderInterface
	 */
	public function getEnvironmentVariablesLoader()
	{
		return new FileEnvironmentVariablesLoader(new Filesystem, $this['path.base']);
	}

	/**
	 * Get the service provider repository instance.
	 *
	 * @return \Illuminate\Foundation\ProviderRepository
	 */
	public function getProviderRepository()
	{
		$manifest = $this['config']['app.manifest'];

		return new ProviderRepository(new Filesystem, $manifest);
	}

	/**
	 * Set the application's deferred services.
	 *
	 * @param  array  $services
	 * @return void
	 */
	public function setDeferredServices(array $services)
	{
		$this->deferredServices = $services;
	}



	/**
	 * Register the core class aliases in the container.
	 *
	 * @return void
	 */
	public function registerCoreContainerAliases()
	{
		$aliases = array(
			'app'            => 'Illuminate\Foundation\Application',
			'artisan'        => 'Illuminate\Console\Application',
			'config'         => 'Illuminate\Config\Repository',
			'events'         => 'Illuminate\Events\Dispatcher',
			'files'          => 'Illuminate\Filesystem\Filesystem',
			'log'            => 'Illuminate\Log\Writer',
			'queue'          => 'Illuminate\Queue\QueueManager',
		);

		foreach ($aliases as $key => $alias)
		{
			$this->alias($key, $alias);
		}
	}

	/**
	 * Dynamically access application services.
	 *
	 * @param  string  $key
	 * @return mixed
	 */
	public function __get($key)
	{
		return $this[$key];
	}

	/**
	 * Dynamically set application services.
	 *
	 * @param  string  $key
	 * @param  mixed   $value
	 * @return void
	 */
	public function __set($key, $value)
	{
		$this[$key] = $value;
	}

}
