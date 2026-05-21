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

import { screen } from '@testing-library/react';
import { describe, it, expect, vi } from 'vitest';

import { DatasetColumnModals } from '../subPages/Columns/components/DatasetColumnsModals';

import { renderWithProviders } from './DatasetSetup';

vi.mock('../subPages/Columns/components/AddAndEditDatasetColumnModal', () => ({
  AddAndEditDatasetColumnModal: () => <div data-testid="edit-column-modal">Edit Column Modal</div>,
}));
vi.mock('../subPages/Columns/components/CopyDatasetColumnModal', () => ({
  default: () => <div data-testid="copy-column-modal">Copy Column Modal</div>,
}));
vi.mock('../subPages/Columns/components/DeleteDatasetColumnModal', () => ({
  DeleteDatasetColumnModal: () => <div data-testid="delete-column-modal">Delete Column Modal</div>,
}));

describe('DatasetColumnModals', () => {
  const defaultProps = {
    datasetId: '1',
    actions: {
      activeModal: null,
      closeModal: vi.fn(),
      handleRefresh: vi.fn(),
      deleteError: null,
      isDeleting: false,
      isCloning: false,
    },
    selection: {
      selectedColumn: null,
      columnToDeleteId: 5,
      itemsToDelete: [],
      existingNames: [],
    },
    handlers: {
      handleConfirmCopy: vi.fn(),
      confirmDelete: vi.fn(),
    },
  };

  it('renders nothing when activeModal is null', () => {
    renderWithProviders(<DatasetColumnModals {...defaultProps} />);
    expect(screen.queryByTestId('edit-column-modal')).not.toBeInTheDocument();
    expect(screen.queryByTestId('copy-column-modal')).not.toBeInTheDocument();
    expect(screen.queryByTestId('delete-column-modal')).not.toBeInTheDocument();
  });

  it('renders AddAndEditDatasetColumnModal when activeModal is "edit"', () => {
    const props = { ...defaultProps, actions: { ...defaultProps.actions, activeModal: 'edit' } };
    renderWithProviders(<DatasetColumnModals {...props} />);
    expect(screen.getByTestId('edit-column-modal')).toBeInTheDocument();
  });

  it('renders CopyDatasetColumnModal when activeModal is "copy"', () => {
    const props = { ...defaultProps, actions: { ...defaultProps.actions, activeModal: 'copy' } };
    renderWithProviders(<DatasetColumnModals {...props} />);
    expect(screen.getByTestId('copy-column-modal')).toBeInTheDocument();
  });

  it('renders DeleteDatasetColumnModal when activeModal is "delete" and columnToDeleteId is set', () => {
    const props = { ...defaultProps, actions: { ...defaultProps.actions, activeModal: 'delete' } };
    renderWithProviders(<DatasetColumnModals {...props} />);
    expect(screen.getByTestId('delete-column-modal')).toBeInTheDocument();
  });

  it('does not render DeleteDatasetColumnModal when columnToDeleteId is null', () => {
    const props = {
      ...defaultProps,
      actions: { ...defaultProps.actions, activeModal: 'delete' },
      selection: { ...defaultProps.selection, columnToDeleteId: null },
    };
    renderWithProviders(<DatasetColumnModals {...props} />);
    expect(screen.queryByTestId('delete-column-modal')).not.toBeInTheDocument();
  });
});
