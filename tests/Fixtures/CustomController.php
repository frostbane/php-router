<?php

namespace PHPRouter\Test\Fixtures;

final class CustomController
{
    /**
     * @var string
     */
    private $config;

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function index()
    {
        return "index $this->config";
    }

    public function home()
    {
        echo "home $this->config";
    }
}
