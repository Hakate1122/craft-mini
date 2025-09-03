<?php
namespace Craft\Application;

use Closure;
use Exception;

/**
 * Router Class to handle HTTP routing in the application.
 *
 * This class handles the routing of HTTP requests to their respective handlers.
 * It supports standard routes and API routes, with middleware for both.
 */
#region Router
#[\Attribute]
class Router
{
    /** @var array $routes Stores the standard routes with their handlers and middleware. */
    private $routes = [];
    /** @var array $apiRoutes Stores the API routes with their handlers and middleware. */
    private $apiRoutes = [];
    /** @var array $globalMiddleware Stores global middleware applied to all routes. */
    private $globalMiddleware = [];
    /** @var array $globalApiMiddleware Stores global middleware applied to all API routes. */
    private $globalApiMiddleware = [];
    /** @var mixed $request Stores the request object or array. */
    private $request;
    /** @var array $staticRoutes Static routes for static calls. */
    private static $staticRoutes = [
        'GET' => [],
        'POST' => [],
        'PUT' => [],
        'DELETE' => [],
    ];
    /** @var array $staticApiRoutes Static API routes for static calls. */
    private static $staticApiRoutes = [
        'GET' => [],
        'POST' => [],
        'PUT' => [],
        'DELETE' => [],
    ];

    /** @var array $routeNames Map tên route => [method, path] */
    private static $routeNames = [];

    /** @var array|null $lastRegisteredRoute Stores the method and path of the last registered route. */
    private static $lastRegisteredRoute = null;

    /** @var array $groupContext Stores the current group context for grouped routes */
    private static $groupContext = [
        'prefix' => '',
        'name' => '',
        'middleware' => [],
        'namePrefix' => '',
    ];

    /** @var array $groupStack Stores the group stack for nested groups */
    private static $groupStack = [];

    /** @var mixed $router Stores the router object or array. */
    private $router;
    private $method;
    private $name;
    private $api;

    /**
     * Router constructor.
     * @param mixed $request The request object or array.
     * @param mixed $router The router object or array.
     * @param mixed $method The HTTP method (GET, POST, etc.).
     * @param mixed $name The name of the route.
     * @param mixed $api Whether the route is an API route.
     */
    public function __construct($request = null, $router = null, $method = null, $name = null, $api = false)
    {
        $this->request = $request;
        $this->router = $router;
        $this->method = $method;
        $this->name = $name;
        $this->api = $api;
    }
    public function addMiddleware(callable $middleware): void
    {
        $this->globalMiddleware[] = $middleware;
    }

    public function addApiMiddleware(callable $middleware): void
    {
        $this->globalApiMiddleware[] = $middleware;
    }

    /**
     * Scan controller classes for Router attribute and auto-register routes
     * @param array $controllerClasses
     * @return void
     */
    public static function scanControllerAttributes(array $controllerClasses)
    {
        foreach ($controllerClasses as $controllerClass) {
            if (!class_exists($controllerClass))
                continue;
            $refClass = new \ReflectionClass($controllerClass);
            foreach ($refClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                $attributes = $method->getAttributes(self::class);
                foreach ($attributes as $attr) {
                    $args = $attr->getArguments();
                    $httpMethod = strtoupper($args['method'] ?? 'GET');
                    $route = $args['router'] ?? null;
                    $routeName = $args['name'] ?? null;
                    $isApi = !empty($args['api']);

                    if ($route) {
                        $routeObj = null;
                        if ($isApi) {
                            // Đăng ký vào API routes
                            switch ($httpMethod) {
                                case 'GET':
                                    $routeObj = self::apiGet($route, [$controllerClass, $method->getName()]);
                                    break;
                                case 'POST':
                                    $routeObj = self::apiPost($route, [$controllerClass, $method->getName()]);
                                    break;
                                case 'PUT':
                                    $routeObj = self::apiPut($route, [$controllerClass, $method->getName()]);
                                    break;
                                case 'DELETE':
                                    $routeObj = self::apiDelete($route, [$controllerClass, $method->getName()]);
                                    break;
                                default:
                                    $routeObj = self::apiGet($route, [$controllerClass, $method->getName()]);
                            }
                        } else {
                            // Đăng ký vào standard routes
                            switch ($httpMethod) {
                                case 'GET':
                                    $routeObj = self::get($route, [$controllerClass, $method->getName()]);
                                    break;
                                case 'POST':
                                    $routeObj = self::post($route, [$controllerClass, $method->getName()]);
                                    break;
                                case 'PUT':
                                    $routeObj = self::put($route, [$controllerClass, $method->getName()]);
                                    break;
                                case 'DELETE':
                                    $routeObj = self::delete($route, [$controllerClass, $method->getName()]);
                                    break;
                                default:
                                    $routeObj = self::get($route, [$controllerClass, $method->getName()]);
                            }
                        }
                        if ($routeName && method_exists($routeObj, 'name')) {
                            $routeObj->name($routeName);
                        }
                    }
                }
            }
        }
    }

