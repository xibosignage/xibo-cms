<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
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

use Illuminate\Support\Str;
use League\OAuth2\Server\Exception\OAuthServerException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Slim\Exception\HttpNotFoundException;
use Slim\Exception\HttpSpecializedException;
use Slim\Http\Factory\DecoratedResponseFactory;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Helper\Environment;
use Xibo\Helper\HttpsDetect;
use Xibo\Helper\Translate;
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
            self::rollbackAndCloseStore($container);
            self::writeLog($request, $logErrors, $logErrorDetails, $exception, $container);

            // Generate a response (start with a 500)
            $nyholmFactory = new Psr17Factory();
            $decoratedResponseFactory = new DecoratedResponseFactory($nyholmFactory, $nyholmFactory);

            /** @var Response $response */
            $response = $decoratedResponseFactory->createResponse(500);

            if ($exception instanceof GeneralException || $exception instanceof OAuthServerException) {
                return $exception->generateHttpResponse($response);
            } else if ($exception instanceof HttpSpecializedException) {
                return $response->withJson([
                    'success' => false,
                    'error' => $exception->getCode(),
                    'message' => $exception->getTitle(),
                    'help' => $exception->getDescription()
                ]);
            } else {
                // Any other exception, check to see if we hide the real message
                return $response->withJson([
                    'success' => false,
                    'error' => 500,
                    'message' => $displayErrorDetails
                        ? $exception->getMessage()
                        : __('Unexpected Error, please contact support.')
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
            self::rollbackAndCloseStore($container);
            self::writeLog($request, $logErrors, $logErrorDetails, $exception, $container);

            // Create a response
            // we're outside Slim's middleware here, so we have to handle the response ourselves.
            $nyholmFactory = new Psr17Factory();
            $decoratedResponseFactory = new DecoratedResponseFactory($nyholmFactory, $nyholmFactory);
            $response = $decoratedResponseFactory->createResponse();

            // We need to build all the functions required in the views manually because our middleware stack will
            // not have been built for this handler.
            // Slim4 has made this much more difficult!
            // Terrible in fact.

            // Get the Twig view
            /** @var \Slim\Views\Twig $twig */
            $twig = $container->get('view');

            /** @var \Xibo\Service\ConfigService $configService */
            $configService = $container->get('configService');
            $configService->setDependencies($container->get('store'), $container->get('rootUri'));
            $configService->loadTheme();

            // Do we need to issue STS?
            $response = HttpsDetect::decorateWithStsIfNecessary($configService, $request, $response);

            // Prepend our theme files to the view path
            // Does this theme provide an alternative view path?
            if ($configService->getThemeConfig('view_path') != '') {
                $twig->getLoader()->prependPath(Str::replaceFirst('..', PROJECT_ROOT,
                    $configService->getThemeConfig('view_path')));
            }

            // We have translated error/not-found
            Translate::InitLocale($configService);
            // Build up our own params to pass to Twig
            $viewParams = [
                'theme' => $configService,
                'homeUrl' => $configService->rootUri(),
                'aboutUrl' => $configService->rootUri() . 'about',
                'loginUrl' => $configService->rootUri() . 'login',
                'version' => Environment::$WEBSITE_VERSION_NAME
            ];

            // Handle 404's
            if ($exception instanceof HttpNotFoundException) {
                if ($request->isXhr()) {
                    return $response->withJson([
                        'success' => false,
                        'error' => 404,
                        'message' => __('Sorry we could not find that page.')
                    ], 404);
                } else {
                    return $twig->render($response, 'not-found.twig', $viewParams)->withStatus(404);
                }
            } else {
                // Make a friendly message
                if ($displayErrorDetails || $exception instanceof GeneralException) {
                    $message = htmlspecialchars($exception->getMessage());
                } else {
                    $message = __('Unexpected Error, please contact support.');
                }

                // Parse out data for the exception
                $exceptionData = [
                    'success' => false,
                    'error' => $exception->getCode(),
                    'message' => $message
                ];

                // TODO: we need to update the support library to make getErrorData public
                /*if ($exception instanceof GeneralException) {
                    array_merge($exception->getErrorData(), $exceptionData);
                }*/

                if ($request->isXhr()) {
                    // Note: these are currently served as 200's, which is expected by the FE.
                    return $response->withJson($exceptionData);
                } else {
                    // What status code?
                    $statusCode = 500;
                    if ($exception instanceof GeneralException) {
                        $statusCode = $exception->getHttpStatusCode();
                    }
                    if ($exception instanceof HttpSpecializedException) {
                        $statusCode = $exception->getCode();
                    }

                    // Decide which error page we should load
                    $exceptionClass = 'error-' . strtolower(str_replace('\\', '-', get_class($exception)));

                    // Override the page for an Upgrade Pending Exception
                    if ($exception instanceof UpgradePendingException) {
                        $exceptionClass = 'upgrade-in-progress-page';
                    }

                    if (file_exists(PROJECT_ROOT . '/views/' . $exceptionClass . '.twig')) {
                        $template = $exceptionClass;
                    } else {
                        $template = 'error';
                    }

                    try {
                        return $twig->render($response, $template . '.twig', array_merge($viewParams, $exceptionData))
                            ->withStatus($statusCode);
                    } catch (\Exception $exception) {
                        $response->getBody()->write('Fatal error');
                        return $response->withStatus(500);
                    }
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
            self::rollbackAndCloseStore($container);

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
    private static function handledError($e): bool
    {
        return ($e instanceof InvalidArgumentException
            || $e instanceof ExpiredException
            || $e instanceof AccessDeniedException
            || $e instanceof InstanceSuspendedException
            || $e instanceof UpgradePendingException
        );
    }

    /**
     * @param \Psr\Container\ContainerInterface $container
     */
    private static function rollbackAndCloseStore($container)
    {
        // If we are in a transaction, then we should rollback.
        if ($container->get('store')->getConnection()->inTransaction()) {
            $container->get('store')->getConnection()->rollBack();
        }
        $container->get('store')->close();
    }

    /**
     * @param Request $request
     * @param bool $logErrors
     * @param bool $logErrorDetails
     * @param \Throwable $exception
     * @param \Psr\Container\ContainerInterface $container
     */
    private static function writeLog($request, bool $logErrors, bool $logErrorDetails, \Throwable $exception, $container)
    {
        /** @var \Psr\Log\LoggerInterface $logger */
        $logger = $container->get('logger');

        // Add a processor to our log handler
        Log::addLogProcessorToLogger($logger, $request);

        // Handle logging the error.
        if ($logErrors && !self::handledError($exception)) {
            $logger->error($exception->getMessage());

            if ($logErrorDetails) {
                $logger->debug($exception->getTraceAsString());

                $previous = $exception->getPrevious();
                if ($previous !== null) {
                    $logger->debug(get_class($previous) . ': ' . $previous->getMessage());
                }
            }
        }
    }
}