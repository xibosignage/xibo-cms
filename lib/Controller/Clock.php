<?php
/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */
namespace Xibo\Controller;


use Carbon\Carbon;
use OpenApi\Attributes as OA;
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

    #[OA\Get(
        path: '/clock',
        operationId: 'clock',
        description: 'The Time',
        summary: 'The current CMS time',
        tags: ['misc']
    )]
    #[OA\Response(
        response: 200,
        description: 'successful response',
        content: new OA\JsonContent(
            type: 'object',
            additionalProperties: new OA\AdditionalProperties(type: 'string')
        )
    )]
    /**
     * Gets the Time
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
