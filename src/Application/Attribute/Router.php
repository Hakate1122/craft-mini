<?php
namespace Craft\Application\Attribute;

#[\Attribute]
/**
 * Router attribute for defining HTTP routes.
 *
 * This attribute can be used to annotate controller methods with routing information.
 */
class Router
{
    public string $method;
    public string $router;
    public bool $api;
    public ?string $name;

    /**
     * Router attribute constructor.
     *
     * This constructor initializes the router attribute with the provided values.
     *
     * @param string $method The HTTP method (GET, POST, etc.)
     * @param string $router The route path
     * @param bool $api Whether the route is for an API
     * @param string|null $name The name of the route
     */
    public function __construct(string $method = 'GET', string $router = '', bool $api = false, ?string $name = null)
    {
        $this->method = $method;
        $this->router = $router;
        $this->api = $api;
        $this->name = $name;
    }
}
