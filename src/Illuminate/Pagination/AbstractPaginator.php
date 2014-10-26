<?php namespace Illuminate\Pagination;

use Closure;
use Countable;
use ArrayAccess;
use ArrayIterator;
use JsonSerializable;
use IteratorAggregate;

abstract class AbstractPaginator {

	/**
	 * All of the items being paginated.
	 *
	 * @var \Illuminate\Support\Collection
	 */
	protected $items;

	/**
	 * The number of items to be shown per page.
	 *
	 * @var int
	 */
	protected $perPage;

	/**
	 * The current page being "viewed".
	 *
	 * @var int
	 */
	protected $currentPage;

	/**
	 * The base path to assign to all URLs.
	 *
	 * @var string
	 */
	protected $path = '/';

	/**
	 * The query parameters to add to all URLs.
	 *
	 * @var array
	 */
	protected $query = [];

	/**
	 * The URL fragment to add to all URLs.
	 *
	 * @var string|null
	 */
	protected $fragment = null;

	/**
	 * The query string variable used to store the page.
	 *
	 * @var string
	 */
	protected $pageName = 'page';

	/**
	 * The current page resolver callback.
	 *
	 * @var \Closure
	 */
	protected static $currentPathResolver;

	/**
	 * The current page resolver callback.
	 *
	 * @var \Closure
	 */
	protected static $currentPageResolver;

	/**
	 * Determine if the given value is a valid page number.
	 *
	 * @param  int  $page
	 * @return bool
	 */
	protected function isValidPageNumber($page)
	{
		return $page >= 1 && filter_var($page, FILTER_VALIDATE_INT) !== false;
	}

	/**
	 * Create a range of pagination URLs.
	 *
	 * @param  int  $start
	 * @param  int  $end
	 * @return string
	 */
	public function getUrlRange($start, $end)
	{
		$urls = [];

		for ($page = $start; $page <= $end; $page++)
		{
			$urls[$page] = $this->url($page);
		}

		return $urls;
	}

	/**
	 * Get a URL for a given page number.
	 *
	 * @param  int  $page
	 * @return string
	 */
	public function url($page)
	{
		if ($page <= 0) $page = 1;

		// If we have any extra query string key / value pairs that need to be added
		// onto the URL, we will put them in query string form and then attach it
		// to the URL. This allows for extra information like sortings storage.
		$parameters = [$this->pageName => $page];

		if (count($this->query) > 0)
		{
			$parameters = array_merge($this->query, $parameters);
		}

		return $this->path.'?'
		                .http_build_query($parameters, null, '&')
		                .$this->buildFragment();
	}

	/**
	 * Get the URL for the previous page.
	 *
	 * @return string|null
	 */
	public function previousPageUrl()
	{
		if ($this->currentPage() > 1)
		{
			return $this->url($this->currentPage() - 1);
		}
	}

	/**
	 * Get / set the URL fragment to be appended to URLs.
	 *
	 * @param  string|null  $fragment
	 * @return $this|string|null
	 */
	public function fragment($fragment = null)
	{
		if (is_null($fragment)) return $this->fragment;

		$this->fragment = $fragment;

		return $this;
	}

	/**
	 * Add a set of query string values to the paginator.
	 *
	 * @param  array|string  $key
	 * @param  string|null  $value
	 * @return $this
	 */
	public function appends($key, $value = null)
	{
		if (is_array($key)) return $this->appendArray($key);

		return $this->addQuery($key, $value);
	}

	/**
	 * Add an array of query string values.
	 *
	 * @param  array  $keys
	 * @return $this
	 */
	protected function appendArray(array $keys)
	{
		foreach ($keys as $key => $value)
		{
			$this->addQuery($key, $value);
		}

		return $this;
	}

	/**
	 * Add a query string value to the paginator.
	 *
	 * @param  string  $key
	 * @param  string  $value
	 * @return $this
	 */
	public function addQuery($key, $value)
	{
		if ($key !== $this->pageName)
		{
			$this->query[$key] = $value;
		}

		return $this;
	}

