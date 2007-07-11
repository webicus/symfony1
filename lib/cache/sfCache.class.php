<?php

/*
 * This file is part of the symfony package.
 * (c) 2004-2006 Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * sfCache is an abstract class for all cache classes in symfony.
 *
 * @package    symfony
 * @subpackage cache
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @version    SVN: $Id$
 */
abstract class sfCache
{
  const OLD = 1;
  const ALL = 2;
  const SEPARATOR = ':';

  protected
    $parameterHolder = null;

  /**
   * Retrieves a new sfCache implementation instance.
   *
   * @param  string  A sfCache class name
   *
   * @return sfCache A sfCache implementation instance
   *
   * @throws <b>sfFactoryException</b> If a cache implementation instance cannot be created
   */
  public static function newInstance($class)
  {
    $object = new $class();

    if (!$object instanceof sfCache)
    {
      throw new sfFactoryException(sprintf('Class "%s" is not of the type sfCache.', $class));
    }

    return $object;
  }

  /**
   * Initializes this sfCache instance.
   *
   * @param  array An associative array of initialization parameters.
   *
   * Available parameters:
   *
   * * automaticCleaningFactor (optional): The automatic cleaning process destroy too old (for the given life time) (default value: 1000)
   *   cache files when a new cache file is written.
   *     0               => no automatic cache cleaning
   *     1               => systematic cache cleaning
   *     x (integer) > 1 => automatic cleaning randomly 1 times on x cache write
   *
   * * lifetime (optional): The default life time (default value: 86400)
   *
   * @return bool true, if initialization completes successfully, otherwise false.
   *
   * @throws <b>sfInitializationException</b> If an error occurs while initializing this sfCache instance.
   */
  public function initialize($parameters = array())
  {
    $this->parameterHolder = new sfParameterHolder();
    $this->parameterHolder->add($parameters);

    if (!$this->hasParameter('automaticCleaningFactor'))
    {
      $this->setParameter('automaticCleaningFactor', 1000);
    }

    if (!$this->hasParameter('lifetime'))
    {
      $this->setParameter('lifetime', 86400);
    }
  }

  /**
   * Gets the cache content for a given key.
   *
   * @param  string The cache key
   * @param  mixed  The default value is the key does not exist or not valid anymore
   *
   * @return mixed  The data of the cache
   */
  abstract public function get($key, $default = null);

  /**
   * Returns true if there is a cache for the given key.
   *
   * @param  string  The cache key
   *
   * @return Boolean true if the cache exists, false otherwise
   */
  abstract public function has($key);

  /**
   * Saves some data in the cache.
   *
   * @param string The cache key
   * @param mixed  The data to put in cache
   * @param int    The lifetime
   *
   * @return Boolean true if no problem
   */
  abstract public function set($key, $data, $lifetime = null);

  /**
   * Removes a content from the cache.
   *
   * @param string The cache key
   *
   * @return Boolean true if no problem
   */
  abstract public function remove($key);

  /**
   * Removes content from the cache that matches the given pattern.
   *
   * @param  string  The cache key pattern
   *
   * @return Boolean true if no problem
   *
   * @see patternToRegexp
   */
  abstract public function removePattern($pattern);

  /**
   * Cleans the cache.
   *
   * @param  string  The clean mode
   *                 sfCache::ALL: remove all keys (default)
   *                 sfCache::OLD: remove all expired keys
   *
   * @return Boolean true if no problem
   */
  abstract public function clean($mode = self::ALL);

  /**
   * Returns the timeout for the given key.
   *
   * @param string The cache key
   *
   * @return int The timeout time
   */
  abstract public function getTimeout($key);

  /**
   * Returns the last modification date of the given key.
   *
   * @param string The cache key
   *
   * @return int The last modified time
   */
  abstract public function getLastModified($key);

  /**
   * Gets many keys at once.
   *
   * @param  array An array of keys
   *
   * @return array An associative array of data from cache
   */
  public function getMany($keys)
  {
    $data = array();
    foreach ($keys as $key)
    {
      $data[$key] = $this->get($key);
    }

    return $data;
  }

  /**
   * Retrieves the parameters for the current request.
   *
   * @return sfParameterHolder The parameter holder
   */
  public function getParameterHolder()
  {
    return $this->parameterHolder;
  }

  public function getParameter($name, $default = null, $ns = null)
  {
    return $this->parameterHolder->get($name, $default, $ns);
  }

  public function hasParameter($name, $ns = null)
  {
    return $this->parameterHolder->has($name, $ns);
  }

  public function setParameter($name, $value, $ns = null)
  {
    return $this->parameterHolder->set($name, $value, $ns);
  }

  /**
   * Converts a pattern to a regular expression.
   *
   * A pattern can use some special characters:
   *
   *  - * Matches a namespace (foo:*:bar)
   *  - ** Matches one or more namespaces (foo:**:bar)
   *
   * @param  string A pattern
   *
   * @return string A regular expression
   */
  protected function patternToRegexp($pattern)
  {
    $regexp = '#^'.str_replace('#', '\\#', preg_quote($pattern)).'$#';

    // **
    $regexp = str_replace('\\*\\*', '.+?', $regexp);

    // *
    $regexp = str_replace('\\*', '[^'.preg_quote(sfCache::SEPARATOR).']+', $regexp);

    return $regexp;
  }
}
