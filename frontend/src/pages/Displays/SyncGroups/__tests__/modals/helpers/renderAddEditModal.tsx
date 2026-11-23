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

import AddAndEditSyncGroupModal from '../../../components/AddAndEditSyncGroupModal';
import { mockSyncGroup, mockUser } from '../../fixtures/syncGroup';

import { UserProvider } from '@/context/UserContext';
import { testQueryClient } from '@/setupTests';
import type { SyncGroup } from '@/types/syncGroup';

interface RenderOptions {
  mode?: 'add' | 'edit';
  syncGroup?: SyncGroup | null;
  onClose?: () => void;
  onSave?: (saved: SyncGroup) => void;
  onAfterSave?: (saved: SyncGroup) => void;
}

// Renders the AddAndEditSyncGroupModal in isolation with all required
// providers and sensible defaults. Pass overrides per test.
export const renderAddEditModal = (options: RenderOptions = {}) => {
  const {
    mode = 'add',
    syncGroup = mode === 'edit' ? mockSyncGroup : null,
    onClose = vi.fn(),
    onSave = vi.fn(),
    onAfterSave,
  } = options;

  return {
    onClose,
    onSave,
    onAfterSave,
    ...render(
      <QueryClientProvider client={testQueryClient}>
        <UserProvider initialUser={mockUser}>
          <AddAndEditSyncGroupModal
            mode={mode}
            syncGroup={syncGroup}
            onClose={onClose}
            onSave={onSave}
            onAfterSave={onAfterSave}
          />
        </UserProvider>
      </QueryClientProvider>,
    ),
  };
};
