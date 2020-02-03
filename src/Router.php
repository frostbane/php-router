<?php

namespace PHPRouter;

use Exception;
use Fig\Http\Message\RequestMethodInterface;

/**
 * Routing class to match request URL's against given routes and map
 * them to a controller action.
 */
class Router
{
    /**
     * RouteCollection that holds all Route objects
     *
     * @var RouteCollection
     */
    private $routes = array();

    /**
     * Array to store named routes in, used for reverse routing.
     * @var array
     */
    private $namedRoutes = array();

    /**
     * The base REQUEST_URI. Gets prepended to all route url's.
     * @var string
     */
    private $basePath = '';

    /**
     * @param RouteCollection $collection
     */
    public function __construct(RouteCollection $collection)
    {
        $this->routes = $collection;

        foreach ($this->routes->all() as $route) {
            $name = $route->getName();
            if (null !== $name) {
                $this->namedRoutes[$name] = $route;
            }
        }
    }

    private function getRequestUrlAndMethod()
    {
        $requestMethod = (
            isset($_POST['_method']) &&
            ($_method = strtoupper($_POST['_method'])) &&
            in_array($_method, array(RequestMethodInterface::METHOD_PUT, RequestMethodInterface::METHOD_DELETE), true)
        ) ? $_method : $_SERVER['REQUEST_METHOD'];

        $requestUrl = $_SERVER['REQUEST_URI'];

        // strip GET variables from URL
        if (($pos = strpos($requestUrl, '?')) !== false) {
            $requestUrl = substr($requestUrl, 0, $pos);
        }

        return array(
            $requestMethod,
            $requestUrl,
        );
    }

    /**
     * Set the base _url - gets prepended to all route _url's.
     *
     * @param $basePath
     */
    public function setBasePath($basePath)
    {
        $this->basePath = rtrim($basePath, '/');
    }

    /**
     * @return Route
     */
    private function getMatchingRequestRoute()
    {
        list($requestMethod, $requestUrl) = $this->getRequestUrlAndMethod();

        /** @var Route $route */
        list($route,) = $this->findRoute($requestUrl, $requestMethod);

        return $route;
    }

    /**
     * check if the request has a matching route.
     *
     * does not check if the class exists or not
     *
     * @see Router::requestHasValidRoute()
     */
    public function requestHasRoute()
    {
        $route = $this->getMatchingRequestRoute();

        /** @var Route $route */
        return $route !== null;
    }

    /**
     * check if the request has a valid route
     *
     * @return bool
     */
    public function requestHasValidRoute()
    {
        $route = $this->getMatchingRequestRoute();

        $controller = $route->getValidController();

        return $controller !== null;
    }

    /**
     * Matches the current request against mapped routes
     */
    public function matchCurrentRequest()
    {
        list($requestMethod, $requestUrl) = $this->getRequestUrlAndMethod();

        return $this->match($requestUrl, $requestMethod);
    }

    private function findRoute($requestUrl, $requestMethod)
    {
        $currentDir = dirname($_SERVER['SCRIPT_NAME']);
        $foundRoute = null;
        $params     = array();

        // must be unit testing
        if ($currentDir === "." || "..") {
            $currentDir = "";
        }

        $allRoutes = $this->routes->all();

        // reverse search, last registered route will overwrite
        // previously registered route
        for ($i = count($allRoutes) - 1; $i >= 0; $i--) {
            $routes = $allRoutes[$i];

            // compare server request method with route's allowed http methods
            if (!in_array($requestMethod, (array)$routes->getMethods(), true)) {
                continue;
            }

            if ('/' !== $currentDir) {
                $requestUrl = str_replace($currentDir, '', $requestUrl);
            }

            $route   = rtrim($routes->getRegex(), '/');
            $pattern = '@^' . preg_quote($this->basePath) . $route . '/?$@i';

            if (!preg_match($pattern, $requestUrl, $matches)) {
                continue;
            }

            if (preg_match_all('/:([\w-%]+)/', $routes->getUrl(), $argument_keys)) {
                // grab array with matches
                $argument_keys = $argument_keys[1];

                // check arguments number

                if (count($argument_keys) !== (count($matches) - 1)) {
                    continue;
                }

                // loop trough parameter names, store matching value in $params array
                foreach ($argument_keys as $key => $name) {
                    if (isset($matches[$key + 1])) {
                        $params[$name] = $matches[$key + 1];
                    }
                }
            }

            $foundRoute = $routes;
            break;
        }

        /** @var Route $foundRoute */
        /** @var array $params */
        return array(
            $foundRoute,
            $params,
        );
    }

    public function getRequestRoute()
    {
        list($requestMethod, $requestUrl) = $this->getRequestUrlAndMethod();

        return $this->getRoute($requestUrl, $requestMethod);
    }

    public function getRoute($requestUrl, $requestMethod = RequestMethodInterface::METHOD_GET)
    {
        /** @var Route $route */
        list($route, $params) = $this->findRoute($requestUrl, $requestMethod);

        if ($route !== null) {
            $route->setParameters($params);

            return $route;
        } else {
            return null;
        }
    }

    /**
     * Match given request _url and request method and see if a route
     * has been defined for it If so, return route's target If called
     * multiple times
     *
     * @param string $requestUrl
     * @param string $requestMethod
     *
     * @return null|Route
     */
    public function match($requestUrl, $requestMethod = RequestMethodInterface::METHOD_GET)
    {
        /** @var Route $route */
        $route = $this->getRoute($requestUrl, $requestMethod);

        if ($route !== null) {
            return $route->dispatch();
        } else {
            throw new \DomainException("No route found for $requestMethod '$requestUrl'");
        }
    }

    /**
     * Reverse route a named route
     *
     * @param       $routeName
     * @param array $params Optional array of parameters to use in URL
     *
     * @throws Exception
     *
     * @return string The url to the route
     */
    public function generate($routeName, array $params = array())
    {
        // Check if route exists
        if (!isset($this->namedRoutes[$routeName])) {
            throw new Exception("No route with the name $routeName has been found.");
        }

        /** @var \PHPRouter\Route $route */
        $route = $this->namedRoutes[$routeName];
        $url   = $route->getUrl();

        // replace route url with given parameters
        if ($params && preg_match_all('/:(\w+)/', $url, $param_keys)) {
            // grab array with matches
            $param_keys = $param_keys[1];

            // loop trough parameter names, store matching value in $params array
            foreach ($param_keys as $key) {
                if (isset($params[$key])) {
                    $url = preg_replace('/:' . preg_quote($key, '/') . '/', $params[$key], $url, 1);
                }
            }
        }

        return $url;
    }

    /**
     * Create routes by array, and return a Router object
     *
     * @param array $config provide by Config::loadFromFile()
     *
     * @return Router
     */
    public static function parseConfig(array $config)
    {
        $collection = new RouteCollection();
        foreach ($config['routes'] as $name => $route) {
            $collection->attachRoute(new Route($route[0], array(
                '_controller' => str_replace('.', '::', $route[1]),
                'methods'     => $route[2],
                'name'        => $name,
            )));
        }

        $router = new Router($collection);
        if (isset($config['base_path'])) {
            $router->setBasePath($config['base_path']);
        }

        return $router;
    }
}
