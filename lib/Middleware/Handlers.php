<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Xibo\Middleware;

use Nyholm\Psr7\Factory\Psr17Factory;
use Slim\Exception\HttpSpecializedException;
use Slim\Http\Factory\DecoratedResponseFactory;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\ExpiredException;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InstanceSuspendedException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\UpgradePendingException;

/**
 * Class Handlers
 * @package Xibo\Middleware
 */
class Handlers
{
    /**
     * A JSON error handler to format and output a JSON response and HTTP status code depending on the error received.
     * @param \Psr\Container\ContainerInterface $container
     * @return \Closure
     */
    public static function jsonErrorHandler($container)
    {
        return function (Request $request, \Throwable $exception, bool $displayErrorDetails, bool $logErrors, bool $logErrorDetails) use ($container) {
            // If we are in a transaction, then we should rollback.
            if ($container->get('store')->getConnection()->inTransaction()) {
                $container->get('store')->getConnection()->rollBack();
            }
            $container->get('store')->close();

            // Handle error handling
            if ($logErrors && !self::handledError($exception)) {
                /** @var \Psr\Log\LoggerInterface $logger */
                $logger = $container->get('logger');
                $logger->error('Error with message: ' . $exception->getMessage());

                if ($logErrorDetails) {
                    $logger->debug('Error with trace: ' . $exception->getTraceAsString());
                }
            }

            // Generate a response (start with a 500)
            $nyholmFactory = new Psr17Factory();
            $decoratedResponseFactory = new DecoratedResponseFactory($nyholmFactory, $nyholmFactory);

            /** @var Response $response */
            $response = $decoratedResponseFactory->createResponse(500);

            if ($exception instanceof GeneralException) {
                return $exception->generateHttpResponse($response);
            } else if ($exception instanceof HttpSpecializedException) {
                return $response->withJson([
                    'success' => false,
                    'error' => $exception->getCode(),
                    'message' => $exception->getTitle(),
                    'help' => $exception->getDescription()
                ]);
            } else {
                return $response->withJson([
                    'success' => false,
                    'error' => 500,
                    'message' => $exception->getMessage()
                ]);
            }
        };
    }

    /**
     * @param \Psr\Container\ContainerInterface $container
     * @return \Closure
     */
    public static function webErrorHandler($container)
    {
        return function (Request $request, \Throwable $exception, bool $displayErrorDetails, bool $logErrors, bool $logErrorDetails) use ($container) {
            $nyholmFactory = new Psr17Factory();
            $decoratedResponseFactory = new DecoratedResponseFactory($nyholmFactory, $nyholmFactory);
            /** @var Response $response */
            $response = $decoratedResponseFactory->createResponse($exception->getCode());

            if ($exception->getCode() == 404) {
                $container->get('logger')->debug(sprintf('Page Not Found. %s', $request->getUri()->getPath()));
                return $response = $response->withRedirect('/notFound');
            } else {
                /** @var \Xibo\Helper\Session $session */
                $session = $container->get('session');

                /** @var \Psr\Log\LoggerInterface $logger */
                $logger = $container->get('logger');

                $message = ( !empty($exception->getMessage()) ) ? $exception->getMessage() : __('Unexpected Error, please contact support.');

                // log the error
                $logger->error('Error with message: ' . $message);
                $logger->debug('Error with trace: ' . $exception->getTraceAsString());

                $exceptionClass = 'error-' . strtolower(str_replace('\\', '-', get_class($exception)));

                if ($exception instanceof UpgradePendingException) {
                    $exceptionClass = 'upgrade-in-progress-page';
                }

                if ($request->getUri()->getPath() != '/error') {

                    // set data in session, this is handled and then cleared in Error Controller.
                    $session->set('exceptionMessage', $message);
                    $session->set('exceptionCode', $exception->getCode());
                    $session->set('exceptionClass', $exceptionClass);
                    $session->set('priorRoute', $request->getUri()->getPath());

                    return $response = $response->withRedirect('/error');
                } else {
                    // this should only happen when there is an error in Middleware or if something went horribly wrong.
                    $mode = $container->get('configService')->getSetting('SERVER_MODE');

                    if (strtolower($mode) === 'test') {
                        $message = $exception->getMessage() . ' thrown in ' . $exception->getTraceAsString();
                    } else {
                        $message = $exception->getMessage();
                    }

                    // If we are in a transaction, then we should rollback.
                    if ($container->get('store')->getConnection()->inTransaction()) {
                        $container->get('store')->getConnection()->rollBack();
                    }
                    $container->get('store')->close();

                    // attempt to render a twig template in this application state will not go well
                    // as such return simple json response, with trace if the application is in test mode.
                    return $response = $response->withJson(['error' => $message]);
                }
            }
        };
    }

    /**
     * @param \Psr\Container\ContainerInterface $container
     * @return \Closure
     */
    public static function testErrorHandler($container)
    {
        return function (Request $request, \Throwable $exception, bool $displayErrorDetails, bool $logErrors, bool $logErrorDetails) use ($container) {
            // If we are in a transaction, then we should rollback.
            if ($container->get('store')->getConnection()->inTransaction()) {
                $container->get('store')->getConnection()->rollBack();
            }
            $container->get('store')->close();

            $nyholmFactory = new Psr17Factory();
            $decoratedResponseFactory = new DecoratedResponseFactory($nyholmFactory, $nyholmFactory);
            /** @var Response $response */
            $response = $decoratedResponseFactory->createResponse($exception->getCode());

            return $response->withJson([
                'success' => false,
                'error' => $exception->getMessage(),
                'httpStatus' => $exception->getCode(),
                'data' => []
            ]);
        };
    }

    /**
     * Determine if we are a handled exception
     * @param $e
     * @return bool
     */
    private static function handledError($e)
    {
        if (method_exists($e, 'handledException'))
            return $e->handledException();

        return ($e instanceof InvalidArgumentException
            || $e instanceof ExpiredException
            || $e instanceof AccessDeniedException
            || $e instanceof InstanceSuspendedException
            || $e instanceof UpgradePendingException
        );
    }
}