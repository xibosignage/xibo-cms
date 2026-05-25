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

import { screen } from '@testing-library/react';
import type { UserEvent } from '@testing-library/user-event';

import { mockDisplayGroup } from '../fixtures/displayGroup';

// Waits for the table row to appear, clicks the Edit quick-action button,
// waits for the modal to mount, and returns the dialog element.
export const openEditModal = async (user: UserEvent) => {
  await screen.findByText(mockDisplayGroup.displayGroup);
  await user.click(screen.getByRole('button', { name: /edit/i }));
  return screen.findByRole('dialog');
};
