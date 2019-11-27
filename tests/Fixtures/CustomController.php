<?php

namespace PHPRouter\Test\Fixtures;

final class CustomController
{
    private $config;

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function index()
    {
    }
}
