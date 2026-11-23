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

import { screen } from '@testing-library/react';
import { describe, it, expect, vi } from 'vitest';

import { DatasetDataModals } from '../subPages/Data/components/DatasetDataModals';

import { renderWithProviders } from './DatasetSetup';

vi.mock('../subPages/Data/components/AddAndEditDatasetDataModal', () => ({
  AddAndEditDataModal: () => <div data-testid="edit-data-modal">Edit Data Modal</div>,
}));
vi.mock('../subPages/Data/components/CopyDatasetDataModal', () => ({
  default: () => <div data-testid="copy-data-modal">Copy Data Modal</div>,
}));
vi.mock('../subPages/Data/components/DeleteDatasetDataModal', () => ({
  DeleteDatasetDataModal: () => <div data-testid="delete-data-modal">Delete Data Modal</div>,
}));

describe('DatasetDataModals', () => {
  const defaultProps = {
    datasetId: '1',
    columnsSchema: [],
    actions: {
      activeModal: null,
      closeModal: vi.fn(),
      handleRefresh: vi.fn(),
      deleteError: null,
      isDeleting: false,
      isCloning: false,
    },
    selection: {
      selectedData: null,
      itemsToDelete: [],
      rowToDeleteId: null,
    },
    handlers: {
      handleConfirmCopy: vi.fn(),
      confirmDelete: vi.fn(),
    },
  };

  it('renders nothing when activeModal is null', () => {
    renderWithProviders(<DatasetDataModals {...defaultProps} />);
    expect(screen.queryByTestId('edit-data-modal')).not.toBeInTheDocument();
    expect(screen.queryByTestId('copy-data-modal')).not.toBeInTheDocument();
    expect(screen.queryByTestId('delete-data-modal')).not.toBeInTheDocument();
  });

  it('renders AddAndEditDataModal when activeModal is "edit"', () => {
    const props = { ...defaultProps, actions: { ...defaultProps.actions, activeModal: 'edit' } };
    renderWithProviders(<DatasetDataModals {...props} />);
    expect(screen.getByTestId('edit-data-modal')).toBeInTheDocument();
  });

  it('renders CopyDatasetDataModal when activeModal is "copy"', () => {
    const props = { ...defaultProps, actions: { ...defaultProps.actions, activeModal: 'copy' } };
    renderWithProviders(<DatasetDataModals {...props} />);
    expect(screen.getByTestId('copy-data-modal')).toBeInTheDocument();
  });

  it('renders DeleteDatasetDataModal when activeModal is "delete" and itemsToDelete is non-empty', () => {
    const fakeRow = { id: '1' };
    const props = {
      ...defaultProps,
      actions: { ...defaultProps.actions, activeModal: 'delete' },
      selection: {
        ...defaultProps.selection,
        itemsToDelete: [fakeRow],
        rowToDeleteId: '1',
      },
    };
    renderWithProviders(<DatasetDataModals {...props} />);
    expect(screen.getByTestId('delete-data-modal')).toBeInTheDocument();
  });

  it('does not render DeleteDatasetDataModal when itemsToDelete is empty', () => {
    const props = {
      ...defaultProps,
      actions: { ...defaultProps.actions, activeModal: 'delete' },
    };
    renderWithProviders(<DatasetDataModals {...props} />);
    expect(screen.queryByTestId('delete-data-modal')).not.toBeInTheDocument();
  });
});
