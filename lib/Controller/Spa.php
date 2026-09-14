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

use Psr\Http\Message\ResponseInterface;
use Slim\Exception\HttpNotFoundException;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Helper\ViteManifest;

/**
 * Class Spa
 * @package Xibo\Controller
 */
class Spa extends Base
{
    /**
     * Top-level React sections that should get the SPA shell. Source of truth for this list is
     * the top-level entries of APP_ROUTES in frontend/src/config/appRoutes.ts - add a section
     * here when one is added there.
     *
     * 'library' and 'schedule' already have exact bare GET/POST routes registered elsewhere in
     * lib/routes.php (Library::grid/add, Schedule::grid/add) - registering an optional-trailing
     * pattern for them would register a second exact route for the bare path and fatal every
     * request (FastRoute throws BadRouteException on a duplicate static route, regardless of
     * registration order). They're listed here so publicRoutes/routes-spa.php share one source,
     * but routes-spa.php gives them a mandatory trailing segment instead - see SECTIONS_NO_INDEX.
     *
     * @var string[]
     */
    public const SECTIONS = [
        'welcome', 'dashboard', 'schedule', 'design', 'library', 'displays',
        'administration', 'reporting', 'advanced', 'developer', 'notification-centre',
    ];

    /**
     * Sections from SECTIONS that must NOT get an optional-trailing-segment pattern because the
     * bare path already has an exact PHP route registered elsewhere (see SECTIONS docblock).
     *
     * @var string[]
     */
    public const SECTIONS_NO_INDEX = ['library', 'schedule'];

    /**
     * Standalone React routes declared outside APP_ROUTES
     * (see frontend/src/routes/index.tsx).
     *
     * @var string[]
     */
    public const STANDALONE_ROUTES = ['user/force-change-password', 'application/authorize'];

    /**
     * Build the route pattern strings for every SECTIONS/STANDALONE_ROUTES entry. Used by both
     * lib/routes-spa.php (to register the routes) and State.php (to mark them public), so the
     * two can never drift apart - route patterns are matched as exact strings by
     * WebAuthentication::getPublicRoutes().
     *
     * @return string[]
     */
    public static function getRoutePatterns(): array
    {
        $patterns = [];

        foreach (self::SECTIONS as $section) {
            $patterns[] = in_array($section, self::SECTIONS_NO_INDEX, true)
                ? '/' . $section . '/{path:.*}'
                : '/' . $section . '[/{path:.*}]';
        }

        foreach (self::STANDALONE_ROUTES as $section) {
            $patterns[] = '/' . $section . '[/{path:.*}]';
        }

        return $patterns;
    }

    /**
     * Render the React SPA shell for one of the explicit routes registered in
     * lib/routes-spa.php. Runs through the normal middleware stack, so CsrfGuard and Csp have
     * already put csrfToken/cspNonce on the view before this is called - app-spa.twig picks them
     * up unchanged.
     *
     * @throws HttpNotFoundException if the Vite assets aren't built - same fallback as before.
     */
    public function shell(Request $request, Response $response): ResponseInterface
    {
        $rootUri = $this->getConfig()->rootUri();

        try {
            // Throws if the manifest is present but the entry is missing (assets not built).
            $appJsUrl = ViteManifest::getJsUrl('index.html', $rootUri);
        } catch (\Throwable) {
            throw new HttpNotFoundException($request);
        }

        $this->getState()->template = 'app-spa';
        $this->getState()->setData([
            'appJsUrl' => $appJsUrl,
            'appCssUrls' => ViteManifest::getCssUrls('index.html', $rootUri),
            'assetBase' => ViteManifest::getAssetBase($rootUri),
            'viteClientUrl' => ViteManifest::getClientUrl(),
            'viteRefreshUrl' => ViteManifest::getRefreshUrl(),
        ]);

        // The shell is identical for authed and unauthed users (auth is enforced client-side
        // by requireAuthLoader), so it must never be cached or replayed from bfcache after logout.
        return $this->render($request, $response)->withHeader('Cache-Control', 'no-store');
    }
}
