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
