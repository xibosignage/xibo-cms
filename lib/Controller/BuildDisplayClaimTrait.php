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

use Respect\Validation\Validator as v;
use Xibo\Entity\Display;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Sanitizer\SanitizerInterface;

/**
 * Shared claim-building logic for display add flows.
 *
 * Expects the consuming class to have $displayGroupFactory and $folderFactory properties and to
 * extend Base (for getUser).
 */
trait BuildDisplayClaimTrait
{
    /**
     * Build the claim array from the operator's form input.
     *
     * For an activation code the claim is cached alongside the code, so that RegisterDisplay can
     * apply the settings (name, folder, authorisation, group) at registration time. For Manual
     * Configure it is applied as soon as the form is saved, once the Player has connected. The
     * same rules decide what the operator may set in both.
     *
     * @return array
     * @throws AccessDeniedException
     * @throws InvalidArgumentException
     */
    private function buildDisplayClaim(SanitizerInterface $sanitizedParams): array
    {
        $claim = [];

        $claim['userId'] = $this->getUser()->userId;

        if ($sanitizedParams->getCheckbox('authorised')) {
            $claim['licensed'] = 1;
        }

        if ($this->getUser()->featureEnabled('displays.modify')) {
            $displayName = trim($sanitizedParams->getString('displayName') ?? '');
            if ($displayName !== '') {
                // Checked here rather than left to the display: RegisterDisplay saves without
                // validating, so a name the column can't hold would stop the Player registering.
                if (!v::stringType()->length(null, Display::MAX_NAME_LENGTH)->validate($displayName)) {
                    throw new InvalidArgumentException(__('Name cannot exceed 50 characters'), 'displayName');
                }

                $claim['display'] = $displayName;
            }

            $folderId = $sanitizedParams->getInt('folderId');
            if (!empty($folderId)) {
                // Same rule as moving a display group into root.
                if ($folderId === 1) {
                    $this->checkRootFolderAllowSave();
                }

                // The settings are applied without an edit check on the display, so the folder
                // has to be one this user could have picked.
                if (!$this->getUser()->checkViewable($this->folderFactory->getById($folderId, 0))) {
                    throw new AccessDeniedException(__('Access Denied to Folder'));
                }

                $claim['folderId'] = $folderId;
            }

            $displayGroupId = $sanitizedParams->getInt('displayGroupId');
            if (!empty($displayGroupId)) {
                $displayGroup = $this->displayGroupFactory->getById($displayGroupId);

                if (!$this->getUser()->checkEditable($displayGroup)) {
                    throw new AccessDeniedException(__('Access Denied to DisplayGroup'));
                }

                if ($displayGroup->isDynamic == 1) {
                    throw new InvalidArgumentException(
                        __('Displays cannot be manually assigned to a Dynamic Group'),
                        'displayGroupId'
                    );
                }

                $claim['displayGroupId'] = $displayGroupId;
            }
        }

        return $claim;
    }
}
