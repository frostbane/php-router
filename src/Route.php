<?php

namespace PHPRouter;

class Route
{
    /**
     * URL of this Route
     * @var string
     */
    private $url;

    /**
     * Accepted HTTP methods for this route.
     *
     * @var string[]
     */
    private $methods;

    /**
     * Target for this route, can be anything.
     * @var mixed
     */
    private $target;

    /**
     * The name of this route, used for reversed routing
     * @var string
     */
    private $name;

    /**
     * Custom parameter filters for this route
     * @var array
     */
    private $filters = array();

    /**
     * Array containing parameters passed through request URL
     * @var array
     */
    private $parameters = array();

    /**
     * @var null|string
     */
    private $action;

    /**
     * @var array
     */
    private $config;

    /**
     * @var null|string
     */
    private $class;

    /**
     * @param string $pathInfo
     * @param array  $config
     */
    public function __construct($pathInfo, array $config)
    {
        $this->url        = $pathInfo;
        $this->config     = $config;
        $this->methods    = isset($config['methods']) ? (array)$config['methods'] : array();
        $this->target     = isset($config['target']) ? $config['target'] : null;
        $this->name       = isset($config['name']) ? $config['name'] : null;
        $this->parameters = isset($config['parameters']) ? $config['parameters'] : array();
        $this->filters    = isset($config['filters']) ? $config['filters'] : array();
        $action           = isset ($this->config['_controller']) ? explode('::', $this->config['_controller']) : array();
        $this->class      = isset($action[0]) ? $action[0] : null;
        $this->action     = isset($action[1]) ? $action[1] : null;
    }

    public function getUrl()
    {
        return $this->url;
    }

    public function setUrl($url)
    {
        $url = (string)$url;

        // make sure that the URL is suffixed with a forward slash
        if (substr($url, -1) !== '/') {
            $url .= '/';
        }

        $this->url = $url;
    }

    public function getTarget()
    {
        return $this->target;
    }

    public function setTarget($target)
    {
        $this->target = $target;
    }

    public function getMethods()
    {
        return $this->methods;
    }

    public function setMethods(array $methods)
    {
        $this->methods = $methods;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = (string)$name;
    }

    public function setFilters(array $filters)
    {
        $this->filters = $filters;
    }

    public function getFilters()
    {
        return $this->filters;
    }

    public function getRegex()
    {
        $url = preg_quote($this->url);

        return preg_replace_callback('/(\\\\(:\w+))/', array(&$this, 'substituteFilter'), $url);
    }

    private function substituteFilter($matches)
    {
        if (isset($matches[1], $this->filters[$matches[2]])) {
            return $this->filters[$matches[2]];
        }

        return '([\w-%]+)';
    }

    public function getParameters()
    {
        return $this->parameters;
    }

    public function setParameters(array $parameters)
    {
        $this->parameters = $parameters;
    }

    public function getValidController()
    {
        $class  = $this->class;
        $method = $this->action;

        if (!class_exists($class)) {
            return null;
        }

        if (empty($method) || trim($method) === '') {
            $method = "__invoke";
        }

        try {
            $classRexl = new \ReflectionClass($class);
        } catch (\ReflectionException $ex) {
            return null;
        }

        try {
            $methRexl = $classRexl->getMethod($method);
        } catch (\ReflectionException $ex) {
            return null;
        }

        // use only public methods
        // to avoid calling inherited protected methods
        if(!$methRexl->isPublic()) {
            return null;
        }

        return $this->config['_controller'];
    }

    /**
     * sort parameters according the the method's arguments
     *
     * @return array
     *
     * @throws \ReflectionException
     */
    private function sortParameters()
    {
        $class      = $this->class;
        $method     = $this->action;
        $parameters = $this->parameters;
        $arguments  = array();

        if (empty($method) || trim($method) === '') {
            $method = "__invoke";
        }

        $rexl = new \ReflectionMethod($class, $method);

        foreach ($rexl->getParameters() as $methArgs) {
            $arg = $methArgs->getName();

            if (array_key_exists($arg, $parameters)) {
                $arguments[$arg] = $parameters[$arg];

                unset($parameters[$arg]);
            } else {
                // argument is not in the parameters
                $arguments[$arg] = null;
            }
        }

        if (count($parameters) > 0) {
            // fill the unset arguments
            foreach ($arguments as $arg => &$v) {
                if ($v === null) {
                    //$key = array_keys($parameters)[0];

                    $v = array_shift($parameters);
                }

                if (count($parameters) <= 0) {
                    break;
                }
            }
        }

        // merge the remaining parameters
        return array_merge($arguments, $parameters);
    }

    private function canBeEchoed($var)
    {
        return method_exists($var, '__toString') || (is_scalar($var) && !is_null($var));
    }

    public function dispatch($instance = null)
    {
        is_null($instance) and $instance = new $this->class();

        $param = $this->sortParameters();

        ob_start();

        if (empty($this->action) || trim($this->action) === '') {
            // __invoke on a class
            $result = call_user_func_array($instance, $param);
        } else {
            $result = call_user_func_array(array($instance, $this->action), $param);
        }

        if ($this->canBeEchoed($result)) {
            echo $result;
        }

        $buffer = ob_get_clean();

        return $buffer;
    }

    public function getAction()
    {
        return $this->action;
    }

    public function getClass()
    {
        return $this->class;
    }
}
