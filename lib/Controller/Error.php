<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (Error.php)
 */


namespace Xibo\Controller;

use League\OAuth2\Server\Exception\OAuthException;
use Xibo\Exception\AccessDeniedException;
use Xibo\Exception\ConfigurationException;
use Xibo\Exception\FormExpiredException;
use Xibo\Exception\InstanceSuspendedException;
use Xibo\Exception\TokenExpiredException;
use Xibo\Exception\UpgradePendingException;
use Xibo\Helper\Environment;
use Xibo\Helper\Translate;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;

/**
 * Class Error
 * @package Xibo\Controller
 */
class Error extends Base
{
    /**
     * Set common dependencies.
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param DateServiceInterface $date
     * @param ConfigServiceInterface $config
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config);
    }

    /**
     * @throws ConfigurationException
     * @throws \Slim\Exception\Stop
     * @throws \Xibo\Exception\ControllerNotImplemented
     */
    public function notFound()
    {
        $app = $this->getApp();

        // Not found controller happens outside the normal middleware stack for some reason
        // Setup the translations for gettext
        Translate::InitLocale($this->getConfig());

        // Configure the locale for date/time
        if (Translate::GetLocale(2) != '')
            $this->getDate()->setLocale(Translate::GetLocale(2));
        
        $this->getLog()->debug('Page Not Found. %s', $app->request()->getResourceUri());

        $message = __('Page not found');

        // Different action depending on the app name
        switch ($app->getName()) {

            case 'web':

                // Set up theme
                \Xibo\Middleware\Theme::setTheme($app);

                if ($app->request()->isAjax()) {
                    $this->getState()->hydrate([
                        'success' => false,
                        'message' => $message,
                        'template' => ''
                    ]);
                }
                else {
                    $app->flashNow('globalError', $message);
                    $this->getState()->template = 'not-found';
                }

                $this->render();

                break;

            case 'auth':
            case 'api':
            case 'test':

                $this->getState()->hydrate([
                    'httpStatus' => 404,
                    'success' => false,
                    'message' => $message
                ]);

                $this->render();

                break;

            case 'console':
            case 'maint':

                // Render the error page.
                echo $message;

                $app->stop();
                break;
        }
    }

    /**
     * @param \Exception $e
     * @throws ConfigurationException
     * @throws \Slim\Exception\Stop
     * @throws \Xibo\Exception\ControllerNotImplemented
     */
    public function handler(\Exception $e)
    {
        $app = $this->getApp();
        $handled = $this->handledError($e);
        $app->commit = false;

        if ($handled) {
            $this->getLog()->debug($e->getMessage() . $e->getTraceAsString());
        }
        else {
            // Log the full error
            $this->getLog()->debug($e->getMessage() . $e->getTraceAsString());
            $this->getLog()->error($e->getMessage() . ' Exception Type: ' . get_class($e));
        }

        // Different action depending on the app name
        switch ($app->getName()) {

            case 'web':

                $message = ($handled) ? $e->getMessage() : __('Unexpected Error, please contact support.');

                // Just in case our theme has not been set by the time the exception was raised.
                $this->getState()->setData([
                    'theme' => $this->getConfig(),
                    'version' => Environment::$WEBSITE_VERSION_NAME
                ]);

                if ($app->request()->isAjax()) {
                    $this->getState()->hydrate([
                        'success' => false,
                        'message' => $message,
                        'template' => ''
                    ]);
                }
                else {
                    // Template depending on whether one exists for the type of exception
                    // get the exception class
                    $exceptionClass = 'error-' . strtolower(str_replace('\\', '-', get_class($e)));

                    // An upgrade might be pending
                    if ($e instanceof UpgradePendingException)
                        $exceptionClass = 'upgrade-in-progress-page';

                    $this->getLog()->debug('Loading error template ' . $exceptionClass);

                    if (file_exists(PROJECT_ROOT . '/views/' . $exceptionClass . '.twig')) {
                        $this->getState()->template = $exceptionClass;
                    } else {
                        $this->getState()->template = 'error';
                    }

                    $app->flashNow('globalError', $message);
                }

                $this->render();

                break;

            case 'auth':
            case 'api':

                $status = 500;

                if ($e instanceof OAuthException) {
                    $status = $e->httpStatusCode;

                    foreach ($e->getHttpHeaders() as $header) {
                        $app->response()->header($header);
                    }
                }
                else if ($e instanceof \InvalidArgumentException) {
                    $status = 422;
                }
                else if (property_exists(get_class($e), 'httpStatusCode')) {
                    $status = $e->httpStatusCode;
                }

                $this->getState()->hydrate([
                    'httpStatus' => $status,
                    'success' => false,
                    'message' => (($handled) ? __($e->getMessage()) : __('Unexpected Error, please contact support.')),
                    'data' => (method_exists($e, 'getErrorData')) ? $e->getErrorData() : []
                ]);

                $this->render();

                break;

            case 'console':
            case 'maint':

                // Render the error page.
                if ($handled) {
                    echo $e->getMessage();
                }
                else
                    echo __('Unknown Error');

                $app->stop();
                break;
        }
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
}