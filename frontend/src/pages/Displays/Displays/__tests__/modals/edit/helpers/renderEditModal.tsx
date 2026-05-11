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
