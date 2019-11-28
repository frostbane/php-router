<?php

namespace PHPRouter\Test\Fixtures;

final class InvokableController
{
    public $message;

    public function __invoke($id, $user)
    {
        echo "$this->message $id:$user";
    }
}
