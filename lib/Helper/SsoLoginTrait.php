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

namespace Xibo\Helper;

use Slim\Http\ServerRequest as Request;
use Xibo\Entity\User;

/**
 * Shared completion steps for a successful SSO login (SAML, CAS), once the local Xibo
 * User record has been resolved or provisioned.
 */
trait SsoLoginTrait
{
    public function completeSsoLogin(User $user, Request $request, string $provider): User
    {
        // Load User
        $user = $this->getUser(
            $user->userId,
            $request->getAttribute('ip_address'),
            $this->getSession()->get('sessionHistoryId')
        );

        // Overwrite our stored user with this new object.
        $this->setUserForRequest($user);

        // Switch Session ID's
        $this->getSession()->setIsExpired(0);
        $this->getSession()->regenerateSessionId();
        $this->getSession()->setUser($user->userId);

        $user->touch();

        // Audit Log
        $this->getLog()->audit('User', $user->userId, 'Login Granted via ' . $provider, [
            'UserAgent' => $request->getHeader('User-Agent')
        ]);

        return $user;
    }
}
