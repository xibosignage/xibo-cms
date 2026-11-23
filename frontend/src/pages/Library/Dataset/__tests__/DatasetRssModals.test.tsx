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

import { DatasetRssModals } from '../subPages/Rss/components/DatasetRssModals';

import { renderWithProviders } from './DatasetSetup';

vi.mock('../subPages/Rss/components/AddAndEditDatasetRssModal', () => ({
  AddAndEditDatasetRssModal: () => <div data-testid="edit-rss-modal">Edit RSS Modal</div>,
}));
vi.mock('../subPages/Rss/components/CopyDatasetRssModal', () => ({
  default: () => <div data-testid="copy-rss-modal">Copy RSS Modal</div>,
}));
vi.mock('../subPages/Rss/components/DeleteDatasetRssModal', () => ({
  DeleteDatasetRssModal: () => <div data-testid="delete-rss-modal">Delete RSS Modal</div>,
}));

describe('DatasetRssModals', () => {
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
      selectedRss: null,
      rssToDeleteId: 5,
      itemsToDelete: [],
      existingNames: [],
    },
    handlers: {
      handleConfirmCopy: vi.fn(),
      confirmDelete: vi.fn(),
    },
  };

  it('renders nothing when activeModal is null', () => {
    renderWithProviders(<DatasetRssModals {...defaultProps} />);
    expect(screen.queryByTestId('edit-rss-modal')).not.toBeInTheDocument();
    expect(screen.queryByTestId('copy-rss-modal')).not.toBeInTheDocument();
    expect(screen.queryByTestId('delete-rss-modal')).not.toBeInTheDocument();
  });

  it('renders AddAndEditDatasetRssModal when activeModal is "edit"', () => {
    const props = { ...defaultProps, actions: { ...defaultProps.actions, activeModal: 'edit' } };
    renderWithProviders(<DatasetRssModals {...props} />);
    expect(screen.getByTestId('edit-rss-modal')).toBeInTheDocument();
  });

  it('renders CopyDatasetRssModal when activeModal is "copy"', () => {
    const props = { ...defaultProps, actions: { ...defaultProps.actions, activeModal: 'copy' } };
    renderWithProviders(<DatasetRssModals {...props} />);
    expect(screen.getByTestId('copy-rss-modal')).toBeInTheDocument();
  });

  it('renders DeleteDatasetRssModal when activeModal is "delete" and rssToDeleteId is set', () => {
    const props = { ...defaultProps, actions: { ...defaultProps.actions, activeModal: 'delete' } };
    renderWithProviders(<DatasetRssModals {...props} />);
    expect(screen.getByTestId('delete-rss-modal')).toBeInTheDocument();
  });

  it('does not render DeleteDatasetRssModal when rssToDeleteId is null', () => {
    const props = {
      ...defaultProps,
      actions: { ...defaultProps.actions, activeModal: 'delete' },
      selection: { ...defaultProps.selection, rssToDeleteId: null },
    };
    renderWithProviders(<DatasetRssModals {...props} />);
    expect(screen.queryByTestId('delete-rss-modal')).not.toBeInTheDocument();
  });
});
