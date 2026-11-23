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

use Slim\Http\ServerRequest as Request;
use Xibo\Entity\User;
use Xibo\Service\LogServiceInterface;

trait LogoutTrait
{
    public function completeLogoutFlow(User $user, Session $session, LogServiceInterface $log, Request $request)
    {
        $user->touch();
        $session->setIsExpired(1);

        $log->audit('User', $user->userId, 'User logout', [
            'UserAgent' => $request->getHeader('User-Agent')
        ]);

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();

        // Commit immediately - don't leave it to request shutdown, where a request already
        // mid-flight for this session id could still overwrite the row with its own pre-logout
        // in-memory state. See Session::persist().
        $session->persist();
    }
}
