<?php

namespace PHPRouterTest\Test;

use PHPRouter\Route;
use PHPRouter\Test\Fixtures\InvokableController;
use PHPRouter\Test\Fixtures\PrintableDate;
use PHPUnit_Framework_TestCase;

class RouteTest extends PHPUnit_Framework_TestCase
{
    private $routeUsing__invoke;
    private $routeWithParameters;
    private $routeInvalid;
    private $routeWithPrivateMethod;

    protected function setUp()
    {
        $this->routeUsing__invoke = new Route(
            '/home/:user/:id',
            array(
                '_controller' => '\PHPRouter\Test\Fixtures\InvokableController',
                'methods'     => array(
                    'GET',
                ),
                'filters'     => array(
                    ":user" => "([A-Z][a-z]+)",
                    ":id"   => "([1-9]\\d)",
                ),
            )
        );

        $this->routeWithPrivateMethod = new Route(
            '/home/:user/:id',
            array(
                '_controller' => '\PHPRouter\Test\Fixtures\SomeController::privateMethod',
                'methods'     => array(
                    'GET',
                ),
            )
        );

        $this->routeWithParameters = new Route(
            '/page/:page_id/:page_size',
            array(
                '_controller' => '\PHPRouter\Test\Fixtures\SomeController::page',
                'methods'     => array(
                    'GET',
                ),
                'target'      => 'thisIsAString',
                'name'        => 'page',
            )
        );

        $this->routeInvalid = new Route(
            '/test',
            array(
                '_controller' => '\PHPRouter\Test\Fixtures\TestController::page',
                'methods'     => array(
                    'GET',
                ),
                'target'      => 'thisIsAString',
                'name'        => 'page',
            )
        );
    }

    public function testGetUrl()
    {
        $this->assertEquals('/page/:page_id/:page_size', $this->routeWithParameters->getUrl());
    }

    public function testSetUrl()
    {
        $this->routeWithParameters->setUrl('/pages/:page_name/');
        $this->assertEquals('/pages/:page_name/', $this->routeWithParameters->getUrl());

        $this->routeWithParameters->setUrl('/pages/:page_name');
        $this->assertEquals('/pages/:page_name/', $this->routeWithParameters->getUrl());
    }

    public function testGetMethods()
    {
        $this->assertEquals(array('GET'), $this->routeWithParameters->getMethods());
    }

    public function testSetMethods()
    {
        $this->routeWithParameters->setMethods(array('POST'));
        $this->assertEquals(array('POST'), $this->routeWithParameters->getMethods());

        $this->routeWithParameters->setMethods(array('GET', 'POST', 'PUT', 'DELETE'));
        $this->assertEquals(array('GET', 'POST', 'PUT', 'DELETE'), $this->routeWithParameters->getMethods());
    }

    public function testGetTarget()
    {
        $this->assertEquals('thisIsAString', $this->routeWithParameters->getTarget());
    }

    public function testSetTarget()
    {
        $this->routeWithParameters->setTarget('ThisIsAnotherString');
        $this->assertEquals('ThisIsAnotherString', $this->routeWithParameters->getTarget());
    }

    public function testGetName()
    {
        $this->assertEquals('page', $this->routeWithParameters->getName());
    }

    public function testSetName()
    {
        $this->routeWithParameters->setName('pageroute');
        $this->assertEquals('pageroute', $this->routeWithParameters->getName());
    }

    public function testGetAction()
    {
        $this->assertEquals('page', $this->routeWithParameters->getAction());
        $this->assertEquals(null, $this->routeUsing__invoke->getAction());
    }

    public function testGetClass()
    {
        $this->assertEquals('\PHPRouter\Test\Fixtures\SomeController', $this->routeWithParameters->getClass());
        $this->assertEquals('\PHPRouter\Test\Fixtures\InvokableController', $this->routeUsing__invoke->getClass());
    }

    public function testGetValidController()
    {
        $this->assertEquals("\PHPRouter\Test\Fixtures\SomeController::page",
                            $this->routeWithParameters->getValidController());
        $this->assertNull($this->routeInvalid->getValidController());
    }

    public function testGetValidController_privateMethod()
    {
        $this->assertNull($this->routeWithPrivateMethod->getValidController());
    }

    public function testSetGetParameters()
    {
        $param = array("page_id" => 123);
        $this->routeWithParameters->setParameters($param);

        $this->assertEquals($param, $this->routeWithParameters->getParameters());
    }

    public function testGetRegexNoFilters()
    {
        $filters = $this->routeWithParameters->getFilters();
        $regex   = $this->routeWithParameters->getRegex();

        $this->assertEmpty($filters);
        $this->assertEquals("/page/([\\w-%]+)/([\\w-%]+)", $regex);
    }

    public function testGetFilterRegex()
    {
        $filters = array(
            ":page_id"   => "([\\d+])",
            ":page_size" => "([1-9]\\d)",
        );

        $this->routeWithParameters->setFilters($filters);

        $this->assertEquals("/page/{$filters[':page_id']}/{$filters[':page_size']}",
                            $this->routeWithParameters->getRegex());
        $this->assertEquals("/home/([A-Z][a-z]+)/([1-9]\\d)",
                            $this->routeUsing__invoke->getRegex());
    }

    public function testDispatch__invoke()
    {
        $message = "welcome";
        $param   = array(
            "user" => "akane",
            "id"   => 1,
        );

        $this->routeUsing__invoke->setParameters($param);

        $this->assertEquals(" {$param['id']}:{$param['user']}", $this->routeUsing__invoke->dispatch());

        $instance = new InvokableController();

        $instance->message = $message;

        $this->assertEquals("$message {$param['id']}:{$param['user']}", $this->routeUsing__invoke->dispatch($instance));
    }

    public function testDispatch()
    {
        $buffer = $this->routeWithParameters->dispatch();

        $this->assertEquals("", $buffer);
    }

    public function testParameterSorting()
    {
        $route = new Route(
            '/',
            array(
                '_controller' => '\PHPRouter\Test\Fixtures\SomeController::parameterSort',
                'methods'     => array(
                    'GET',
                ),
            )
        );

        $params = array(
            "group_name" => "fi",
            "id"         => 1,
            "page_name"  => "profile",
            "user"       => "akane",
            "tag_name"   => "m",
            "flag"       => 2,
            "admin"      => 0,
        );

        // route is expected to arrange the params according to the function arguments
        // and fill the missing ones with the remaining unmatched parameters
        $expected = implode(",", array(1, "fi", "akane", "profile", "m", 2, 0));

        $route->setParameters($params);
        $this->assertEquals($expected, $route->dispatch());
    }

    public function testCanBeEchoed()
    {
        $route = new Route("", array());

        $rexl = new \ReflectionMethod($route, "canBeEchoed");

        $rexl->setAccessible(true);

        $provider = array(
            array(true, true, "1"),
            array(true, false, ""),
            array(true, 1, "1"),
            array(true, 0, "0"),
            array(true, 1.23, "1.23"),
            array(true, 0.45, "0.45"),
            array(true, "", ""),
            array(true, new PrintableDate(), "to string"),
            array(false, null),
            array(false, array()),
            array(false, date_create()),
        );

        foreach ($provider as $case) {
            $expected = $case[0];
            $item     = $case[1];

            $result = $rexl->invoke($route, $item);

            $this->assertEquals($expected, $result);

            if ($expected) {
                ob_start();
                echo $item;

                $strval = ob_get_clean();

                $this->assertEquals($strval, $case[2]);
            }
        }
    }
}
