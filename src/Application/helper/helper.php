
<?php

if (!function_exists('old')) {
    /**
     * Get old input value from the previous request.
     * @param string $key
     * @param mixed $default
     * @return mixed|null
     */
    function old($key, $default = null)
    {
        if (isset($GLOBALS['old'][$key])) {
            return $GLOBALS['old'][$key];
        }
        if (isset($GLOBALS['old']) && is_array($GLOBALS['old']) && array_key_exists($key, $GLOBALS['old'])) {
            return $GLOBALS['old'][$key];
        }
        return $default;
    }
}

if (!function_exists('resource')) {
    /**
     * Get the URL for a resource file (located in resource/).
     * 
     * @param string $path
     * @return string
     */
    function resource($path)
    {
        return \Craft\Application\View::resource($path);
    }
}

if (!function_exists('source')) {
    /**
     * Get the URL for a source file (located in public/source).
     * 
     * @param string $path
     * @return string
     */
    function source($path = '')
    {
        $baseUrl = getBaseUrl();

        $host = $_SERVER['HTTP_HOST'] ?? '';
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $path = ltrim(str_replace(['..', '\\'], '', $path), '/');

        if (strpos($baseUrl, '/public/') !== false) {
            return $baseUrl . 'source/' . $path;
        }

        if (
            preg_match('/^([a-zA-Z0-9\-\.]+)(:\d+)?$/', $host) &&
            (strpos($scriptName, '/public/') === false)
        ) {
            return $baseUrl . 'source/' . $path;
        }

        return $baseUrl . 'source/' . $path;
    }
}

if (!function_exists('redirect')) {
    /**
     * Redirect helper: 
     * - redirect()->route('name') : Redirect to a named route.
     * - redirect($url) : Redirect to a specific URL.
     * @param string|null $url URL to redirect to.
     * @return object|void
     */
    function redirect($url = null) {
        if ($url !== null) {
            header('Location: ' . $url);
            exit;
        }
        return new class {
            /**
             * Redirect to a named route.
             */
            public function route($name, $params = []) {
                $url = route($name, $params);
                header('Location: ' . $url);
                exit;
            }
            /**
             * Redirect to a specific URL.
             */
            public function to($url) {
                header('Location: ' . $url);
                exit;
            }
        };
    }
}

if (!function_exists('route')) {
    /**
     * Generate a URL for a named route.
     * 
     * @param string $name The route name.
     * @param array $params The route parameters.
     * @return string|null
     */
    function route($name, $params = [])
    {
        return \Craft\Application\Router::route($name, $params);
    }
}

if (!function_exists('session')) {
    /**
     * Helper function for session get/set.
     * - session(): get all session variables.
     * - session($key): get session value.
     * - session($key, $value): set session value.
     *
     * @param string|null $key The session key.
     * @param mixed|null $value The session value.
     * @return mixed|null
     */
    function session($key = null, $value = null)
    {

        if (is_null($key) && is_null($value)) {
            return $_SESSION;
        }

        if (!is_null($key) && is_null($value)) {
            return \Craft\Application\Session::get($key);
        }

        if (!is_null($key) && !is_null($value)) {
            \Craft\Application\Session::set($key, $value);
            return null;
        }

        return null;
    }
}

if (!function_exists('getBaseUrl')) {
    /**
     * Get the base URL of the application.
     * 
     * @return string
     */
    function getBaseUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];
        $scriptName = $_SERVER['SCRIPT_NAME'];
        $basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/') . '/';
        return $scheme . "://" . $host . $basePath;
    }
}
