<?php
namespace Craft\Application;

use Exception;

/**
 * App Class
 *
 * This class initializes and boot the application environment, sets up error handling,
 * loads environment variables, and configures reporting for errors, exceptions,
 * and runtime issues. It also handles CLI reporting if the application is run
 * from the command line.
 */
#region App
class App
{
    /**
     * Version of Craft Framework.
     * @var string
     */
    public const version = '1.0.3-dev';

    /**
     * Application environment
     * @var string
     */
    private static $environment = 'production';

    /**
     * Application debug mode
     * @var bool
     */
    private static $debug = false;

    /**
     * Initialize application configuration
     * 
     * @return void
     */
    public static function initializeConfig()
    {
        self::$environment = env('APP_ENVIRONMENT', 'production');
        self::$debug = env('APP_DEBUG', 'false');

        // Validate environment
        if (!in_array(self::$environment, ['local', 'development', 'staging', 'production'])) {
            self::$environment = 'production';
        }

        // Security: Disable debug in production
        if (self::$environment === 'production') {
            self::$debug = false;
        }
    }

    /**
     * Get current environment
     * 
     * @return string
     */
    public static function environment(): string
    {
        return self::$environment;
    }

    /**
     * Check if application is in debug mode
     * 
     * @return bool
     */
    public static function isDebug(): bool
    {
        return self::$debug;
    }

    /**
     * Check if application is in production
     * 
     * @return bool
     */
    public static function isProduction(): bool
    {
        return self::$environment === 'production';
    }

    /**
     * Set security headers for web requests
     * 
     * @return void
     */
    private static function setSecurityHeaders()
    {
        if (headers_sent()) {
            return; // Headers already sent
        }

        // Security headers
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // Content Security Policy (basic)
        if (self::isProduction()) {
            header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline';");
        }

        // Remove server information
        header_remove('X-Powered-By');
    }

    /**
     * Validate session configuration
     * 
     * @return void
     */
    private static function validateSessionConfig()
    {
        // Set secure session configuration
        if (self::isProduction()) {
            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_secure', '1');
            ini_set('session.use_strict_mode', '1');
            ini_set('session.cookie_samesite', 'Strict');
        }

        // Validate session save path
        $sessionPath = ini_get('session.save_path');
        if ($sessionPath && !is_writable($sessionPath)) {
            throw new Exception('Session save path is not writable: ' . $sessionPath);
        }
    }

    /**
     * Validate service configuration
     * 
     * @return void
     */
    private static function validateServiceConfig()
    {
        // Validate required environment variables for services
        $requiredVars = ['APP_NAME', 'APP_TIMEZONE'];
        foreach ($requiredVars as $var) {
            if (!env($var)) {
                throw new Exception("Required environment variable missing: {$var}");
            }
        }
    }

    /**
     * Initialize error reporting with validation
     * 
     * @param string $logDir
     * @return void
     */
    private static function initializeErrorReporting(string $logDir)
    {
        // Validate log files can be created
        $logFiles = [
            'parse.log',
            'exception.log',
            'error.log',
            'runtime.log'
        ];

        foreach ($logFiles as $logFile) {
            $fullPath = $logDir . $logFile;
            if (!is_writable(dirname($fullPath))) {
                throw new Exception("Cannot write to log file: {$fullPath}");
            }
        }

        // Initialize error reporting
        \Craft\Reports\Parse::sign(true, $logDir . 'parse.log');
        \Craft\Reports\Exception::sign(true, $logDir . 'exception.log');
        \Craft\Reports\Error::sign(true, $logDir . 'error.log');
        \Craft\Reports\Runtime::sign(true, $logDir . 'runtime.log');
    }

    /**
     * Validate application health
     * 
     * @return array
     */
    public static function healthCheck(): array
    {
        $health = [
            'status' => 'healthy',
            'environment' => self::$environment,
            'debug' => self::$debug,
            'version' => self::version,
            'timestamp' => date('Y-m-d H:i:s'),
            'checks' => []
        ];

        // Check required directories
        $requiredDirs = [
            'logs' => ROOT_DIR . 'public/logs/',
            'vendor' => ROOT_DIR . 'vendor/',
            'app' => ROOT_DIR . 'app/'
        ];

        foreach ($requiredDirs as $name => $path) {
            $health['checks'][$name] = [
                'status' => is_dir($path) && is_readable($path) ? 'ok' : 'error',
                'path' => $path
            ];
        }

        // Check if any checks failed
        foreach ($health['checks'] as $check) {
            if ($check['status'] === 'error') {
                $health['status'] = 'unhealthy';
                break;
            }
        }

        return $health;
    }

