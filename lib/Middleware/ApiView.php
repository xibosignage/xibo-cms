<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (ApiView.php)
 */


namespace Xibo\Middleware;


use Slim\Slim;
use Slim\View;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;

class ApiView extends View
{
    public function render($template = '', $data = NULL)
    {
        Log::debug('API Render with data %s', json_encode($this->all()));

        $app = Slim::getInstance();

        // Don't envelope unless requested
        if (Sanitize::getInt('envelope') == 1) {
            // Envelope
            $response = $this->all();

            // append error bool
            if (!$this->has('success') || !$this->get('success')) {
                $response['success'] = false;
            }

            // append status code
            $response['status'] = $app->response()->getStatus();

            // add flash messages
            if (isset($this->data->flash) && is_object($this->data->flash)){
                $flash = $this->data->flash->getMessages();
                if (count($flash)) {
                    $response['flash'] = $flash;
                } else {
                    unset($response['flash']);
                }
            }

            // Enveloped responses always return 200
            $app->status(200);
        } else {
            // Don't envelope
            // Set status
            $app->status(intval($this->get('status')));

            // Are we successful?
            if (!$this->has('success') || !$this->get('success')) {
                // Error condition
                $response = [];
                $app->response()->body($this->get('message'));
                $app->stop();
            }
            else {
                // Set the response to our data object
                $response = $this->get('data');
            }
        }

        // JSON header
        $app->response()->header('Content-Type', 'application/json');

        // Callback?
        $jsonp_callback = $app->request->get('callback', null);

        if ($jsonp_callback !== null) {
            $app->response()->body($jsonp_callback.'('.json_encode($response).')');
        } else {
            $app->response()->body(json_encode($response));
        }

        $app->stop();
    }
}