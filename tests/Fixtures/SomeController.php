<?php

namespace PHPRouter\Test\Fixtures;

final class SomeController
{
    public function usersCreate()
    {
    }

    public function indexAction()
    {
    }

    public function user()
    {
    }

    /**
     * @return mixed[]
     */
    public function page()
    {
        return func_get_args();
    }

    /**
     * @return mixed[]
     */
    public function dynamicFilterUrlMatch()
    {
        return func_get_args();
    }

    public function parameterSort($id, $group, $user, $page, $tag)
    {
        echo implode(",", func_get_args());
    }
}
