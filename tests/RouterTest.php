<?php
namespace Snout\Tests;

use PHPUnit\Framework\TestCase;
use Ds\Map;
use Snout\Exceptions\RouterException;
use Snout\Parameter;
use Snout\Route;
use Snout\Request;
use Snout\Router;

class RouterTest extends TestCase
{
    public function testMatch() : void
    {
        $routed = false;
        $test_parameters = new Map([
            'id'   => new Parameter('id', 'integer', 21),
            'name' => new Parameter('name', 'string', 'foo')
        ]);

        $get = function ($result_parameters) use ($test_parameters, &$routed) {
            $routed = true;

            $test_parameters->map(
                function ($name, $parameter) use ($result_parameters) {
                    $this->assertTrue(
                        $result_parameters->hasKey($name)
                    );
                    $this->assertTrue(
                        $parameter->compare($result_parameters->get($name))
                    );
                }
            );
        };

        $router = new Router();
        $router->push(new Route([
            'name'       => 'should_run',
            'path'       => '/user/{id: integer}/name/{name: string}',
            'controller' => new Map(['get' => $get])
        ]));

        $router->push(new Route([
            'name'       => 'should_not_run_1',
            'path'       => '/foo',
            'controller' => new Map()
        ]));

        $router->push(new Route([
            'name'       => 'should_not_run_2',
            'path'       => '/bar',
            'controller' => new Map()
        ]));

        $request = new Request('/user/21/name/foo', 'get');
        $this->assertEquals('get', $request->getMethod());
        $route = $router->match($request);
        $this->assertTrue($route->hasControllerForMethod($request->getMethod()));
        $this->assertFalse($route->hasSubRouter());
        $controller = $route->getControllerForMethod($request->getMethod());
        $parameters = $route->getParameters();
        $controller($route->getParameters());
        $this->assertTrue($routed);
    }

    public function testManualSubRouting() : void
    {
        $sub_routed = false;
        $test_parameters = new Map([
            'id'   => new Parameter('id', 'integer', 21)
        ]);

        $get = function ($result_parameters)
 use ($test_parameters, &$sub_routed) {
            $sub_routed = true;

            $test_parameters->map(
                function ($name, $parameter) use ($result_parameters) {
                    $this->assertTrue(
                        $result_parameters->hasKey($name)
                    );
                    $this->assertTrue(
                        $parameter->compare($result_parameters->get($name))
                    );
                }
            );
        };

        $sub_router = new Router();
        $sub_router->push(new Route([
            'name'       => 'sub_router',
            'path'       => '/{id: integer}',
            'controller' => new Map(['get' => $get]),
        ]));

        $routed = false;
        $router = new Router();
        $router->push(new Route([
            'name'       => 'router',
            'path'       => '/user',
            'controller' => new Map([
                'get' => function () use (&$routed) {
                    $routed = true;
                }
            ]),
            'sub_router' => $sub_router
        ]));

        $request = new Request('/user/21', 'get');
        $this->assertEquals('get', $request->getMethod());
        $route = $router->match($request);
        $this->assertTrue($route->hasControllerForMethod($request->getMethod()));
        $controller = $route->getControllerForMethod($request->getMethod());
        $parameters = $route->getParameters();
        $controller($route->getParameters());

        $this->assertTrue($route->hasSubRouter());
        $sub_router = $route->getSubRouter();
        $sub_route = $sub_router->match($request);
        $this->assertTrue(
            $sub_route->hasControllerForMethod($request->getMethod())
        );
        $sub_controller = $sub_route->getControllerForMethod(
            $request->getMethod()
        );
        $parameters->putAll($sub_route->getParameters());
        $sub_controller($parameters);

        $this->assertTrue($sub_routed);
        $this->assertTrue($routed);
    }

    public function testRun() : void
    {
        $routed = false;
        $test_parameters = new Map([
            'id'   => new Parameter('id', 'integer', 21),
            'name' => new Parameter('name', 'string', 'foo')
        ]);

        $get = function ($result_parameters, $arg) use ($test_parameters, &$routed) {
            $routed = true;
            $this->assertEquals('bar', $arg);

            $test_parameters->map(
                function ($name, $parameter) use ($result_parameters) {
                    $this->assertTrue(
                        $result_parameters->hasKey($name)
                    );
                    $this->assertTrue(
                        $parameter->compare($result_parameters->get($name))
                    );
                }
            );
        };

        $router = new Router();
        $router->push(new Route([
            'name'       => 'should_run',
            'path'       => '/user/{id: integer}/name/{name: string}',
            'controller' => new Map(['get' => $get])
        ]));

        $router->push(new Route([
            'name'       => 'should_not_run_1',
            'path'       => '/foo',
            'controller' => new Map()
        ]));

        $router->push(new Route([
            'name'       => 'should_not_run_2',
            'path'       => '/bar',
            'controller' => new Map()
        ]));

        $router->run(new Request('/user/21/name/foo', 'get'), 'bar');
        $this->assertTrue($routed);
    }

