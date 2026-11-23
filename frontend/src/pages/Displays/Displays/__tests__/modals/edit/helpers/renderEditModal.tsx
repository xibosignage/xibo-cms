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

import { QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import type React from 'react';
import { MemoryRouter } from 'react-router-dom';
import { vi } from 'vitest';

import EditDisplayModal from '../../../../components/EditDisplayModal';
import { mockDisplay, mockUser } from '../../../fixtures/display';

import { UserProvider } from '@/context/UserContext';
import { testQueryClient } from '@/setupTests';

// Shared render helper for EditDisplayModal tests.
// vi.mock() calls must still live in each consuming test file — Vitest hoists
// them per-file. This helper only owns the JSX wrapper.
export const renderEditModal = async (
  overrides: Partial<React.ComponentProps<typeof EditDisplayModal>> = {},
) => {
  const defaults = {
    isOpen: true,
    data: mockDisplay,
    onClose: vi.fn(),
    onSave: vi.fn(),
  };
  const utils = render(
    <QueryClientProvider client={testQueryClient}>
      <UserProvider initialUser={mockUser}>
        <MemoryRouter>
          <EditDisplayModal {...defaults} {...overrides} />
        </MemoryRouter>
      </UserProvider>
    </QueryClientProvider>,
  );
  await screen.findByRole('dialog', { name: /edit/i });
  return utils;
};
