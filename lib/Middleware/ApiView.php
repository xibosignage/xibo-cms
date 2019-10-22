<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (ApiView.php)
 */


namespace Xibo\Middleware;


use Slim\Slim;
use Slim\View;
use Xibo\Helper\HttpsDetect;

class ApiView extends View
{
    public function render($template = '', $data = NULL)
    {
        $app = Slim::getInstance();

        // JSONP Callback?
        $jsonPCallback = $app->request->get('callback', null);

        // Don't envelope unless requested
        if ($jsonPCallback != null || $app->request()->get('envelope', 0) == 1 || $app->getName() == 'test') {
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
                $response = [
                    'error' => [
                        'message' => $this->get('message'),
                        'code' => intval($this->get('status')),
                        'data' => $this->get('data')
                    ]
                ];
            }
            else {
                // Are we a grid?
                if ($this->get('grid') == true) {
                    // Set the response to our data['data'] object
                    $grid = $this->get('data');
                    $response = $grid['data'];

                    // Total Number of Rows
                    $totalRows = $grid['recordsTotal'];

                    // Set some headers indicating our next/previous pages
                    $start = $app->sanitizerService->getInt('start', 0);
                    $size = $app->sanitizerService->getInt('length', 10);

                    $linkHeader = '';
                    $url = (new HttpsDetect())->getUrl() . $app->request()->getPath();

                    // Is there a next page?
                    if ($start + $size < $totalRows)
                        $linkHeader .= '<' . $url . '?start=' . ($start + $size) . '&length=' . $size . '>; rel="next", ';

                    // Is there a previous page?
                    if ($start > 0)
                        $linkHeader .= '<' . $url . '?start=' . ($start - $size) . '&length=' . $size . '>; rel="prev", ';

                    // The first page
                    $linkHeader .= '<' . $url . '?start=0&length=' . $size . '>; rel="first"';

                    $app->response()->header('X-Total-Count', $totalRows);
                    $app->response()->header('Link', $linkHeader);
                } else {
                    // Set the response to our data object
                    $response = $this->get('data');
                }
            }
        }

        // JSON header
        $app->response()->header('Content-Type', 'application/json');

        if ($jsonPCallback !== null) {
            $app->response()->body($jsonPCallback.'('.json_encode($response).')');
        } else {
            $app->response()->body(json_encode($response, JSON_PRETTY_PRINT));
        }

        $app->stop();
    }
}