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

use OpenApi\Attributes as OA;
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

    #[OA\Get(
        path: '/display/faults',
        operationId: 'displayFaultsSearch',
        description: 'Search Player reported faults',
        summary: 'Player Fault Search',
        tags: ['display']
    )]
    #[OA\Get(
        path: '/display/faults/{displayId}',
        operationId: 'displayFaultsSearchByDisplayId',
        description: 'Search Player reported faults for a single Display',
        summary: 'Player Fault Search by Display',
        tags: ['display']
    )]
    #[OA\Parameter(
        name: 'displayId',
        description: 'The Display ID to restrict results to',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'code',
        description: 'Filter by fault code',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'incidentDt',
        description: 'Filter by the date the fault occurred',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'activeOnly',
        description: 'Only return faults which are currently active, excluding any which have already expired',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'boolean')
    )]
    #[OA\Response(
        response: 200,
        description: 'successful operation',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/PlayerFault')
        )
    )]
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
        $activeOnly = $parsedParams->getCheckbox('activeOnly') === 1;

        if ($displayId != null) {
            $playerFaults = $this->playerFaultFactory->getByDisplayId(
                $displayId,
                $this->gridRenderSort($parsedParams),
                $activeOnly
            );
        } else {
            $filter = [
                'code' => $parsedParams->getInt('code'),
                'incidentDt' => $parsedParams->getDate('incidentDt'),
                'displayId' => $parsedParams->getInt('displayId'),
                'activeOnly' => $activeOnly
            ];

            $playerFaults = $this->playerFaultFactory->query($this->gridRenderSort($parsedParams), $this->gridRenderFilter($filter, $parsedParams));
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->playerFaultFactory->countLast();
        $this->getState()->setData($playerFaults);

        return $this->render($request, $response);
    }
}
