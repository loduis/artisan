<?php namespace Illuminate\Foundation;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use Illuminate\Events\EventServiceProvider;
use Illuminate\Routing\RoutingServiceProvider;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Illuminate\Contracts\Foundation\Application as ApplicationContract;

class Application extends Container implements ApplicationContract {

	/**
	 * The Laravel framework version.
	 *
	 * @var string
	 */
	const VERSION = '5.0-dev';

	/**
	 * The base path for the Laravel installation.
	 *
	 * @var string
	 */
	protected $basePath;

	/**
	 * Indicates if the application has been bootstrapped before.
	 *
	 * @var bool
	 */
	protected $hasBeenBootstrapped = false;

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
	 * @param  string|null  $basePath
	 * @return void
	 */
	public function __construct($basePath = null)
	{
		$this->registerBaseBindings();

		$this->registerBaseServiceProviders();

		$this->registerCoreContainerAliases();

		if ($basePath) $this->setBasePath($basePath);
	}

	/**
	 * Get the version number of the application.
	 *
	 * @return string
	 */
	public function version()
	{
		return static::VERSION;
	}

	/**
	 * Register the basic bindings into the container.
	 *
	 * @return void
	 */
	protected function registerBaseBindings()
	{
		static::setInstance($this);

		$this->instance('app', $this);

		$this->instance('Illuminate\Container\Container', $this);
	}

	/**
	 * Register all of the base service providers.
	 *
	 * @return void
	 */
	protected function registerBaseServiceProviders()
	{
		$this->register(new EventServiceProvider($this));

		$this->register(new RoutingServiceProvider($this));
	}

	/**
	 * Run the given array of bootstrap classes.
	 *
	 * @param  array  $bootstrappers
	 * @return void
	 */
	public function bootstrapWith(array $bootstrappers)
	{
		foreach ($bootstrappers as $bootstrapper)
		{
			$this->make($bootstrapper)->bootstrap($this);
		}

		$this->hasBeenBootstrapped = true;
	}

	/**
	 * Determine if the application has been bootstrapped before.
	 *
	 * @return bool
	 */
	public function hasBeenBootstrapped()
	{
		return $this->hasBeenBootstrapped;
	}

	/**
	 * Set the base path for the application.
	 *
	 * @param  string  $basePath
	 * @return $this
	 */
	public function setBasePath($basePath)
	{
		$this->basePath = $basePath;

		$this->bindPathsInContainer();

		return $this;
	}

	/**
	 * Bind all of the application paths in the container.
	 *
	 * @return $this
	 */
	protected function bindPathsInContainer()
	{
		$this->instance('path', $this->path());

		foreach (['base', 'config', 'database', 'lang', 'public', 'storage'] as $path)
		{
			$this->instance('path.'.$path, $this->{$path.'Path'}());
		}
	}

	/**
	 * Get the path to the application "app" directory.
	 *
	 * @return string
	 */
	public function path()
	{
		return $this->basePath.'/app';
	}

	/**
	 * Get the base path of the Laravel installation.
	 *
	 * @return string
	 */
	public function basePath()
	{
		return $this->basePath;
	}

	/**
	 * Get the path to the application configuration files.
	 *
	 * @return string
	 */
	public function configPath()
	{
		return $this->basePath.'/config';
	}

	/**
	 * Get the path to the database directory.
	 *
	 * @return string
	 */
	public function databasePath()
	{
		return $this->basePath.'/database';
	}

	/**
	 * Get the path to the language files.
	 *
	 * @return string
	 */
	public function langPath()
	{
		return $this->basePath.'/resources/lang';
	}

	/**
	 * Get the path to the public / web directory.
	 *
	 * @return string
	 */
	public function publicPath()
	{
		return $this->basePath.'/public';
	}

	/**
	 * Get the path to the storage directory.
	 *
	 * @return string
	 */
	public function storagePath()
	{
		return $this->basePath.'/storage';
	}

	/**
	 * Get or check the current application environment.
	 *
	 * @param  mixed
	 * @return string
	 */
	public function environment()
	{
		if (count(func_get_args()) > 0)
		{
			return in_array($this['env'], func_get_args());
		}

		return $this['env'];
	}

	/**
	 * Determine if application is in local environment.
	 *
	 * @return bool
	 */
	public function isLocal()
	{
		return $this['env'] == 'local';
	}

