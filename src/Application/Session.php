<?php
namespace Craft\Application;

/**
 * Session management class
 *
 * This class provides methods to start, get, set, and destroy session variables.
 * It is used to manage user sessions in the application.
 */
class Session
{
    /**
     * Start the session
     */
    public static function start()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Get a session variable
     *
     * @param string $key
     * @return mixed
     */
    public static function get($key)
    {
        return $_SESSION[$key] ?? null;
    }

    /**
     * Set a session variable
     *
     * @param string $key
     * @param mixed $value
     */
    public static function set($key, $value)
    {
        if (!session($key)) {
            $_SESSION[$key] = $value;
        }
    }

    /**
     * Destroy the session
     */
    public static function destroy()
    {
        session_unset();
        session_destroy();
    }
}