	/**
	 * Build the full fragment portion of a URL.
	 *
	 * @return string
	 */
	protected function buildFragment()
	{
		return $this->fragment ? '#'.$this->fragment : '';
	}

	/**
	 * Get the slice of items being paginated.
	 *
	 * @return array
	 */
	public function items()
	{
		return $this->items->all();
	}

	/**
	 * Get the number of the first item in the slice.
	 *
	 * @return int
	 */
	public function firstItem()
	{
		return ($this->currentPage - 1) * $this->perPage + 1;
	}

	/**
	 * Get the number of the last item in the slice.
	 *
	 * @return int
	 */
	public function lastItem()
	{
		return $this->firstItem() + count($this->items) - 1;
	}

	/**
	 * Get the number of items shown per page.
	 *
	 * @return int
	 */
	public function perPage()
	{
		return $this->perPage;
	}

	/**
	 * Get the current page.
	 *
	 * @return int
	 */
	public function currentPage()
	{
		return $this->currentPage;
	}

	/**
	 * Determine if there are enough items to split into multiple pages.
	 *
	 * @return bool
	 */
	public function hasPages()
	{
		return ! ($this->currentPage() == 1 && ! $this->hasMorePages());
	}

	/**
	 * Resolve the current request path or return the default value.
	 *
	 * @param  string  $default
	 * @return string
	 */
	public static function resolveCurrentPath($default = '/')
	{
		if (isset(static::$currentPathResolver))
		{
			return call_user_func(static::$currentPathResolver);
		}

		return $default;
	}

	/**
	 * Set the current request path resolver callback.
	 *
	 * @param  \Closure  $resolver
	 * @return void
	 */
	public static function currentPathResolver(Closure $resolver)
	{
		static::$currentPathResolver = $resolver;
	}

	/**
	 * Resolve the current page or return the default value.
	 *
	 * @param  int  $default
	 * @return int
	 */
	public static function resolveCurrentPage($default = 1)
	{
		if (isset(static::$currentPageResolver))
		{
			return call_user_func(static::$currentPageResolver);
		}

		return $default;
	}

	/**
	 * Set the current page resolver callback.
	 *
	 * @param  \Closure  $resolver
	 * @return void
	 */
	public static function currentPageResolver(Closure $resolver)
	{
		static::$currentPageResolver = $resolver;
	}

	/**
	 * Get an iterator for the items.
	 *
	 * @return \ArrayIterator
	 */
	public function getIterator()
	{
		return new ArrayIterator($this->items->all());
	}

	/**
	 * Determine if the list of items is empty or not.
	 *
	 * @return bool
	 */
	public function isEmpty()
	{
		return empty($this->items);
	}

	/**
	 * Get the number of items for the current page.
	 *
	 * @return int
	 */
	public function count()
	{
		return count($this->items);
	}

	/**
	 * Determine if the given item exists.
	 *
	 * @param  mixed  $key
	 * @return bool
	 */
	public function offsetExists($key)
	{
		return array_key_exists($key, $this->items->all());
	}

	/**
	 * Get the item at the given offset.
	 *
	 * @param  mixed  $key
	 * @return mixed
	 */
	public function offsetGet($key)
	{
		return $this->items[$key];
	}

	/**
	 * Set the item at the given offset.
	 *
	 * @param  mixed  $key
	 * @param  mixed  $value
	 * @return void
	 */
	public function offsetSet($key, $value)
	{
		$this->items[$key] = $value;
	}

	/**
	 * Unset the item at the given key.
	 *
	 * @param  mixed  $key
	 * @return void
	 */
	public function offsetUnset($key)
	{
		unset($this->items[$key]);
	}

	/**
	 * Render the contents of the paginator when casting to string.
	 *
	 * @return string
	 */
	public function __toString()
	{
		return $this->render();
	}

}