    // --- STATIC ROUTE METHODS ---
    public static function get(string $path, $handler, array $middleware = [])
    {
        // Kiểm tra xem có đang trong group context không
        if (!empty(self::$groupContext['prefix'])) {
            $fullPath = self::buildGroupPath($path);
            $fullMiddleware = array_merge(self::$groupContext['middleware'], $middleware);

            self::$staticRoutes['GET'][$fullPath] = ['handler' => $handler, 'middleware' => $fullMiddleware];
            self::$lastRegisteredRoute = ['method' => 'GET', 'path' => $fullPath];

            // Tự động thêm name nếu có group name
            $groupName = self::$groupContext['name'];
            if ($groupName) {
                $routeName = $groupName . str_replace('/', '.', trim($path, '/'));
                self::$routeNames[$routeName] = ['GET', $fullPath];
            }
        } else {
            self::$staticRoutes['GET'][$path] = ['handler' => $handler, 'middleware' => $middleware];
            self::$lastRegisteredRoute = ['method' => 'GET', 'path' => $path];
        }

        return new static();
    }
    public static function post(string $path, $handler, array $middleware = [])
    {
        // Kiểm tra xem có đang trong group context không
        if (!empty(self::$groupContext['prefix'])) {
            $fullPath = self::buildGroupPath($path);
            $fullMiddleware = array_merge(self::$groupContext['middleware'], $middleware);

            self::$staticRoutes['POST'][$fullPath] = ['handler' => $handler, 'middleware' => $fullMiddleware];
            self::$lastRegisteredRoute = ['method' => 'POST', 'path' => $fullPath];

            // Tự động thêm name nếu có group name
            $groupName = self::$groupContext['name'];
            if ($groupName) {
                $routeName = $groupName . str_replace('/', '.', trim($path, '/'));
                self::$routeNames[$routeName] = ['POST', $fullPath];
            }
        } else {
            self::$staticRoutes['POST'][$path] = ['handler' => $handler, 'middleware' => $middleware];
            self::$lastRegisteredRoute = ['method' => 'POST', 'path' => $path];
        }

        return new static();
    }
    public static function put(string $path, $handler, array $middleware = [])
    {
        // Kiểm tra xem có đang trong group context không
        if (!empty(self::$groupContext['prefix'])) {
            $fullPath = self::buildGroupPath($path);
            $fullMiddleware = array_merge(self::$groupContext['middleware'], $middleware);

            self::$staticRoutes['PUT'][$fullPath] = ['handler' => $handler, 'middleware' => $fullMiddleware];
            self::$lastRegisteredRoute = ['method' => 'PUT', 'path' => $fullPath];

            // Tự động thêm name nếu có group name
            $groupName = self::$groupContext['name'];
            if ($groupName) {
                $routeName = $groupName . str_replace('/', '.', trim($path, '/'));
                self::$routeNames[$routeName] = ['PUT', $fullPath];
            }
        } else {
            self::$staticRoutes['PUT'][$path] = ['handler' => $handler, 'middleware' => $middleware];
            self::$lastRegisteredRoute = ['method' => 'PUT', 'path' => $path];
        }

        return new static();
    }
    public static function delete(string $path, $handler, array $middleware = [])
    {
        // Kiểm tra xem có đang trong group context không
        if (!empty(self::$groupContext['prefix'])) {
            $fullPath = self::buildGroupPath($path);
            $fullMiddleware = array_merge(self::$groupContext['middleware'], $middleware);

            self::$staticRoutes['DELETE'][$fullPath] = ['handler' => $handler, 'middleware' => $fullMiddleware];
            self::$lastRegisteredRoute = ['method' => 'DELETE', 'path' => $fullPath];

            // Tự động thêm name nếu có group name
            $groupName = self::$groupContext['name'];
            if ($groupName) {
                $routeName = $groupName . str_replace('/', '.', trim($path, '/'));
                self::$routeNames[$routeName] = ['DELETE', $fullPath];
            }
        } else {
            self::$staticRoutes['DELETE'][$path] = ['handler' => $handler, 'middleware' => $middleware];
            self::$lastRegisteredRoute = ['method' => 'DELETE', 'path' => $path];
        }

        return new static();
    }

