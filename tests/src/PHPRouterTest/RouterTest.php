<?php

namespace PHPRouterTest\Test;

use PHPRouter\Config;
use PHPRouter\Route;
use PHPRouter\Router;
use PHPRouter\RouteCollection;
use PHPUnit_Framework_TestCase;

class RouterTest extends PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider matcherProvider
     *
     * @param Router $router
     * @param string $path
     * @param string $expected
     */
    public function testMatch($router, $path, $expected)
    {
        if ($expected === false) {
            $this->setExpectedException("\DomainException");
        }

        $buffer = $router->match($path);

        if ($expected === true) {
            $this->assertNotNull($buffer);
        }
    }

    public function testMatchWrongMethod()
    {
        $router = $this->getRouter();

        $this->setExpectedException("\DomainException");

        $router->match('/users', 'POST');
    }

    public function testBasePathConfigIsSettedProperly()
    {
        $router = new Router(new RouteCollection);
        $router->setBasePath('/webroot/');

        $this->assertAttributeEquals('/webroot', 'basePath', $router);
    }

    public function testMatchRouterUsingBasePath()
    {
        $collection = new RouteCollection();
        $collection->attach(new Route('/users/', array(
            '_controller' => '\PHPRouter\Test\Fixtures\SomeController::usersCreate',
            'methods'     => 'GET',
        )));

        $router = new Router($collection);
        $router->setBasePath('/localhost/webroot');

        foreach ($this->serverProvider() as $server) {
            $_SERVER = $server;
            $buffer  = $router->matchCurrentRequest();

            $this->assertNotNull($buffer);
        }
    }

    public function testMatchRouterUsingMethod()
    {
        $collection = new RouteCollection();

        $collection->attachRoute(new Route('/user', array(
            '_controller' => '\PHPRouter\Test\Fixtures\SomeController::indexAction',
            'methods'     => 'GET',
            'name'        => 'index',
        )));
        $collection->attachRoute(new Route('/user', array(
            '_controller' => '\PHPRouter\Test\Fixtures\SomeController::usersCreate',
            'methods'     => 'POST',
            'name'        => 'register',
        )));

        $router = new Router($collection);

        $_SERVER["REQUEST_URI"]    = "/user";
        $_SERVER["REQUEST_METHOD"] = "POST";

        $buffer = $router->matchCurrentRequest();

        unset($_SERVER["REQUEST_URI"]);
        unset($_SERVER["REQUEST_METHOD"]);

        $this->assertEquals("register user", $buffer);

        $_SERVER["REQUEST_URI"]    = "/user";
        $_SERVER["REQUEST_METHOD"] = "GET";

        $buffer = $router->matchCurrentRequest();

        unset($_SERVER["REQUEST_URI"]);
        unset($_SERVER["REQUEST_METHOD"]);

        $this->assertEquals("index", $buffer);
    }

    public function testGetRouteUsingMethod()
    {
        $collection = new RouteCollection();

        $collection->attachRoute(new Route('/', array(
            '_controller' => '\PHPRouter\Test\Fixtures\CustomController::index',
            'methods'     => 'GET',
            'name'        => 'default',
        )));
        $collection->attachRoute(new Route('/', array(
            '_controller' => '\PHPRouter\Test\Fixtures\CustomController::home',
            'methods'     => 'POST',
            'name'        => 'home',
        )));

        $router = new Router($collection);

        $_SERVER["REQUEST_URI"]    = "/";
        $_SERVER["REQUEST_METHOD"] = "POST";

        $route    = null;
        $hasRoute = $router->requestHasValidRoute();

        if ($hasRoute) {
            $route = $router->getRequestRoute();
        }

        unset($_SERVER["REQUEST_URI"]);
        unset($_SERVER["REQUEST_METHOD"]);

        $this->assertTrue($hasRoute);
        $this->assertNotNull($route);
        $this->assertEquals("home", $route->getAction());

        $_SERVER["REQUEST_URI"]    = "/";
        $_SERVER["REQUEST_METHOD"] = "GET";

        $route    = null;
        $hasRoute = $router->requestHasValidRoute();

        if ($hasRoute) {
            $route = $router->getRequestRoute();
        }

        unset($_SERVER["REQUEST_URI"]);
        unset($_SERVER["REQUEST_METHOD"]);

        $this->assertTrue($hasRoute);
        $this->assertNotNull($route);
        $this->assertEquals("index", $route->getAction());

    }

    private function serverProvider()
    {
        return array(
            array(
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI'    => '/localhost/webroot/users/',
                'SCRIPT_NAME'    => 'index.php',
            ),
            array(
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI'    => '/localhost/webroot/users/?foo=bar&bar=foo',
                'SCRIPT_NAME'    => 'index.php',
            ),
        );
    }

    public function testGetParamsInsideControllerMethod()
    {
        $collection = new RouteCollection();
        $route      = new Route(
            '/page/:page_id',
            array(
                '_controller' => '\PHPRouter\Test\Fixtures\SomeController::page',
                'methods'     => 'GET',
            )
        );
        $route->setFilters(array('page_id' => '([a-zA-Z]+)'), true);
        $collection->attachRoute($route);

        $router = new Router($collection);
        $this->assertEquals(
            array('page_id' => 'MySuperPage'),
            $router->getRoute('/page/MySuperPage')->getParameters()
        );
    }

    public function testParamsWithDynamicFilterMatch()
    {
        $collection = new RouteCollection();
        $route      = new Route(
            '/js/:filename.js',
            array(
                '_controller' => '\PHPRouter\Test\Fixtures\SomeController::dynamicFilterUrlMatch',
                'methods'     => 'GET',
            )
        );

        $route->setFilters(array(':filename' => '([[:alnum:]\.]+)'), true);
        $collection->attachRoute($route);

        $router = new Router($collection);

        $this->assertEquals(
            array('filename' => 'someJsFile'),
            $router->getRoute('/js/someJsFile.js')->getParameters()
        );

        $this->assertEquals(
            array('filename' => 'someJsFile.min'),
            $router->getRoute('/js/someJsFile.min.js')->getParameters()
        );

        $this->assertEquals(
            array('filename' => 'someJsFile.min.js'),
            $router->getRoute('/js/someJsFile.min.js.js')->getParameters()
        );
    }

    public function testParseConfig()
    {
        $config = Config::loadFromFile(__DIR__ . '/../../Fixtures/router.yaml');
        $router = Router::parseConfig($config);
        $this->assertAttributeEquals($config['base_path'], 'basePath', $router);
    }

    public function testGenerate()
    {
        $router = $this->getRouter();
        $this->assertSame('/users/', $router->generate('users'));
        $this->assertSame('/user/123', $router->generate('user', array('id' => 123)));
    }

    /**
     * @expectedException \Exception
     */
    public function testGenerateNotExistent()
    {
        $router = $this->getRouter();
        $this->assertSame('/notExists/', $router->generate('notThisRoute'));
    }

    /**
     * this is for controllers that have a custom constructor, or
     * controllers that need to be injected before calling dispatch
     */
    public function testMatchToCustomController()
    {
        $collection = new RouteCollection();

        $collection->attachRoute(new Route('/', array(
            '_controller' => '\PHPRouter\Test\Fixtures\CustomController::index',
            'methods'     => 'POST',
            'name'        => 'default',
        )));

        $router = new Router($collection);

        $_SERVER["REQUEST_URI"]    = "/";
        $_SERVER["REQUEST_METHOD"] = "POST";

        $route    = null;
        $hasRoute = $router->requestHasValidRoute();

        if ($hasRoute) {
            $route = $router->getRequestRoute();
        }

        unset($_SERVER["REQUEST_URI"]);
        unset($_SERVER["REQUEST_METHOD"]);

        $this->assertTrue($hasRoute);
        $this->assertNotNull($route);

        $class    = $route->getClass();
        $instance = new $class("akane");
        // in real life applications, this is when you do you Dance Instructor gayness magic

        $this->assertInstanceOf("\PHPRouter\Test\Fixtures\CustomController", $instance);

        $result = $route->dispatch($instance);

        $this->assertEquals("index akane", $result);
    }

    public function testRoutePrivate()
    {
        $collection = new RouteCollection();

        $collection->attachRoute(new Route('/', array(
            '_controller' => '\PHPRouter\Test\Fixtures\SomeController::privateMethod',
            'methods'     => 'GET',
            'name'        => 'default',
        )));

        $router = new Router($collection);

        $_SERVER["REQUEST_URI"]    = "/";
        $_SERVER["REQUEST_METHOD"] = "GET";

        $hasValidRoute = $router->requestHasValidRoute();

        unset($_SERVER["REQUEST_URI"]);
        unset($_SERVER["REQUEST_METHOD"]);

        $this->assertFalse($hasValidRoute);
    }

    /**
     * @return Router
     */
    private function getRouter()
    {
        $collection = new RouteCollection();
        $collection->attachRoute(new Route('/users/', array(
            '_controller' => '\PHPRouter\Test\Fixtures\SomeController::usersCreate',
            'methods'     => 'GET',
            'name'        => 'users',
        )));

        $collection->attachRoute(new Route('/user/:id', array(
            '_controller' => '\PHPRouter\Test\Fixtures\SomeController::user',
            'methods'     => 'GET',
            'name'        => 'user',
        )));

        $collection->attachRoute(new Route('/', array(
            '_controller' => '\PHPRouter\Test\Fixtures\SomeController::indexAction',
            'methods'     => 'GET',
            'name'        => 'index',
        )));

        return new Router($collection);
    }

    /**
     * @return mixed[][]
     */
    public function matcherProvider1()
    {
        $router = $this->getRouter();

        return array(
            array($router, '', true),
            array($router, '/', true),
            array($router, '/aaa', false),
            array($router, '/users', true),
            array($router, '/usersssss', false),
            array($router, '/user/1', true),
            array($router, '/user/%E3%81%82', true),
        );
    }

    /**
     * @return mixed[][]
     */
    public function matcherProvider2()
    {
        $router = $this->getRouter();
        $router->setBasePath('/api');

        return array(
            array($router, '', false),
            array($router, '/', false),
            array($router, '/aaa', false),
            array($router, '/users', false),
            array($router, '/user/1', false),
            array($router, '/user/%E3%81%82', false),
            array($router, '/api', true),
            array($router, '/api/aaa', false),
            array($router, '/api/users', true),
            array($router, '/api/userssss', false),
            array($router, '/api/user/1', true),
            array($router, '/api/user/%E3%81%82', true),
        );
    }

    /**
     * @return string[]
     */
    public function matcherProvider()
    {
        return array_merge($this->matcherProvider1(), $this->matcherProvider2());
    }
}
