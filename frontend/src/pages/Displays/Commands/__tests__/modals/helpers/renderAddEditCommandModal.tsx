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
import { render } from '@testing-library/react';
import { vi } from 'vitest';

import AddEditCommandModal from '../../../components/AddEditCommandModal';
import { mockCommand, mockUser } from '../../fixtures/command';

import { UserProvider } from '@/context/UserContext';
import { testQueryClient } from '@/setupTests';
import type { Command } from '@/types/command';

interface RenderOptions {
  mode?: 'add' | 'edit';
  command?: Command | null;
  onClose?: () => void;
  onSave?: (saved: Command) => void;
}

export const renderAddEditCommandModal = (options: RenderOptions = {}) => {
  const {
    mode = 'add',
    command = mode === 'edit' ? mockCommand : null,
    onClose = vi.fn(),
    onSave = vi.fn(),
  } = options;

  return {
    onClose,
    onSave,
    ...render(
      <QueryClientProvider client={testQueryClient}>
        <UserProvider initialUser={mockUser}>
          <AddEditCommandModal mode={mode} command={command} onClose={onClose} onSave={onSave} />
        </UserProvider>
      </QueryClientProvider>,
    ),
  };
};