    // --- STATIC API ROUTE METHODS ---
    public static function apiGet(string $path, $handler, array $middleware = [])
    {
        $path = self::normalizeApiPathStatic($path);
        self::$staticApiRoutes['GET'][$path] = ['handler' => $handler, 'middleware' => $middleware];
        self::$lastRegisteredRoute = ['method' => 'GET', 'path' => $path, 'api' => true];
        return new static();
    }
    public static function apiPost(string $path, $handler, array $middleware = [])
    {
        $path = self::normalizeApiPathStatic($path);
        self::$staticApiRoutes['POST'][$path] = ['handler' => $handler, 'middleware' => $middleware];
        self::$lastRegisteredRoute = ['method' => 'POST', 'path' => $path, 'api' => true];
        return new static();
    }
    public static function apiPut(string $path, $handler, array $middleware = [])
    {
        $path = self::normalizeApiPathStatic($path);
        self::$staticApiRoutes['PUT'][$path] = ['handler' => $handler, 'middleware' => $middleware];
        self::$lastRegisteredRoute = ['method' => 'PUT', 'path' => $path, 'api' => true];
        return new static();
    }
    public static function apiDelete(string $path, $handler, array $middleware = [])
    {
        $path = self::normalizeApiPathStatic($path);
        self::$staticApiRoutes['DELETE'][$path] = ['handler' => $handler, 'middleware' => $middleware];
        self::$lastRegisteredRoute = ['method' => 'DELETE', 'path' => $path, 'api' => true];
        return new static();
    }

    // --- GROUP ROUTE METHODS ---
    /**
     * Group routes under a common prefix.
     * @param string $prefix The prefix to group routes under.
     * @return static
     */
    public static function group(string $prefix)
    {
        // Lưu context hiện tại vào stack
        self::$groupStack[] = self::$groupContext;

        // Cập nhật context mới
        self::$groupContext['prefix'] = rtrim(self::$groupContext['prefix'], '/') . '/' . ltrim($prefix, '/');
        self::$groupContext['name'] = '';
        self::$groupContext['middleware'] = [];
        self::$groupContext['namePrefix'] = '';

        return new static();
    }

    /**
     * Đặt prefix cho tên route trong group
     * @param string $prefix
     * @return static
     */
    public static function namePrefix(string $prefix): self
    {
        self::$groupContext['namePrefix'] = $prefix;
        return new static();
    }
    public static function name(string $name): self
    {
        $prefix = self::$groupContext['namePrefix'] ?? '';
        $fullName = $prefix . $name;
        if (self::$lastRegisteredRoute) {
            $method = self::$lastRegisteredRoute['method'];
            $path = self::$lastRegisteredRoute['path'];
            self::$routeNames[$fullName] = [$method, $path];
        } else {
            self::$groupContext['name'] = $fullName;
        }
        return new static();
    }
    public static function middleware($middleware)
    {
        if (is_string($middleware)) {
            self::$groupContext['middleware'] = [$middleware];
        } elseif (is_array($middleware)) {
            self::$groupContext['middleware'] = $middleware;
        }
        return new static();
    }
    /**
     * Execute a callback within the context of the current route group.
     * @param callable $callback The callback to execute.
     */
    public static function action(callable $callback)
    {
        $callback();

        // Khôi phục context cũ
        self::$groupContext = array_pop(self::$groupStack);

        return new static();
    }

