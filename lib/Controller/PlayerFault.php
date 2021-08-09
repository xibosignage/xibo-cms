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

use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Factory\PlayerFaultFactory;

class PlayerFault extends Base
{
    /** @var PlayerFaultFactory */
    private $playerFaultFactory;

    /**
     * PlayerFault constructor.
     * @param PlayerFaultFactory $playerFaultFactory
     */
    public function __construct(PlayerFaultFactory $playerFaultFactory)
    {
        $this->playerFaultFactory = $playerFaultFactory;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param int $displayId
     * @return Response
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function grid(Request $request, Response $response, int $displayId) : Response
    {
        $parsedParams = $this->getSanitizer($request->getQueryParams());

        if ($displayId != null) {
            $playerFaults = $this->playerFaultFactory->getByDisplayId($displayId, $this->gridRenderSort($parsedParams));
        } else {
            $filter = [
                'code' => $parsedParams->getInt('code'),
                'incidentDt' => $parsedParams->getDate('incidentDt'),
                'displayId' => $parsedParams->getInt('displayId')
            ];

            $playerFaults = $this->playerFaultFactory->query($this->gridRenderSort($parsedParams), $this->gridRenderFilter($filter, $parsedParams));
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->playerFaultFactory->countLast();
        $this->getState()->setData($playerFaults);

        return $this->render($request, $response);
    }
}
