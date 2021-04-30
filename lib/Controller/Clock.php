<?php
/**
 * Copyright (C) 2021 Xibo Signage Ltd
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


use Carbon\Carbon;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Helper\Session;

/**
 * Class Clock
 * @package Xibo\Controller
 */
class Clock extends Base
{
    /**
     * @var Session
     */
    private $session;

    /**
     * Set common dependencies.
     * @param Session $session
     */
    public function __construct($session)
    {
        $this->session = $session;
    }

    /**
     * Gets the Time
     *
     * @SWG\Get(
     *  path="/clock",
     *  operationId="clock",
     *  tags={"misc"},
     *  description="The Time",
     *  summary="The current CMS time",
     *  @SWG\Response(
     *      response=200,
     *      description="successful response",
     *      @SWG\Schema(
     *          type="object",
     *          additionalProperties={"title":"time", "type":"string"}
     *      )
     *  )
     * )
     *
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function clock(Request $request, Response $response)
    {
        $this->session->refreshExpiry = false;

        if ($request->isXhr() || $this->isApi($request)) {
            $output = Carbon::now()->format('H:i T');

            $this->getState()->setData(array('time' => $output));
            $this->getState()->html = $output;
            $this->getState()->clockUpdate = true;
            $this->getState()->success = true;
            return $this->render($request, $response);
        } else {
            // We are returning the response directly, so write the body.
            $response->getBody()->write(Carbon::now()->format('c'));
            return $response;
        }
    }
}