    // --- HELPER FUNCTIONS FOR GROUPED ROUTES ---
    private static function buildGroupPath(string $path): string
    {
        $prefix = self::$groupContext['prefix'];
        $fullPath = rtrim($prefix, '/') . '/' . ltrim($path, '/');
        return '/' . ltrim($fullPath, '/');
    }
    public static function getGroupName(): ?string
    {
        return self::$groupContext['name'] ?: null;
    }
    private static function normalizeApiPathStatic(string $path): string
    {
        $trimmed = trim($path, '/');
        if (strpos($trimmed, 'api/') === 0) {
            return '/' . $trimmed;
        }
        if ($trimmed === 'api') {
            return '/api/';
        }
        return '/api/' . $trimmed;
    }

    /**
     * Run the route handler after merging static routes.
     */
    public function runInstance(): void
    {
        // Merge static routes
        foreach (self::$staticRoutes as $method => $routes) {
            if (!isset($this->routes[$method])) {
                $this->routes[$method] = [];
            }
            $this->routes[$method] = array_merge($this->routes[$method], $routes);
        }
        // Merge static API routes
        foreach (self::$staticApiRoutes as $method => $routes) {
            if (!isset($this->apiRoutes[$method])) {
                $this->apiRoutes[$method] = [];
            }
            $this->apiRoutes[$method] = array_merge($this->apiRoutes[$method], $routes);
        }

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);

        $scriptName = dirname($_SERVER['SCRIPT_NAME']);
        if ($scriptName !== '/' && strpos($uri, $scriptName) === 0) {
            $uri = substr($uri, strlen($scriptName));
        }
        $uri = rtrim($uri, '/') ?: '/';

        if (isset($this->routes[$method][$uri])) {
            $this->callStandardHandler($this->routes[$method][$uri], []);
            return;
        }

        foreach ($this->routes[$method] ?? [] as $route => $routeData) {
            $pattern = '#^' . preg_replace('#\{([^/]+)\}#', '([^/]+)', rtrim($route, '/')) . '$#';
            if (preg_match($pattern, $uri, $matches)) {
                array_shift($matches);
                $this->callStandardHandler($routeData, $matches);
                return;
            }
        }

        $allowedMethods = [];
        foreach ($this->routes as $m => $routesByMethod) {
            if (isset($routesByMethod[$uri])) {
                $allowedMethods[] = $m;
            }
            foreach ($routesByMethod as $route => $routeData) {
                $pattern = '#^' . preg_replace('#\{([^/]+)\}#', '([^/]+)', rtrim($route, '/')) . '$#';
                if (preg_match($pattern, $uri)) {
                    $allowedMethods[] = $m;
                }
            }
        }

        if ($this->handleApiRoute($method, $uri)) {
            return;
        }

        foreach ($this->apiRoutes as $m => $routesByMethod) {
            if (isset($routesByMethod[$uri])) {
                $allowedMethods[] = $m;
            }
            foreach ($routesByMethod as $route => $routeData) {
                $pattern = '#^' . preg_replace('#\{([^/]+)\}#', '([^/]+)', rtrim($route, '/')) . '$#';
                if (preg_match($pattern, $uri)) {
                    $allowedMethods[] = $m;
                }
            }
        }

        if (!empty($allowedMethods)) {
            http_response_code(405);
            header('Allow: ' . implode(', ', array_unique($allowedMethods)));
            throw new Exception("405 Method Not Allowed: The requested URL " . $uri . " exists but does not support $method method.Allowed: " . implode(', ', array_unique($allowedMethods)) . "");
        }

        $matchedParamRoute = false;
        foreach ($this->routes[$method] ?? [] as $route => $routeData) {
            // Lấy prefix trước {param}
            $routePrefix = preg_replace('#\{([^/]+)\}#', '', rtrim($route, '/'));
            if ($routePrefix !== '' && (rtrim($uri, '/') === rtrim($routePrefix, '/'))) {
                $matchedParamRoute = $route;
                break;
            }
        }
        if ($matchedParamRoute) {
            http_response_code(400);
            throw new Exception("400 Bad Request: Missing required parameter for route <b>$matchedParamRoute</b>.");
        }

