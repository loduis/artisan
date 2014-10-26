<?php namespace Illuminate\Contracts\Container;

use Closure;

interface Container {

	/**
	 * Determine if the given abstract type has been bound.
	 *
	 * @param  string  $abstract
	 * @return bool
	 */
	public function bound($abstract);

	/**
	 * Alias a type to a different name.
	 *
	 * @param  string  $abstract
	 * @param  string  $alias
	 * @return void
	 */
	public function alias($abstract, $alias);

	/**
	 * Assign a set of tags to a given binding.
	 *
	 * @param  string  $abstract
	 * @param  array|dynamic  $tags
	 * @return void
	 */
	public function tag($abstract, $tags);

	/**
	 * Resolve all of the bindings for a given tag.
	 *
	 * @param  array  $tag
	 * @return array
	 */
	public function tagged($tag);

	/**
	 * Register a binding with the container.
	 *
	 * @param  string|array  $abstract
	 * @param  \Closure|string|null  $concrete
	 * @param  bool  $shared
	 * @return void
	 */
	public function bind($abstract, $concrete = null, $shared = false);

	/**
	 * Register a binding if it hasn't already been registered.
	 *
	 * @param  string  $abstract
	 * @param  \Closure|string|null  $concrete
	 * @param  bool  $shared
	 * @return void
	 */
	public function bindIf($abstract, $concrete = null, $shared = false);

	/**
	 * Register a shared binding in the container.
	 *
	 * @param  string  $abstract
	 * @param  \Closure|string|null  $concrete
	 * @return void
	 */
	public function singleton($abstract, $concrete = null);

	/**
	 * "Extend" an abstract type in the container.
	 *
	 * @param  string    $abstract
	 * @param  \Closure  $closure
	 * @return void
	 *
	 * @throws \InvalidArgumentException
	 */
	public function extend($abstract, Closure $closure);

	/**
	 * Define a contextual binding.
	 *
	 * @param  string  $concrete
	 * @return \Illuminate\Contracts\Container\ContextualBindingBuilder
	 */
	public function when($concrete);

	/**
	 * Resolve the given type from the container.
	 *
	 * @param  string  $abstract
	 * @param  array   $parameters
	 * @return mixed
	 */
	public function make($abstract, $parameters = array());

	/**
	 * Call the given Closure / class@method and inject its dependencies.
	 *
	 * @param  callable|string  $callback
	 * @param  array  $parameters
	 * @param  string|null  $defaultMethod
	 * @return mixed
	 */
	public function call($callback, array $parameters = array(), $defaultMethod = null);

	/**
	 * Determine if the given abstract type has been resolved.
	 *
	 * @param  string $abstract
	 * @return bool
	 */
	public function resolved($abstract);

	/**
	 * Register a new resolving callback.
	 *
	 * @param  string    $abstract
	 * @param  \Closure  $callback
	 * @return void
	 */
	public function resolving($abstract, Closure $callback);

	/**
	 * Register a new resolving callback for all types.
	 *
	 * @param  \Closure  $callback
	 * @return void
	 */
	public function resolvingAny(Closure $callback);

}
