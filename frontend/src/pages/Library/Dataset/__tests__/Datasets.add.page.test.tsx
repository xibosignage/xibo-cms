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

// The Datasets page with its real modals: Datasets.restricted.test.tsx stubs
// DatasetModals out, so the add flow lives here.

import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, test, vi } from 'vitest';

import Datasets from '../Datasets';

import { mockDataset, renderWithProviders } from './DatasetSetup';

import { createDataset, fetchDataConnectorSource, fetchDataset } from '@/services/datasetApi';
import { testQueryClient } from '@/setupTests';
import { escapeRegExp } from '@/testUtils/escapeRegExp';
import { PERSONAS, buildCurrentUser } from '@/testUtils/personas';
import type { Dataset } from '@/types/dataset';

vi.mock('@/services/folderApi');
vi.mock('@/services/datasetApi');
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));
vi.mock('../hooks/useDatasetFilterOptions', () => ({
  useDatasetFilterOptions: () => ({ filterOptions: [], isLoading: false }),
}));
vi.mock('@/components/ui/FolderActionModals', () => ({ default: () => null }));
vi.mock('@/components/ui/forms/SelectFolder', () => ({ default: () => null }));
vi.mock('@/components/ui/modals/Modal');

// A content manager working in their own home folder (not the root), so the
// Add button is enabled without super-admin rights and the new dataset lands
// in that folder.
const HOME_FOLDER_ID = 5;
const contentManager = buildCurrentUser(PERSONAS.contentManager, { homeFolderId: HOME_FOLDER_ID });

const fullPermissions = { view: 1, edit: 1, delete: 1, modifyPermissions: 1 };
const prices = mockDataset({
  dataSetId: 1,
  dataSet: 'Prices',
  description: 'Daily fuel prices',
  code: 'PRICES_01',
  userPermissions: fullPermissions,
});
const stock = mockDataset({
  dataSetId: 2,
  dataSet: 'Stock',
  description: 'Warehouse stock levels',
  code: 'STOCK_01',
  userPermissions: fullPermissions,
});
const created = mockDataset({
  dataSetId: 3,
  dataSet: 'Opening Hours',
  description: 'Branch opening hours',
  code: 'HOURS_01',
  folderId: HOME_FOLDER_ID,
  userPermissions: fullPermissions,
});

const mockDatasetRows = (rows: Dataset[]) =>
  vi.mocked(fetchDataset).mockResolvedValue({ rows, totalCount: rows.length });

// The accessible name of a table row is its cells' text joined by spaces.
const rowFor = (dataset: Dataset) =>
  new RegExp(
    [dataset.dataSet, dataset.description, dataset.code].map((v) => escapeRegExp(v!)).join('\\s+'),
  );

describe('Listing and adding datasets on the Datasets page', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockDatasetRows([prices, stock]);
    vi.mocked(fetchDataConnectorSource).mockResolvedValue([]);
  });

  test(
    'the table shows each dataset with its name, description and code',
    { tags: ['smoke'] },
    async () => {
      renderWithProviders(<Datasets />, { user: contentManager });

      await screen.findByRole('row', { name: rowFor(prices) });
      expect(screen.getByRole('row', { name: rowFor(stock) })).toBeInTheDocument();
    },
  );

  test(
    'adding a dataset from the page sends the entered values and shows the new dataset',
    { tags: ['smoke'] },
    async () => {
      const user = userEvent.setup();
      vi.mocked(createDataset).mockResolvedValueOnce(created);

      renderWithProviders(<Datasets />, { user: contentManager });
      await screen.findByRole('row', { name: rowFor(prices) });

      await user.click(screen.getByRole('button', { name: 'Add Dataset' }));
      const dialog = await screen.findByRole('dialog', { name: 'Add Dataset' });

      // Once saved, the refreshed list includes the new dataset.
      mockDatasetRows([prices, stock, created]);

      await user.type(screen.getByRole('textbox', { name: 'Name' }), 'Opening Hours');
      await user.type(screen.getByRole('textbox', { name: 'Code' }), 'HOURS_01');
      await user.type(screen.getByRole('textbox', { name: 'Description' }), 'Branch opening hours');
      await user.click(screen.getByRole('button', { name: 'Save' }));

      await waitFor(() => {
        expect(createDataset).toHaveBeenCalledWith({
          testDataSetId: 0,
          dataSet: 'Opening Hours',
          description: 'Branch opening hours',
          code: 'HOURS_01',
          folderId: HOME_FOLDER_ID,
          isRemote: false,
          isRealTime: false,
          dataConnectorSource: '',
          method: 'GET',
          uri: '',
          postData: '',
          authentication: 'none',
          username: '',
          password: '',
          customHeaders: '',
          userAgent: '',
          sourceId: '1',
          dataRoot: '',
          csvSeparator: ',',
          ignoreFirstRow: false,
          summarize: 'none',
          summarizeField: '',
          refreshRate: 0,
          clearRate: 1,
          truncateOnEmpty: 0,
          runsAfter: 0,
          rowLimit: 0,
          limitPolicy: 'stop',
        });
      });
      expect(createDataset).toHaveBeenCalledTimes(1);

      await waitFor(() => expect(dialog).not.toBeInTheDocument());
      await screen.findByRole('row', { name: rowFor(created) });
    },
  );
});
