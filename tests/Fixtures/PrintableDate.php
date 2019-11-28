<?php

namespace PHPRouter\Test\Fixtures;

final class PrintableDate
{
    public function __construct()
    {
    }

    public function __toString()
    {
        return "to string";
    }
}
