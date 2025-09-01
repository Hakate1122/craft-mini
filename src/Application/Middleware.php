<?php
namespace Craft\Application;

/**
 * Middleware Class
 * 
 * This class handles middleware functionality for the application.
 * It provides a simple way to add middleware to routes and groups.
 */

class Middleware
{
    /**
     * @var array $middlewares Stores registered middlewares
     */
    private static $middlewares = [];

    /**
     * Register a middleware
     * @param string $name Middleware name
     * @param callable $callback Middleware callback
     */
    public static function register(string $name, callable $callback): void
    {
        self::$middlewares[$name] = $callback;
    }

    /**
     * Get a middleware by name
     * @param string $name Middleware name
     * @return callable|null
     */
    public static function get(string $name): ?callable
    {
        return self::$middlewares[$name] ?? null;
    }

    /**
     * Check if middleware exists
     * @param string $name Middleware name
     * @return bool
     */
    public static function exists(string $name): bool
    {
        return isset(self::$middlewares[$name]);
    }

    /**
     * Run a middleware
     * @param string $name Middleware name
     * @param array $context Context data
     * @return mixed
     */
    public static function run(string $name, array $context = [])
    {
        if (self::exists($name)) {
            return call_user_func(self::$middlewares[$name], $context);
        }
        return null;
    }
}