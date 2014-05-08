<?php

namespace Lewis\OAuth2\Server\Storage;

use Closure;
use Predis\Client;
use League\OAuth2\Server\Storage\Adapter;

class RedisAdapter extends Adapter
{
    /**
     * Create a new redis adpater instance.
     * 
     * @param  \Predis\Client  $redis
     * @return void
     */
    public function __construct(Client $redis)
    {
        $this->redis = $redis;
    }

    /**
     * Get a value from the Redis store.
     * 
     * @param  string  $key
     * @param  string  $table
     * @return mixed
     */
    public function getValue($key, $table)
    {
        $key = $this->prefix($key, $table);

        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        if (! $value = $this->redis->get($key)) {
            return false;
        }

        return $this->cache[$key] = (is_string($value) && $decoded = json_decode($value, true)) ? $decoded : $value;
    }

    /**
     * Set a value in the Redis store.
     * 
     * @param  string  $key
     * @param  string  $table
     * @param  mixed  $value
     * @return bool
     */
    public function setValue($key, $table, $value)
    {
        $key = $this->prefix($key, $table);

        $this->cache[$key] = $value;

        return $this->redis->set($key, $this->prepareValue($value));
    }

    /**
     * Push a value onto a set.
     * 
     * @param  string  $key
     * @param  string  $table
     * @param  mixed  $value
     * @return int
     */
    public function pushSet($key, $table, $value)
    {
        $key = $this->prefix($key, $table);

        if (! isset($this->cache[$key])) {
            $this->cache[$key] = [];
        }

        array_push($this->cache[$key], $value);

        return $this->redis->sadd($key, $this->prepareValue($value));
    }

    /**
     * Get a set from the Redis store.
     * 
     * @param  string  $key
     * @param  string  $table
     * @return array
     */
    public function getSet($key, $table)
    {
        $key = $this->prefix($key, $table);

        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $list = $this->redis->smembers($key);

        // We'll spin through each item on the array and attempt to decode
        // any JSON so that we get the proper array representations.
        return $this->cache[$key] = array_map(function ($item) {
            if (is_string($item) && $decoded = json_decode($item, true)) {
                return $decoded;
            }

            return $item;
        }, $list);
    }

    /**
     * Delete a value from a set.
     * 
     * @param  string  $key
     * @param  string  $table
     * @param  string  $value
     * @return int
     */
    public function deleteSet($key, $table, $value)
    {
        $key = $this->prefix($key, $table);

        if (isset($this->cache[$key]) && ($cacheKey = array_search($value, $this->cache[$key])) !== false) {
            unset($this->cache[$key][$cacheKey]);
        }

        return $this->redis->srem($key, $value);
    }

    /**
     * Delete a key from the Redis store.
     * 
     * @param  string  $key
     * @param  string  $table
     * @return int
     */
    public function deleteKey($key, $table)
    {
        $key = $this->prefix($key, $table);

        if (isset($this->cache[$key])) {
            unset($this->cache[$key]);
        }

        return $this->redis->del($key);
    }

    /**
     * Get a matching set member by using a callback to run the
     * comparison. If the callback returns a non-null response
     * then that response is assumed to be a match.
     * 
     * @param  string  $key
     * @param  string  $table
     * @param  \Closure  $callback
     * @return mixed
     */
    public function getMatchingMember($key, $table, Closure $callback)
    {
        foreach ($this->getSet($key, $table) as $member) {
            if ($response = $callback($member)) {
                return $response;
            }
        }
    }

    /**
     * Increment the value of a key by one.
     * 
     * @param  string  $table
     * @return int
     */
    public function increment($table)
    {
        $key = $this->prefix(null, $table);

        return $this->redis->incr($key);
    }

    /**
     * Prepare a value for storage in Redis.
     * 
     * @param  mixed  $value
     * @return string
     */
    protected function prepareValue($value)
    {
        if (is_array($value)) {
            $value = json_encode($value);
        }

        return $value;
    }

    /**
     * Prefix a key with its table.
     * 
     * @param  string  $key
     * @param  string  $table
     * @return string
     */
    protected function prefix($key, $table)
    {
        $table = str_replace('_', ':', $table);

        return trim("{$table}:{$key}", ':');
    }
}