    /**
     * Load environment variables from .env file
     * 
     * @return void
     */
    private static function loadEnvironmentVariables()
    {
        $envFile = ROOT_DIR . '.env';
        if (file_exists($envFile)) {
            try {
                $env = new \Datahihi1\TinyEnv\TinyEnv(ROOT_DIR);
                $env->load();
            } catch (Exception $e) {
                if (self::$environment !== 'production') {
                    throw $e;
                }
            }
        }
    }

    /**
     * Configure error reporting based on environment
     * 
     * @return void
     */
    private static function configureErrorReporting()
    {
        if (self::$environment === 'production') {
            error_reporting(0);
            ini_set('display_errors', '0');
            ini_set('display_startup_errors', '0');
            ini_set('log_errors', '1');
            ini_set('error_log', ROOT_DIR . 'public/logs/php_errors.log');
        } else {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
            ini_set('display_startup_errors', '1');
        }
    }

    /**
     * Configure timezone from environment variable
     * 
     * @return void
     */
    private static function configureTimezone()
    {
        $timezone = env('APP_TIMEZONE', 'UTC');
        if (!in_array($timezone, \DateTimeZone::listIdentifiers())) {
            $timezone = 'UTC';
        }
        date_default_timezone_set($timezone);
    }

    /**
     * Validate database configuration (optional)
     * 
     * @return void
     */
    private static function validateDatabaseConfig()
    {
        if (env('DB_HOST') && env('DB_NAME')) {
            $host = env('DB_HOST');
            $dbname = env('DB_NAME');
            $username = env('DB_USER');
            $password = env('DB_PASS');
            if (!$host || !$dbname || !$username) {
                throw new Exception('Incomplete database configuration');
            }
            // Optionally, test connection here if needed
            // $testConnection = new \mysqli($host, $username, $password, $dbname);
            // $testConnection->close();
        }
    }

    /**
     * Initializes the routing configuration.
     * 
     * @return void
     * @throws Exception if the route configuration file is not found or invalid
     */
    public static function initializeRoute()
    {
        // Initialize routing configuration
        $routeConfigPath = ROOT_DIR . 'app/Router/web.php';
        if (file_exists($routeConfigPath)) {
            require $routeConfigPath;
        } else {
            throw new Exception("Route configuration file not found: " . $routeConfigPath);
        }
    }

    /**
     * Initializes the web environment.
     *
     * @param string|null $logDir The directory where log files will be stored.
     * 
     * @return void
     */
    public static function initializeWeb(?string $logDir = null)
    {
        try {
            // Initialize configuration
            self::initializeConfig();

            // Load environment variables
            self::loadEnvironmentVariables();

            // Configure error reporting
            self::configureErrorReporting();

            // Configure timezone
            self::configureTimezone();

            // Validate log directory
            if ($logDir && !is_dir($logDir)) {
                if (!mkdir($logDir, 0755, true)) {
                    throw new Exception("Failed to create log directory: {$logDir}");
                }
            }

            // Validate log directory is writable
            if ($logDir && !is_writable($logDir)) {
                throw new Exception("Log directory is not writable: {$logDir}");
            }

            // Validate session configuration (moved here)
            self::validateSessionConfig();

            // Start session with security
            Session::start();

            // Initialize error reporting with validation
            if ($logDir) {
                self::initializeErrorReporting($logDir);
            }

            // Validate required environment variables for services
            self::validateServiceConfig();

            // Validate database config (optional)
            self::validateDatabaseConfig();

        } catch (Exception $e) {
            if (self::isDebug()) {
                dump('Application initialization failed: ' . $e->getMessage());
            }
            http_response_code(500);
            die('Application initialization failed');
        }
    }

    /**
     * Boots the web application.
     * 
     * @return void
     */
    public static function bootWeb()
    {
        // Initialize configuration
        self::initializeConfig();

        // // Set security headers
        // self::setSecurityHeaders();

        // Start run route handler
        self::initializeRoute();
    }
}
#endregion