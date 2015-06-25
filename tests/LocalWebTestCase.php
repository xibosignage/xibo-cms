<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (TestCase.php)
 */

namespace Xibo\Tests;

use Slim\Environment;
use Slim\Slim;
use Xibo\Controller\Error;

class LocalWebTestCase extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Slim
     */
    protected $app;
    public $request;
    public $response;

    // We support these methods for testing. These are available via
    // `this->get()` and `$this->post()`. This is accomplished with the
    // `__call()` magic method below.
    public $testingMethods = array('get', 'post', 'patch', 'put', 'delete', 'head');

    /**
     * Gets the Slim instance configured
     * @return Slim
     * @throws \Xibo\Exception\NotFoundException
     */
    public function getSlimInstance()
    {
        $app = new \Slim\Slim(array(
            'mode' => 'testing'
        ));
        $app->setName('test');
        $app->runNo = \Xibo\Helper\Random::generateString(10);
        $app->add(new \Xibo\Middleware\Storage());
        $app->add(new \Xibo\Middleware\State());

        $app->add(new \JsonApiMiddleware());
        $app->view(new \JsonApiView());

        // Configure the Slim error handler
        $app->error(function (\Exception $e) use ($app) {
            $controller = new Error();
            $controller->handler($e);
        });

        // Super User
        $app->user = \Xibo\Factory\UserFactory::getById(1);

        // All routes
        require PROJECT_ROOT . '/lib/routes.php';

        return $app;
    }

    protected function start()
    {
        $this->app = $this->getSlimInstance();
    }

    // Implement our `get`, `post`, and other http operations
    public function __call($method, $arguments)
    {
        if (in_array($method, $this->testingMethods)) {
            list($path, $data, $headers) = array_pad($arguments, 3, array());
            return $this->request($method, $path, $data, $headers);
        }
        throw new \BadMethodCallException(strtoupper($method) . ' is not supported');
    }

    // Abstract way to make a request to SlimPHP, this allows us to mock the
    // slim environment
    private function request($method, $path, $data = array(), $optionalHeaders = array())
    {
        // Capture STDOUT
        ob_start();

        $options = array(
            'REQUEST_METHOD' => strtoupper($method),
            'PATH_INFO'      => $path,
            'SERVER_NAME'    => 'local.dev'
        );

        if ($method === 'get') {
            $options['QUERY_STRING'] = http_build_query($data);
        } elseif (is_array($data)) {
            $options['slim.input']   = http_build_query($data);
        } else {
            $options['slim.input']   = $data;
        }

        // Prepare a mock environment
        Environment::mock(array_merge($options, $optionalHeaders));

        // Establish some useful references to the slim app properties
        $this->request  = $this->app->request();
        $this->response = $this->app->response();

        // Execute our app
        $this->app->run();

        // Return the application output. Also available in `response->body()`
        return ob_get_clean();
    }
}