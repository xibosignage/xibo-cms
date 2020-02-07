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


namespace Xibo\Controller;

use League\OAuth2\Server\Exception\OAuthException;
use Psr\Container\ContainerInterface;
use Slim\Views\Twig;
use Throwable;
use Xibo\Exception\AccessDeniedException;
use Xibo\Exception\ConfigurationException;
use Xibo\Exception\FormExpiredException;
use Xibo\Exception\InstanceSuspendedException;
use Xibo\Exception\TokenExpiredException;
use Xibo\Exception\UpgradePendingException;
use Xibo\Helper\ApplicationState;
use Xibo\Helper\Environment;
use Xibo\Helper\SanitizerService;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\HelpServiceInterface;
use Xibo\Service\LogServiceInterface;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;

/**
 * Class Error
 * @package Xibo\Controller
 */
class Error extends Base
{

    /** @var ContainerInterface */
   private $container;

    /**
     * Set common dependencies.
     * @param LogServiceInterface $log
     * @param SanitizerService $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param DateServiceInterface $date
     * @param ConfigServiceInterface $config
     * @param Twig $view
     * @param ContainerInterface $container
     */
    public function __construct(LogServiceInterface $log, SanitizerService $sanitizerService, ApplicationState $state, \Xibo\Entity\User $user, HelpServiceInterface $help, DateServiceInterface $date, ConfigServiceInterface $config, Twig $view, ContainerInterface $container)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config, $view);
        $this->container = $container;
    }

    /**
     * Determine if we are a handled exception
     * @param $e
     * @return bool
     */
    private function handledError($e)
    {
        if (method_exists($e, 'handledException'))
            return $e->handledException();

        return ($e instanceof \InvalidArgumentException
            || $e instanceof OAuthException
            || $e instanceof FormExpiredException
            || $e instanceof AccessDeniedException
            || $e instanceof InstanceSuspendedException
            || $e instanceof UpgradePendingException
            || $e instanceof TokenExpiredException
        );
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Xibo\Exception\ConfigurationException
     * @throws \Xibo\Exception\ControllerNotImplemented
     */
    public function notFoundPage(Request $request, Response $response)
    {
        $message = __('Not found');

        // TODO make sure all relevant cases have their names set (web and api do), at the moment only web entry point is set to use this function.
        switch ($request->getAttribute('name')) {

            case 'web':

                if ($request->isXhr()) {
                    $this->getState()->hydrate([
                        'success' => false,
                        'message' => $message,
                        'template' => ''
                    ]);
                }
                else {
                    $this->getState()->template = 'not-found';
                }

                return $this->render($request, $response);

                break;

            case 'auth':
            case 'API':
            case 'test':

                $this->getState()->hydrate([
                    'httpStatus' => 404,
                    'success' => false,
                    'message' => $message
                ]);

                return $this->render($request, $response);

                break;

            case 'console':
            case 'maint':

                // Render the error page.
                echo $message;

                //$app->stop();
                break;
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Xibo\Exception\ConfigurationException
     * @throws \Xibo\Exception\ControllerNotImplemented
     */
    public function errorPage(Request $request, Response $response)
    {
        //$handled = $this->handledError($e);
        $message = $this->container->get('session')->get('exceptionMessage');

        // TODO make sure all relevant cases have their names set (web and api do), at the moment only web entry point is set to use this function.
        switch ($request->getAttribute('name')) {

            case 'web':
                // Just in case our theme has not been set by the time the exception was raised.
                $this->getState()->setData([
                    'theme' => $this->getConfig(),
                    'version' => Environment::$WEBSITE_VERSION_NAME
                ]);

                if ($request->isXhr()) {
                    $this->getState()->hydrate([
                        'success' => false,
                        'message' => $message,
                        'template' => ''
                    ]);
                }
                else {
                    // Template depending on whether one exists for the type of exception
                    // get the exception class
                    $exceptionClass = $this->container->get('session')->get('exceptionClass');

                    $this->getLog()->debug('Loading error template ' . $exceptionClass);

                    if (file_exists(PROJECT_ROOT . '/views/' . $exceptionClass . '.twig')) {
                        $this->getState()->template = $exceptionClass;
                    } else {
                        $this->getState()->template = 'error';
                    }

                    $this->getState()->setData([
                        'error' => $message
                    ]);
                }

                $this->container->get('session')->unSet('exceptionMessage');
                $this->container->get('session')->unSet('exceptionClass');
                $this->container->get('session')->unSet('exceptionCode');
                $this->getState()->setCommitState(false);

                return $this->render($request, $response);

                break;

            case 'auth':
            case 'API':

                $this->getState()->hydrate([
                    'httpStatus' => $this->container->get('session')->get('exceptionCode'),
                    'success' => false,
                    'message' => $message,
                    'data' => []
                ]);

                return $this->render($request, $response);

                break;

            case 'console':
            case 'maint':

                // Render the error page.
                echo $message;

                //$app->stop();
                break;
        }
    }
}