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
import { render } from '@testing-library/react';
import { vi } from 'vitest';

import { mockUser } from '../../fixtures/displayProfile';

import { UserProvider } from '@/context/UserContext';
import AddDisplayProfileModal from '@/pages/Displays/DisplayProfile/components/AddDisplayProfileModal';
import { testQueryClient } from '@/setupTests';
import type { DisplayProfile } from '@/types/displayProfile';

interface RenderOptions {
  onClose?: () => void;
  onSave?: (created: DisplayProfile) => void;
}

export const renderAddDisplayProfileModal = (options: RenderOptions = {}) => {
  const { onClose = vi.fn(), onSave = vi.fn() } = options;

  return {
    onClose,
    onSave,
    ...render(
      <QueryClientProvider client={testQueryClient}>
        <UserProvider initialUser={mockUser}>
          <AddDisplayProfileModal onClose={onClose} onSave={onSave} />
        </UserProvider>
      </QueryClientProvider>,
    ),
  };
};
