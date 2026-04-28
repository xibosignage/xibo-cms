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

import { DatasetModals } from '../components/DatasetModals';

import { renderWithProviders } from './DatasetSetup';

// Mock child components to verify they are rendered
vi.mock('../components/AddAndEditDatasetModal', () => ({
  default: () => <div data-testid="edit-modal">Edit Modal</div>,
}));
vi.mock('../components/CopyDatasetModal', () => ({
  default: () => <div data-testid="copy-modal">Copy Modal</div>,
}));
vi.mock('../components/DeleteDatasetModal', () => ({
  default: () => <div data-testid="delete-modal">Delete Modal</div>,
}));

vi.mock('@/components/ui/modals/ShareModal', () => ({
  default: () => <div data-testid="share-modal">Share Modal</div>,
}));
vi.mock('@/components/ui/modals/MoveModal', () => ({
  default: () => <div data-testid="move-modal">Move Modal</div>,
}));
vi.mock('@/components/ui/FolderActionModals', () => ({
  default: () => <div data-testid="folder-action-modals">Folder Actions</div>,
}));

describe('DatasetModals', () => {
  const defaultProps = {
    actions: {
      activeModal: null,
      closeModal: vi.fn(),
      handleRefresh: vi.fn(),
      deleteError: null,
      isDeleting: false,
      isCloning: false,
    },
    selection: {
      selectedDataset: null,
      selectedDatasetId: null,
      itemsToDelete: [],
      itemsToMove: [],
      existingNames: [],
      shareEntityIds: null,
      setShareEntityIds: vi.fn(),
    },
    handlers: {
      confirmDelete: vi.fn(),
      handleConfirmClone: vi.fn(),
      handleConfirmMove: vi.fn(),
    },
    folderActions: {} as never,
  };

  it('renders nothing when activeModal is null', () => {
    renderWithProviders(<DatasetModals {...defaultProps} />);
    expect(screen.queryByTestId('edit-modal')).not.toBeInTheDocument();
    expect(screen.queryByTestId('copy-modal')).not.toBeInTheDocument();
    expect(screen.queryByTestId('delete-modal')).not.toBeInTheDocument();
  });

  it('renders AddAndEditDatasetModal when activeModal is "edit"', () => {
    const props = { ...defaultProps, actions: { ...defaultProps.actions, activeModal: 'edit' } };
    renderWithProviders(<DatasetModals {...props} />);
    expect(screen.getByTestId('edit-modal')).toBeInTheDocument();
    expect(screen.queryByTestId('copy-modal')).not.toBeInTheDocument();
  });

  it('renders CopyDatasetModal when activeModal is "copy"', () => {
    const props = { ...defaultProps, actions: { ...defaultProps.actions, activeModal: 'copy' } };
    renderWithProviders(<DatasetModals {...props} />);
    expect(screen.getByTestId('copy-modal')).toBeInTheDocument();
  });

  it('renders DeleteDatasetModal when activeModal is "delete"', () => {
    const props = { ...defaultProps, actions: { ...defaultProps.actions, activeModal: 'delete' } };
    renderWithProviders(<DatasetModals {...props} />);
    expect(screen.getByTestId('delete-modal')).toBeInTheDocument();
  });

  it('renders ShareModal when activeModal is "share"', () => {
    const props = { ...defaultProps, actions: { ...defaultProps.actions, activeModal: 'share' } };
    renderWithProviders(<DatasetModals {...props} />);

    expect(screen.getByTestId('share-modal')).toBeInTheDocument();
  });
});
