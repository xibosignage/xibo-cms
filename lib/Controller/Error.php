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
use Xibo\Exception\NotFoundException;
use Xibo\Helper\Log;
use Xibo\Helper\Translate;

class Error extends Base
{
    public function notFound()
    {
        $app = $this->getApp();

        // Not found controller happens outside the normal middleware stack for some reason
        // Setup the translations for gettext
        Translate::InitLocale();

        // Configure the locale for date/time
        if (Translate::GetLocale(2) != '')
            \Jenssegers\Date\Date::setLocale(Translate::GetLocale(2));

        $message = __('Page not found');

        // Different action depending on the app name
        switch ($app->getName()) {

            case 'web':

                if ($this->getApp()->request()->isAjax()) {
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

    public function handler(\Exception $e)
    {
        $app = $this->getApp();
        $handled = $this->handledError($e);
        $app->commit = false;

        if ($handled) {
            Log::debug($e->getMessage());
        }
        else {
            // Log the full error
            Log::debug($e->getMessage() . $e->getTraceAsString());
            Log::error($e->getMessage() . ' Exception Type: ' . get_class($e));
        }

        // Different action depending on the app name
        switch ($app->getName()) {

            case 'web':

                $message = ($handled) ? $e->getMessage() : __('Unexpected Error, please contact support.');

                if ($this->getApp()->request()->isAjax()) {
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

                    if (file_exists(PROJECT_ROOT . '/views/' . $exceptionClass . '.twig'))
                        $this->getState()->template = $exceptionClass;
                    else
                        $this->getState()->template = 'error';

                    $app->flashNow('globalError', $message);
                }

                $this->render();

                break;

            case 'auth':
            case 'api':
            case 'test':

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
                    'message' => (($handled) ? __($e->getMessage()) : __('Unexpected Error, please contact support.'))
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

    private function handledError($e)
    {
        return ($e instanceof \InvalidArgumentException
            || $e instanceof OAuthException
            || $e instanceof FormExpiredException
            || $e instanceof AccessDeniedException
            || $e instanceof NotFoundException
            || $e instanceof InstanceSuspendedException
            || $e instanceof ConfigurationException
        );
    }
}