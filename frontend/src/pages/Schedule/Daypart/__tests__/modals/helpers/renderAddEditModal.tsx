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

import AddAndEditDaypartModal from '../../../components/AddAndEditDaypartModal';
import { mockDaypart, mockUser } from '../../fixtures/daypart';

import { UserProvider } from '@/context/UserContext';
import { testQueryClient } from '@/setupTests';
import type { Daypart } from '@/types/daypart';

interface RenderOptions {
  type?: 'add' | 'edit';
  data?: Daypart | null;
  onClose?: () => void;
  onSave?: (saved: Daypart) => void;
  isOpen?: boolean;
}

export const renderAddEditModal = (options: RenderOptions = {}) => {
  const {
    type = 'add',
    data = type === 'edit' ? mockDaypart : null,
    onClose = vi.fn(),
    onSave = vi.fn(),
    isOpen = true,
  } = options;

  return {
    onClose,
    onSave,
    ...render(
      <QueryClientProvider client={testQueryClient}>
        <UserProvider initialUser={mockUser}>
          <AddAndEditDaypartModal
            type={type}
            isOpen={isOpen}
            data={data}
            onClose={onClose}
            onSave={onSave}
          />
        </UserProvider>
      </QueryClientProvider>,
    ),
  };
};
