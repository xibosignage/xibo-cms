<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (Error.php)
 */


namespace Xibo\Controller;


use League\OAuth2\Server\Exception\OAuthException;
use Xibo\Helper\Log;

class Error extends Base
{
    public function handler(\Exception $e)
    {
        $app = $this->getApp();
        $handled = $this->handledError($e);

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
                        'message' => $message
                    ]);
                }
                else {
                    $app->flashNow('globalError', $message);
                    $this->getState()->template = 'error';
                }

                $this->render();

                break;

            case 'api':
            case 'test':

                $this->getState()->hydrate([
                    'success' => false,
                    'message' => (($handled) ? $e->getMessage() : __('Unexpected Error, please contact support.'))
                ]);

                $status = 500;

                if ($e instanceof OAuthException)
                    $status = $e->httpStatusCode;

                $this->render($status);

                break;

            case 'auth':
                $this->render();
                break;

            case 'console':
                // Render the error page.
                if ($handled) {
                    echo $e->getMessage();
                }
                else
                    echo $e;

                $app->stop();
                break;
        }
    }

    private function handledError($e)
    {
        return ($e instanceof \InvalidArgumentException);
    }
}