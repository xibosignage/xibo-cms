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

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render } from '@testing-library/react';
import type { ReactElement } from 'react';
import React from 'react';
import { MemoryRouter } from 'react-router-dom';
import { vi } from 'vitest';

import { UserProvider } from '@/context/UserContext';
import type { DatasetDataConnectorSource } from '@/services/datasetApi';
import type { Dataset } from '@/types/dataset';
import type { User } from '@/types/user';

vi.mock('@/components/ui/Notification', () => ({
  notify: {
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
    warning: vi.fn(),
  },
}));

const createTestQueryClient = () =>
  new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
      },
    },
  });

// Pass { user } to render as that user; the default is no user, as before.
export function renderWithProviders(
  ui: ReactElement,
  { route = '/', user = null }: { route?: string; user?: User | null } = {},
) {
  const testQueryClient = createTestQueryClient();

  return render(
    <QueryClientProvider client={testQueryClient}>
      <UserProvider initialUser={user}>
        <MemoryRouter initialEntries={[route]}>{ui}</MemoryRouter>
      </UserProvider>
    </QueryClientProvider>,
  );
}

export const mockDataset = (overrides = {}): Dataset =>
  ({
    dataSetId: 1,
    dataSet: 'Test Dataset',
    description: 'A test description',
    code: 'TEST_01',
    isRemote: 0,
    isRealTime: 0,
    dataConnectorSource: '',
    folderId: null,
    ...overrides,
  }) as unknown as Dataset;

export const mockRealTimeDataset = (overrides = {}): Dataset =>
  mockDataset({
    dataSetId: 2,
    dataSet: 'Real-Time Dataset',
    description: 'A real-time dataset',
    code: 'RT_01',
    isRealTime: 1,
    dataConnectorSource: 'connector-source-1',
    ...overrides,
  });

export const mockDataConnectorSources: DatasetDataConnectorSource[] = [
  { id: 'connector-source-1', name: 'Connector Source 1' },
  { id: 'connector-source-2', name: 'Connector Source 2' },
];
