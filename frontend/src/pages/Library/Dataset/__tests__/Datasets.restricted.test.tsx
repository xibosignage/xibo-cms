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

// Move needs the Folders feature (xibo-cms PR #3875). These tests run as a
// content manager, not a super admin: hasFeature() lets a super admin through
// without reading any features, so only a restricted user can show the gate.

import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, test, vi } from 'vitest';

import Datasets from '../Datasets';

import { mockDataset, renderWithProviders } from './DatasetSetup';

import { fetchDataset } from '@/services/datasetApi';
import { PERSONAS, buildCurrentUser } from '@/testUtils/personas';
import { openFirstRowMenu, selectAllRowsAndWait } from '@/testUtils/rtl';
import type { Dataset } from '@/types/dataset';

vi.mock('@/services/folderApi');
vi.mock('@/services/datasetApi', () => ({
  fetchDataset: vi.fn(),
  exportDatasetCsv: vi.fn(),
}));
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));
vi.mock('../hooks/useDatasetActions', () => ({
  useDatasetActions: () => ({
    isDeleting: false,
    deleteError: null,
    setDeleteError: vi.fn(),
    isCloning: false,
    isClearingCache: false,
    confirmDelete: vi.fn(),
    confirmClearCache: vi.fn(),
    handleConfirmClone: vi.fn(),
    handleConfirmMove: vi.fn(),
  }),
}));
vi.mock('../hooks/useDatasetFilterOptions', () => ({
  useDatasetFilterOptions: () => ({ filterOptions: [], isLoading: false }),
}));
vi.mock('../components/DatasetModals', () => ({ DatasetModals: () => null }));
vi.mock('@/components/ui/FolderActionModals', () => ({ default: () => null }));

// A content manager can edit datasets and, as shipped, has the Folders feature.
// No settings, like the rest of this suite, which renders with no user at all.
const withFolders = buildCurrentUser(PERSONAS.contentManager);
const withoutFolders = buildCurrentUser(PERSONAS.contentManager, {
  features: { 'folder.view': false },
});

// Every dataset can be fully acted on, so a missing Move can only come from
// the user's features.
const fullPermissions = { view: 1, edit: 1, delete: 1, modifyPermissions: 1 };
const datasets: Dataset[] = [
  mockDataset({ dataSetId: 1, dataSet: 'Prices', userPermissions: fullPermissions }),
  mockDataset({ dataSetId: 2, dataSet: 'Stock', userPermissions: fullPermissions }),
];

describe('Datasets: Move needs folder access', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(fetchDataset).mockResolvedValue({ rows: datasets, totalCount: datasets.length });
  });

  test('a user without folder access gets no Move in the row menu', async () => {
    const user = userEvent.setup();
    renderWithProviders(<Datasets />, { user: withoutFolders });

    await openFirstRowMenu(user, 'Prices');

    expect(screen.queryByRole('button', { name: /^move$/i })).not.toBeInTheDocument();
  });

  test('a user with folder access gets Move in the row menu', async () => {
    const user = userEvent.setup();
    renderWithProviders(<Datasets />, { user: withFolders });

    await openFirstRowMenu(user, 'Prices');

    expect(screen.getByRole('button', { name: /^move$/i })).toBeInTheDocument();
  });

  test('a user without folder access gets no Move in the bulk menu', async () => {
    const user = userEvent.setup();
    renderWithProviders(<Datasets />, { user: withoutFolders });

    await selectAllRowsAndWait(user, 'Prices');

    expect(screen.queryByRole('button', { name: /^move$/i })).not.toBeInTheDocument();
  });

  test('a user with folder access gets Move in the bulk menu', async () => {
    const user = userEvent.setup();
    renderWithProviders(<Datasets />, { user: withFolders });

    await selectAllRowsAndWait(user, 'Prices');

    expect(screen.getByRole('button', { name: /^move$/i })).toBeInTheDocument();
  });
});
