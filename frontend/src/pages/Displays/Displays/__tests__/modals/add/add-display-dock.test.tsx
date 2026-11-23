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

import { QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type React from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { usePendingConnectToast } from '../../../components/AddDisplay/hooks/usePendingConnect';
import { DisplayModals } from '../../../components/DisplaysModals';
import { mockUser } from '../../fixtures/display';

import { UserProvider } from '@/context/UserContext';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/components/ui/modals/Modal');
vi.mock('@/components/ui/Notification', () => ({
  notify: { success: vi.fn(), error: vi.fn(), info: vi.fn() },
}));

// The wizard itself is covered in add-display.test.tsx. Here it only has to show whether it is
// open and offer the minimize it would offer while waiting for a Player.
vi.mock('../../../components/AddDisplay/AddDisplayModal', () => ({
  default: ({
    isOpen,
    onMinimize,
  }: {
    isOpen?: boolean;
    onMinimize?: (displayName: string) => void;
  }) =>
    isOpen ? (
      <div role="dialog" aria-label="Add Display">
        <button type="button" onClick={() => onMinimize?.('Lobby')}>
          Minimize
        </button>
      </div>
    ) : null,
}));
vi.mock('../../../components/AddDisplay/AddDisplayDock', () => ({
  default: ({ displayName, onMaximize }: { displayName: string; onMaximize: () => void }) => (
    <button type="button" onClick={onMaximize}>
      Dock: {displayName}
    </button>
  ),
}));
vi.mock('../../../components/AddDisplay/hooks/usePendingConnect', () => ({
  usePendingConnectToast: vi.fn(),
}));

// =============================================================================
// Helpers
// =============================================================================

type Props = React.ComponentProps<typeof DisplayModals>;

const handlers = {
  confirmDelete: vi.fn(),
  handleConfirmMove: vi.fn(),
  confirmAuthorise: vi.fn(),
  confirmCheckLicence: vi.fn(),
  confirmRequestScreenShot: vi.fn(),
  confirmCollectNow: vi.fn(),
  confirmWakeOnLan: vi.fn(),
  confirmPurgeAll: vi.fn(),
  confirmTriggerWebhook: vi.fn(),
  confirmSetDefaultLayout: vi.fn(),
  confirmMoveCms: vi.fn(),
  confirmMoveCmsCancel: vi.fn(),
  confirmSetBandwidth: vi.fn(),
  confirmBulkAuthorise: vi.fn(),
  confirmBulkCheckLicence: vi.fn(),
  confirmBulkRequestScreenShot: vi.fn(),
  confirmBulkCollectNow: vi.fn(),
  confirmBulkTriggerWebhook: vi.fn(),
  confirmBulkSetDefaultLayout: vi.fn(),
  confirmSendCommand: vi.fn(),
  confirmBulkMoveCms: vi.fn(),
};

const selection: Props['selection'] = {
  selectedDisplay: null,
  itemsToDelete: [],
  itemsToMove: [],
  actionDisplay: null,
  bulkActionItems: [],
  shareEntityIds: null,
  setShareEntityIds: vi.fn(),
};

/**
 * Renders the modals with the page's activeModal kept in local state, as Displays.tsx does, so
 * opening and closing behave as they do on the page.
 */
const renderModals = ({ canAdd = true }: { canAdd?: boolean } = {}) => {
  let activeModal: string | null = 'add';
  const openModal = vi.fn();
  const closeModal = vi.fn();

  const props = (): Props => ({
    canAdd,
    actions: {
      activeModal,
      openModal,
      closeModal,
      handleRefresh: vi.fn(),
      deleteError: null,
      isDeleting: false,
      isMoving: false,
      isActionPending: false,
      actionError: null,
    },
    selection,
    handlers,
  });

  // DisplayModals reads the signed-in user and the licence usage.
  const withProviders = () => (
    <QueryClientProvider client={testQueryClient}>
      <UserProvider initialUser={mockUser}>
        <DisplayModals {...props()} />
      </UserProvider>
    </QueryClientProvider>
  );

  const view = render(withProviders());

  const setActiveModal = (modal: string | null) => {
    activeModal = modal;
    view.rerender(withProviders());
  };

  openModal.mockImplementation((modal: string) => setActiveModal(modal));
  closeModal.mockImplementation(() => setActiveModal(null));

  return { openModal, setActiveModal };
};

// =============================================================================
// Tests
// =============================================================================

describe('Add Display dock', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  test('minimizing swaps the form for the dock, and the dock brings it back', async () => {
    const user = userEvent.setup();
    renderModals();

    await user.click(screen.getByRole('button', { name: 'Minimize' }));
    expect(screen.queryByRole('dialog', { name: 'Add Display' })).not.toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: 'Dock: Lobby' }));
    expect(screen.getByRole('dialog', { name: 'Add Display' })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /^Dock/ })).not.toBeInTheDocument();
  });

  // e.g. the Add Display button in the page header, pressed while the form is minimized.
  test('opening Add Display while minimized brings the same form back', async () => {
    const user = userEvent.setup();
    const { setActiveModal } = renderModals();

    await user.click(screen.getByRole('button', { name: 'Minimize' }));
    setActiveModal('add');

    expect(screen.getByRole('dialog', { name: 'Add Display' })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /^Dock/ })).not.toBeInTheDocument();
  });

  // The open (or minimized) form watches its own code; the background watcher takes over once
  // it is gone, and only for users who could have started it.
  test('the background watcher runs only once the form is gone, for users who can add', () => {
    const { setActiveModal } = renderModals();
    expect(vi.mocked(usePendingConnectToast).mock.lastCall?.[0]).toBe(false);

    setActiveModal(null);
    expect(vi.mocked(usePendingConnectToast).mock.lastCall?.[0]).toBe(true);
  });

  test('the background watcher never runs for users who cannot add displays', () => {
    const { setActiveModal } = renderModals({ canAdd: false });

    setActiveModal(null);
    expect(vi.mocked(usePendingConnectToast).mock.lastCall?.[0]).toBe(false);
  });
});