	/**
	 * Detect the application's current environment.
	 *
	 * @param  \Closure  $callback
	 * @return string
	 */
	public function detectEnvironment(Closure $callback)
	{
		$args = isset($_SERVER['argv']) ? $_SERVER['argv'] : null;

		return $this['env'] = (new EnvironmentDetector())->detect($callback, $args);
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
	 * Determine if we are running unit tests.
	 *
	 * @return bool
	 */
	public function runningUnitTests()
	{
		return $this['env'] == 'testing';
	}

	/**
	 * Register all of the configured providers.
	 *
	 * @return void
	 */
	public function registerConfiguredProviders()
	{
		$manifestPath = $this['path.storage'].'/framework/services.json';

		(new ProviderRepository($this, new Filesystem, $manifestPath))
		            ->load($this->config['app.providers']);
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
		if ($registered = $this->getProvider($provider) && ! $force)
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
		if ($this->booted)
		{
			$this->bootProvider($provider);
		}

		return $provider;
	}

	/**
	 * Get the registered service provider instance if it exists.
	 *
	 * @param  \Illuminate\Support\ServiceProvider|string  $provider
	 * @return \Illuminate\Support\ServiceProvider|null
	 */
	public function getProvider($provider)
	{
		$name = is_string($provider) ? $provider : get_class($provider);

		return array_first($this->serviceProviders, function($key, $value) use ($name)
		{
			return $value instanceof $name;
		});
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
				$this->bootProvider($instance);
			});
		}
	}

	/**
	 * Resolve the given type from the container.
	 *
	 * (Overriding Container::make)
	 *
	 * @param  string  $abstract
	 * @param  array   $parameters
	 * @return mixed
	 */
	public function make($abstract, $parameters = array())
	{
		$abstract = $this->getAlias($abstract);

		if (isset($this->deferredServices[$abstract]))
		{
			$this->loadDeferredProvider($abstract);
		}

		return parent::make($abstract, $parameters);
	}

	/**
	 * Determine if the given abstract type has been bound.
	 *
	 * (Overriding Container::bound)
	 *
	 * @param  string  $abstract
	 * @return bool
	 */
	public function bound($abstract)
	{
		return isset($this->deferredServices[$abstract]) || parent::bound($abstract);
	}

	/**
	 * "Extend" an abstract type in the container.
	 *
	 * (Overriding Container::extend)
	 *
	 * @param  string   $abstract
	 * @param  \Closure  $closure
	 * @return void
	 *
	 * @throws \InvalidArgumentException
	 */
	public function extend($abstract, Closure $closure)
	{
		$abstract = $this->getAlias($abstract);

		if (isset($this->deferredServices[$abstract]))
		{
			$this->loadDeferredProvider($abstract);
		}

		return parent::extend($abstract, $closure);
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

		// Once the application has booted we will also fire some "booted" callbacks
		// for any listeners that need to do work after this initial booting gets
		// finished. This is useful when ordering the boot-up processes we run.
		$this->fireAppCallbacks($this->bootingCallbacks);

		array_walk($this->serviceProviders, function($p) {
			$this->bootProvider($p);
		});

		$this->booted = true;

		$this->fireAppCallbacks($this->bootedCallbacks);
	}

	/**
	 * Boot the given service provider.
	 *
	 * @param  \Illuminate\Support\ServiceProvider  $provider
	 * @return void
	 */
	protected function bootProvider(ServiceProvider $provider)
	{
		if (method_exists($provider, 'boot'))
		{
			return $this->call([$provider, 'boot']);
		}
	}

	/**
	 * Register a new boot listener.
	 *
	 * @param  mixed  $callback
	 * @return void
	 */
	public function booting($callback)
	{
		$this->bootingCallbacks[] = $callback;
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
	 * Determine if the application routes are cached.
	 *
	 * @return bool
	 */
	public function routesAreCached()
	{
		return $this['files']->exists($this->getCachedRoutesPath());
	}

	/**
	 * Get the path to the routes cache file.
	 *
	 * @return string
	 */
	public function getCachedRoutesPath()
	{
		return $this['path.storage'].'/framework/routes.php';
	}

	/**
	 * Determine if the application routes have been scanned.
	 *
	 * @return bool
	 */
	public function routesAreScanned()
	{
		return $this['files']->exists($this->getScannedRoutesPath());
	}

	/**
	 * Get the path to the scanned routes file.
	 *
	 * @return string
	 */
	public function getScannedRoutesPath()
	{
		return $this['path.storage'].'/framework/routes.scanned.php';
	}

	/**
	 * Determine if the application events have been scanned.
	 *
	 * @return bool
	 */
	public function eventsAreScanned()
	{
		return $this['files']->exists($this->getScannedEventsPath());
	}

	/**
	 * Get the path to the scanned events file.
	 *
	 * @return string
	 */
	public function getScannedEventsPath()
	{
		return $this['path.storage'].'/framework/events.scanned.php';
	}

	/**
	 * Handle the given request and get the response.
	 *
	 * @param  \Symfony\Component\HttpFoundation\Request  $request
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	protected function run(SymfonyRequest $request)
	{
		return $this->make('Illuminate\Contracts\Http\Kernel')->run(Request::createFromBase($request));
	}

	/**
	 * Call the booting callbacks for the application.
	 *
	 * @param  array  $callbacks
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
	 * Determine if the application is currently down for maintenance.
	 *
	 * @return bool
	 */
	public function isDownForMaintenance()
	{
		return file_exists($this['config']['app.manifest'].'/down');
	}

	/**
	 * Register a maintenance mode event listener.
	 *
	 * @param  \Closure  $callback
	 * @return void
	 */
	public function down(Closure $callback)
	{
		$this['events']->listen('illuminate.app.down', $callback);
	}

	/**
	 * Get the service providers that have been loaded.
	 *
	 * @return array
	 */
	public function getLoadedProviders()
	{
		return $this->loadedProviders;
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
	 * Determine if the given service is a deferred service.
	 *
	 * @param  string  $service
	 * @return bool
	 */
	public function isDeferredService($service)
	{
		return isset($this->deferredServices[$service]);
	}

	/**
	 * Get the current application locale.
	 *
	 * @return string
	 */
	public function getLocale()
	{
		return $this['config']->get('app.locale');
	}

	/**
	 * Set the current application locale.
	 *
	 * @param  string  $locale
	 * @return void
	 */
	public function setLocale($locale)
	{
		$this['config']->set('app.locale', $locale);

		$this['translator']->setLocale($locale);

		$this['events']->fire('locale.changed', array($locale));
	}

	/**
	 * Register the core class aliases in the container.
	 *
	 * @return void
	 */
	public function registerCoreContainerAliases()
	{
		$aliases = array(
			'app'            => ['Illuminate\Foundation\Application', 'Illuminate\Contracts\Container\Container', 'Illuminate\Contracts\Foundation\Application'],
			'artisan'        => ['Illuminate\Console\Application', 'Illuminate\Contracts\Console\Application'],
			'auth'           => 'Illuminate\Auth\AuthManager',
			'auth.driver'    => ['Illuminate\Auth\Guard', 'Illuminate\Contracts\Auth\Guard'],
			'auth.password.tokens' => 'Illuminate\Auth\Passwords\TokenRepositoryInterface',
			'blade.compiler' => 'Illuminate\View\Compilers\BladeCompiler',
			'cache'          => ['Illuminate\Cache\CacheManager', 'Illuminate\Contracts\Cache\Factory'],
			'cache.store'    => ['Illuminate\Cache\Repository', 'Illuminate\Contracts\Cache\Repository'],
			'config'         => ['Illuminate\Config\Repository', 'Illuminate\Contracts\Config\Repository'],
			'cookie'         => ['Illuminate\Cookie\CookieJar', 'Illuminate\Contracts\Cookie\Factory', 'Illuminate\Contracts\Cookie\QueueingFactory'],
			'exception'      => 'Illuminate\Contracts\Exception\Handler',
			'encrypter'      => ['Illuminate\Encryption\Encrypter', 'Illuminate\Contracts\Encryption\Encrypter'],
			'db'             => 'Illuminate\Database\DatabaseManager',
			'events'         => ['Illuminate\Events\Dispatcher', 'Illuminate\Contracts\Events\Dispatcher'],
			'files'          => 'Illuminate\Filesystem\Filesystem',
			'filesystem'     => 'Illuminate\Contracts\Filesystem\Factory',
			'filesystem.disk' => 'Illuminate\Contracts\Filesystem\Filesystem',
			'filesystem.cloud' => 'Illuminate\Contracts\Filesystem\Cloud',
			'hash'           => 'Illuminate\Contracts\Hashing\Hasher',
			'translator'     => ['Illuminate\Translation\Translator', 'Symfony\Component\Translation\TranslatorInterface'],
			'log'            => ['Illuminate\Log\Writer', 'Illuminate\Contracts\Logging\Log', 'Psr\Log\LoggerInterface'],
			'mailer'         => ['Illuminate\Mail\Mailer', 'Illuminate\Contracts\Mail\Mailer', 'Illuminate\Contracts\Mail\MailQueue'],
			'paginator'      => 'Illuminate\Pagination\Factory',
			'auth.password'  => ['Illuminate\Auth\Passwords\PasswordBroker', 'Illuminate\Contracts\Auth\PasswordBroker'],
			'queue'          => ['Illuminate\Queue\QueueManager', 'Illuminate\Contracts\Queue\Factory', 'Illuminate\Contracts\Queue\Monitor'],
			'queue.connection' => 'Illuminate\Contracts\Queue\Queue',
			'redirect'       => 'Illuminate\Routing\Redirector',
			'redis'          => ['Illuminate\Redis\Database', 'Illuminate\Contracts\Redis\Database'],
			'request'        => 'Illuminate\Http\Request',
			'router'         => ['Illuminate\Routing\Router', 'Illuminate\Contracts\Routing\Registrar'],
			'session'        => 'Illuminate\Session\SessionManager',
			'session.store'  => ['Illuminate\Session\Store', 'Symfony\Component\HttpFoundation\Session\SessionInterface'],
			'url'            => ['Illuminate\Routing\UrlGenerator', 'Illuminate\Contracts\Routing\UrlGenerator'],
			'validator'      => ['Illuminate\Validation\Factory', 'Illuminate\Contracts\Validation\Factory'],
			'view'           => ['Illuminate\View\Factory', 'Illuminate\Contracts\View\Factory'],
		);

		foreach ($aliases as $key => $aliases)
		{
			foreach ((array) $aliases as $alias)
			{
				$this->alias($key, $alias);
			}
		}
	}

	/**
	 * Flush the container of all bindings and resolved instances.
	 *
	 * @return void
	 */
	public function flush()
	{
		parent::flush();

		$this->loadedProviders = [];
	}

}
