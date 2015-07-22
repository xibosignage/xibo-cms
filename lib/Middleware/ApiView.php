<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (ApiView.php)
 */


namespace Xibo\Middleware;


use Slim\Slim;
use Slim\View;

class ApiView extends View
{
    public function render($status=200, $data = NULL) {

        $app = Slim::getInstance();

        $status = intval($status);

        $response = $this->all();

        //append error bool
        if (!$this->has('error')) {
            $response['error'] = false;
        }

        //append status code
        $response['status'] = $app->response()->getStatus();

        //add flash messages
        if(isset($this->data->flash) && is_object($this->data->flash)){
            $flash = $this->data->flash->getMessages();
            if (count($flash)) {
                $response['flash'] = $flash;
            } else {
                unset($response['flash']);
            }
        }

        $app->response()->header('Content-Type', 'application/json');

        $jsonp_callback = $app->request->get('callback', null);

        if ($jsonp_callback !== null) {
            $app->response()->body($jsonp_callback.'('.json_encode($response).')');
        } else {
            $app->response()->body(json_encode($response));
        }

        $app->stop();
    }
}