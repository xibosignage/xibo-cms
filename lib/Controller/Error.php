<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (Error.php)
 */


namespace Xibo\Controller;


use League\OAuth2\Server\Exception\OAuthException;
use Xibo\Exception\AccessDeniedException;
use Xibo\Exception\FormExpiredException;
use Xibo\Exception\NotFoundException;
use Xibo\Helper\Log;

class Error extends Base
{
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
                    $app->flashNow('globalError', $message);
                    $this->getState()->template = 'error';
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
                        $app->response()->headers($header);
                    }
                }

                $this->getState()->hydrate([
                    'httpStatus' => $status,
                    'success' => false,
                    'message' => (($handled) ? $e->getMessage() : __('Unexpected Error, please contact support.'))
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
        );
    }
}