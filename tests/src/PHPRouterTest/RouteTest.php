<?php

namespace PHPRouterTest\Test;

use PHPRouter\Route;
use PHPRouter\Test\Fixtures\InvokableController;
use PHPUnit_Framework_TestCase;

class RouteTest extends PHPUnit_Framework_TestCase
{
    private $routeUsing__invoke;
    private $routeWithParameters;
    private $routeInvalid;

    protected function setUp()
    {
        $this->routeUsing__invoke = new Route(
            '/home/:user/:id',
            array(
                '_controller' => '\PHPRouter\Test\Fixtures\InvokableController',
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

    public function testSetGetParameters()
    {
        $param = array("page_id" => 123);
        $this->routeWithParameters->setParameters($param);

        $this->assertEquals($param, $this->routeWithParameters->getParameters());
    }

    public function testGetRegex()
    {
        $regex = $this->routeWithParameters->getRegex();

        $this->assertEquals("/page/([\\w-%]+)/([\\w-%]+)", $regex);
    }

    public function testGetFilterRegex()
    {
        $filters = array(
            ":user" => "([A-Z][a-z]+)",
            ":id"   => "([1-9]\\d)",
        );

        $this->routeUsing__invoke->setFilters($filters);

        $regex = $this->routeUsing__invoke->getRegex();

        $this->assertEquals("/home/([A-Z][a-z]+)/([1-9]\\d)", $regex);
    }

    public function testDispatch()
    {
        $message = "welcome";
        $param   = array(
            "id"   => 1,
            "user" => "akane",
        );

        $this->routeUsing__invoke->setParameters($param);

        $this->assertEquals(" {$param['id']}:{$param['user']}", $this->routeUsing__invoke->dispatch());

        $instance = new InvokableController();

        $instance->message = $message;

        $this->assertEquals("$message {$param['id']}:{$param['user']}", $this->routeUsing__invoke->dispatch($instance));
    }
}
