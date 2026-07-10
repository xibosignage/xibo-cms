<?php
/*
 * Copyright (C) 2026 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
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
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Service\HelpServiceInterface;

/**
 * Class Help
 * @package Xibo\Controller
 */
class Help extends Base
{
    /** @var HelpServiceInterface */
    private $helpService;

    /**
     * @param HelpServiceInterface $helpService
     */
    public function __construct(HelpServiceInterface $helpService)
    {
        $this->helpService = $helpService;
    }

    #[OA\Get(
        path: '/help/page-links',
        operationId: 'helpPageLinks',
        description: 'Get the help landing page and any page specific help links',
        summary: 'Help page links',
        tags: ['misc']
    )]
    #[OA\Parameter(
        name: 'page',
        description: 'The page name (route name) to return help links for',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Response(
        response: 200,
        description: 'successful response'
    )]
    /**
     * Return the help landing page and links for the requested page.
     *
     * @param Request $request
     * @param Response $response
     * @return ResponseInterface|Response
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function pageLinks(Request $request, Response $response): ResponseInterface|Response
    {
        $page = $this->getSanitizer($request->getQueryParams())->getString('page');

        // HelpService renders link summaries through Parsedown, which can emit PHP
        // deprecation notices. Capture and discard any stray output so it cannot
        // corrupt the JSON response body.
        ob_start();
        try {
            $landingPage = $this->helpService->getLandingPage();
            $links = empty($page) ? [] : $this->helpService->getLinksForPage($page);
        } finally {
            ob_end_clean();
        }

        return $response->withJson([
            'landingPage' => $landingPage,
            'links' => $links,
        ]);
    }
}
