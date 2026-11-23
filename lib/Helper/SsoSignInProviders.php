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

namespace Xibo\Helper;

use Slim\Interfaces\RouteParserInterface;
use Xibo\Service\ConfigServiceInterface;

/**
 * The SAML/CAS buttons shown on the login page when that provider is the active authentication and opts in
 * to showing it ($samlSettings['workflow']['showLoginPage'] / $casSettings['showLoginPage']). Only the active
 * provider registers its routes, so settings left behind for another provider never produce a button.
 *
 * Each button can be named with $samlSettings['workflow']['name'] / $casSettings['name'].
 */
class SsoSignInProviders
{
    /**
     * @param ConfigServiceInterface $config
     * @param RouteParserInterface $routeParser
     * @param string $priorRoute the sanitised route to return to after signing in
     * @return array[] in the same shape as the login page's signInProviders
     */
    public static function build(
        ConfigServiceInterface $config,
        RouteParserInterface $routeParser,
        string $priorRoute
    ): array {
        $providers = [];

        if ($config->isSamlLoginPageShown()) {
            $name = self::name($config->samlSettings['workflow']['name'] ?? null, 'SAML');
            $providers[] = [
                'id' => 'saml',
                'type' => 'saml',
                'name' => $name,
                'isPrimary' => false,
                'label' => sprintf(__('Sign in with %s'), $name),
                'url' => $routeParser->urlFor('saml.login', [], array_filter(['priorRoute' => $priorRoute])),
            ];
        }

        if ($config->isCasLoginPageShown()) {
            $name = self::name($config->casSettings['name'] ?? null, 'CAS');
            $providers[] = [
                'id' => 'cas',
                'type' => 'cas',
                'name' => $name,
                'isPrimary' => false,
                'label' => sprintf(__('Sign in with %s'), $name),
                'url' => $routeParser->urlFor('cas.login'),
            ];
        }

        return $providers;
    }

    private static function name(mixed $name, string $default): string
    {
        return (is_string($name) && trim($name) !== '') ? trim($name) : $default;
    }
}