        // Kiểm tra thiếu param cho API route
        $matchedApiParamRoute = false;
        foreach ($this->apiRoutes[$method] ?? [] as $route => $routeData) {
            $routePrefix = preg_replace('#\{([^/]+)\}#', '', rtrim($route, '/'));
            if ($routePrefix !== '' && (rtrim($uri, '/') === rtrim($routePrefix, '/'))) {
                $matchedApiParamRoute = $route;
                break;
            }
        }
        if ($matchedApiParamRoute) {
            http_response_code(400);
            header('HTTP/1.1 404 Not Found');
            throw new Exception("400 Bad Request: Missing required parameter for API route $matchedApiParamRoute.");
        }

        http_response_code(404);

        header('HTTP/1.1 404 Not Found');
    }
    /**
     * Run the route handler.
     * This method initializes the application and starts the routing process.
     */
    public static function run()
    {
        (new static())->runInstance();
    }
    /**
     * Call the standard route handler after running middleware.
     * @param array $routeData Contains the handler and middleware for the route.
     * @param array $params Optional parameters to pass to the handler.
     * @return void
     */
    private function callStandardHandler(array $routeData, array $params = []): void
    {
        $handler = $routeData['handler'];
        $middleware = array_merge($this->globalMiddleware, $routeData['middleware']);

        $context = ['params' => $params, 'request' => $this->request];
        foreach ($middleware as $mw) {
            // Check if middleware is a string (registered middleware name)
            if (is_string($mw)) {
                $result = \Craft\Application\Middleware::run($mw, $context);
                if ($result === false) {
                    return; // Middleware blocked the request
                }
            } else {
                // Direct callable middleware
                $result = call_user_func($mw, $context);
                if ($result !== null) {
                    echo $result;
                    return;
                }
            }
        }

        if (is_array($handler)) {
            [$class, $method] = $handler;
            if (!class_exists($class)) {
                http_response_code(500);
                throw new Exception("500 Internal Server Error: Class not found: $class");
            }
            $instance = new $class();
            if (!method_exists($instance, $method)) {
                http_response_code(500);
                throw new Exception("500 Internal Server Error: Method $method() not found in class $class");
            }
            $ref = new \ReflectionMethod($instance, $method);
            $args = $params;
            if ($ref->getNumberOfParameters() > count($params)) {
                $args[] = $this->request;
            }
            echo call_user_func_array([$instance, $method], $args);
            return;
        } elseif (is_callable($handler)) {
            $ref = new \ReflectionFunction(Closure::fromCallable($handler));
            $args = $params;
            if ($ref->getNumberOfParameters() > count($params)) {
                $args[] = $this->request;
            }
            echo call_user_func_array($handler, $args);
            return;
        }
        http_response_code(500);
        throw new Exception("Invalid route handler");
    }
    private function handleApiRoute(string $method, string $uri): bool
    {
        if (isset($this->apiRoutes[$method][$uri])) {
            return $this->respondApi($this->apiRoutes[$method][$uri], []);
        }

        foreach ($this->apiRoutes[$method] ?? [] as $route => $routeData) {
            $pattern = '#^' . preg_replace('#\{([^/]+)\}#', '([^/]+)', rtrim($route, '/')) . '$#';
            if (preg_match($pattern, $uri, $matches)) {
                array_shift($matches);
                return $this->respondApi($routeData, $matches);
            }
        }

        return false;
    }
    private function respondApi(array $routeData, array $params = []): bool
    {
        $handler = $routeData['handler'];
        $middleware = array_merge($this->globalApiMiddleware, $routeData['middleware']);

        $context = ['params' => $params, 'request' => $this->request];
        foreach ($middleware as $mw) {
            $result = call_user_func($mw, $context);
            if ($result !== null) {
                header('Content-Type: application/json');
                http_response_code(is_array($result) && isset($result['code']) ? $result['code'] : 400);
                echo json_encode($result, JSON_UNESCAPED_UNICODE);
                return true;
            }
        }

        $result = null;
        if (is_array($handler)) {
            [$class, $method] = $handler;
            if (class_exists($class)) {
                $instance = new $class();
                $ref = new \ReflectionMethod($instance, $method);
                $args = $params;
                if ($ref->getNumberOfParameters() > count($params)) {
                    $args[] = $this->request;
                }
                $result = call_user_func_array([$instance, $method], $args);
            }
        } elseif (is_callable($handler)) {
            $ref = new \ReflectionFunction(Closure::fromCallable($handler));
            $args = $params;
            if ($ref->getNumberOfParameters() > count($params)) {
                $args[] = $this->request;
            }
            $result = call_user_func_array($handler, $args);
        }

        if ($result !== null) {
            header('Content-Type: application/json');
            if (is_array($result) && isset($result['code'])) {
                http_response_code($result['code']);
                unset($result['code']);
            } else {
                http_response_code(200);
            }
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            return true;
        }

        http_response_code(404);
        echo json_encode(['error' => 'Invalid API route']);
        return true;
    }
    protected function getAllRoute(): array
    {
        $all = [];
        foreach (['GET', 'POST', 'PUT', 'DELETE'] as $method) {
            $all[$method] = array_merge(
                self::$staticRoutes[$method] ?? [],
                $this->routes[$method] ?? []
            );
            // Thêm API routes vào mảng với key là 'api'
            $all['api_' . $method] = array_merge(
                self::$staticApiRoutes[$method] ?? [],
                $this->apiRoutes[$method] ?? []
            );
        }
        return $all;
    }

    public static function route(string $name, array $params = []): ?string
    {
        if (!isset(self::$routeNames[$name]))
            return null;
        [$method, $path] = self::$routeNames[$name];
        if ($params) {
            foreach ($params as $val) {
                $path = preg_replace('/\{[^}]+\}/', $val, $path, 1);
            }
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
        if ($basePath === '/')
            $basePath = '';

        if ($path !== '/' && strpos($path, '/') !== 0) {
            $path = '/' . $path;
        }

        $url = $scheme . '://' . $host . $basePath . $path;

        $url = preg_replace('#(?<!:)//+#', '/', $url);

        return $url;
    }
    public static function runTest($testValue = '1')
    {
        $results = [];
        $allRoutes = (new static())->getAllRoute();
        foreach ($allRoutes as $method => $routes) {
            $isApi = strpos($method, 'api_') === 0;
            $realMethod = $isApi ? substr($method, 4) : $method;
            foreach ($routes as $path => $routeData) {
                $testPath = preg_replace('/\{[^}]+\}/', $testValue, $path);

                $_SERVER['REQUEST_METHOD'] = $realMethod;
                $_SERVER['REQUEST_URI'] = $testPath;
                ob_start();
                try {
                    if ($isApi) {
                        // Chạy kiểm tra cho API route
                        (new static())->handleApiRoute($realMethod, $testPath);
                    } else {
                        (new static())->runInstance();
                    }
                    $output = ob_get_clean();
                    $pass = strpos($output, '404') === false && strpos($output, '500') === false;
                    $results[] = [
                        'method' => $realMethod . ($isApi ? ' (API)' : ''),
                        'path' => $path,
                        'test_path' => $testPath,
                        'result' => $pass ? 'PASS' : 'FAIL',
                        'icon' => $pass ? '✅' : '❌',
                        'output' => $output
                    ];
                } catch (\Throwable $e) {
                    ob_end_clean();
                    $results[] = [
                        'method' => $realMethod . ($isApi ? ' (API)' : ''),
                        'path' => $path,
                        'test_path' => $testPath,
                        'result' => 'FAIL',
                        'icon' => '❌',
                        'output' => $e->getMessage()
                    ];
                }
            }
        }
        // In kết quả dạng bảng HTML
        echo '<table border="1" cellpadding="6" style="border-collapse:collapse;margin:20px auto;min-width:700px">';
        echo '<thead><tr>
                <th>Result</th>
                <th>Method</th>
                <th>Test Path</th>
                <th>Route</th>
                <th>Output</th>
            </tr></thead><tbody>';
        foreach ($results as $r) {
            echo '<tr style="background:' . ($r['result'] === 'PASS' ? '#eaffea' : '#ffeaea') . '">';
            echo '<td style="text-align:center">' . $r['icon'] . ' ' . $r['result'] . '</td>';
            echo '<td>' . htmlspecialchars($r['method']) . '</td>';
            echo '<td>' . htmlspecialchars($r['test_path']) . '</td>';
            echo '<td>' . htmlspecialchars($r['path']) . '</td>';
            echo '<td><pre style="margin:0;font-size:13px">' . htmlspecialchars($r['output']) . '</pre></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }
}
#endregion