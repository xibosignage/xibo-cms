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
use Xibo\Support\Exception\AccessDeniedException;

/**
 * Shared completion steps for a successful SSO login (SAML, CAS), once the local Xibo
 * User record has been resolved or provisioned.
 */
trait SsoLoginTrait
{
    public function completeSsoLogin(User $user, Request $request, string $provider): User
    {
        // A reserved user never signs in through an identity provider.
        if ($this->getUserFactory()->isReserved((int)$user->userId)) {
            throw new AccessDeniedException(__('Sorry this account does not exist or cannot be authenticated.'));
        }

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