    public function testAutomaticSubRouting() : void
    {
        $sub_routed = false;
        $test_parameters = new Map([
            'id'   => new Parameter('id', 'integer', 21)
        ]);

        $get = function ($result_parameters, $arg) use ($test_parameters, &$sub_routed) {
            $sub_routed = true;
            $this->assertEquals('bar', $arg);

            $test_parameters->map(
                function ($name, $parameter) use ($result_parameters) {
                    $this->assertTrue(
                        $result_parameters->hasKey($name)
                    );
                    $this->assertTrue(
                        $parameter->compare($result_parameters->get($name))
                    );
                }
            );
        };

        $sub_router = new Router();
        $sub_router->push(new Route([
            'name'       => 'sub_router',
            'path'       => '/{id: integer}',
            'controller' => new Map(['get' => $get]),
        ]));

        $routed = false;
        $router = new Router();
        $router->push(new Route([
            'name'       => 'router',
            'path'       => '/user',
            'controller' => new Map([
                'get' => function ($parameters, $arg) use (&$routed) {
                    $routed = true;
                    $this->assertTrue($parameters->isEmpty());
                    $this->assertEquals('bar', $arg);
                }
            ]),
            'sub_router' => $sub_router
        ]));

        $router->run(new Request('/user/21', 'get'), $arg = 'bar');
        $this->assertTrue($sub_routed);
        $this->assertTrue($routed);
    }

    public function testCustomParameterType() : void
    {
        $routed = false;
        $test_parameters = new Map([
            'name' => new Parameter('name', 'label', 'foo[]')
        ]);

        $get = function ($result_parameters) use ($test_parameters, &$routed) {
            $routed = true;

            $test_parameters->map(
                function ($name, $parameter) use ($result_parameters) {
                    $this->assertTrue(
                        $result_parameters->hasKey($name)
                    );
                    $this->assertTrue(
                        $parameter->compare($result_parameters->get($name))
                    );
                }
            );
        };

        $router = new Router();
        $router->push(new Route([
            'name'       => 'should_run',
            'path'       => '/name/{name: label}',
            'controller' => new Map(['get' => $get]),
            'parameters' => [
                'label' => [
                    'tokens' => [
                        'DIGIT',
                        'ALPHA',
                        'UNDERSCORE',
                        'OPEN_BRACKET',
                        'CLOSE_BRACKET'
                    ],
                    'cast' => 'string'
                ]
            ]
        ]));

        $request = new Request('/name/foo[]', 'get');
        $this->assertEquals('get', $request->getMethod());
        $route = $router->match($request);
        $this->assertTrue($route->hasControllerForMethod($request->getMethod()));
        $this->assertFalse($route->hasSubRouter());
        $controller = $route->getControllerForMethod($request->getMethod());
        $parameters = $route->getParameters();
        $controller($route->getParameters());
        $this->assertTrue($routed);
    }

    public function testNoRoutes() : void
    {
        $this->expectException(RouterException::class);
        $this->expectExceptionMessage(
            "No match for request '/user/21' - No routes were specified."
        );

        $request = new Request('/user/21', 'get');
        $router = new Router();
        $route = $router->match($request);
    }

    public function testMultipleMatches() : void
    {
        $this->expectException(RouterException::class);
        $this->expectExceptionMessage(
            "No match for request '/foo' - Multiple possible routes."
        );

        $router = new Router();
        $router->push(new Route([
            'path'       => '/foo',
            'controller' => new Map()
        ]));

        $router->push(new Route([
            'path'       => '/foo',
            'controller' => new Map()
        ]));

        $router->run(new Request('/foo', 'get'));
    }

    public function testIncompleteMatch() : void
    {
        $this->expectException(RouterException::class);
        $this->expectExceptionMessage(
            "No match for request '/foobar' - "
            . "Incomplete match with route 'foo'."
        );

        $router = new Router();
        $router->push(new Route([
            'name'       => 'foo',
            'path'       => '/foo',
            'controller' => new Map()
        ]));

        $router->run(new Request('/foobar', 'get'));
    }

    public function testDuplicateParameters() : void
    {
        $this->expectException(RouterException::class);
        $this->expectExceptionMessage(
            "Duplicate embedded parameters: 'id'."
        );

        $sub_router = new Router();
        $sub_router->push(new Route([
            'name'       => 'sub_router',
            'path'       => '/{id: integer}',
            'controller' => new Map([
                'get' => function ($parameters) {
                    return;
                }
            ])
        ]));

        $router = new Router();
        $router->push(new Route([
            'name'       => 'router',
            'path'       => '/user/{id: integer}',
            'controller' => new Map([
                'get' => function ($parameters) {
                    return;
                }
            ]),
            'sub_router' => $sub_router
        ]));

        $router->run(new Request('/user/21/22', 'get'), $arg = 'bar');
        $this->assertTrue($sub_routed);
        $this->assertTrue($routed);
    }
}
