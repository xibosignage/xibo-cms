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
import { MemoryRouter } from 'react-router-dom';
import { vi, describe, test, expect, beforeEach } from 'vitest';

import { DisplayModals } from '../../../components/DisplaysModals';
import { buildDisplay, mockDisplay, mockUser } from '../../fixtures/display';

import { UserProvider } from '@/context/UserContext';
import { fetchLicenceUsage } from '@/services/displaysApi';
import type * as DisplaysApiModule from '@/services/displaysApi';
import { testQueryClient } from '@/setupTests';
import { buildCurrentUser, PERSONAS } from '@/testUtils/personas';
import type { User } from '@/types/user';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/components/ui/modals/Modal');
vi.mock('@/services/displaysApi', async (importOriginal) => ({
  ...(await importOriginal<typeof DisplaysApiModule>()),
  fetchLicenceUsage: vi.fn(),
}));

// Sub-component modals are tested in isolation in their own test files.
// Stub them here so DisplayModals can render without their full dependencies.
vi.mock('../../../components/AddDisplay/AddDisplayModal', () => ({ default: () => null }));
vi.mock('../../../components/AddDisplay/AddDisplayDock', () => ({ default: () => null }));
vi.mock('../../../components/AddDisplay/hooks/usePendingConnect', () => ({
  usePendingConnectToast: () => {},
}));
vi.mock('../../../components/EditDisplayModal', () => ({ default: () => null }));
vi.mock('../../../components/DeleteDisplayModal', () => ({ default: () => null }));
vi.mock('../../../components/CollectNowModal', () => ({ default: () => null }));
vi.mock('../../../components/TriggerWebhookModal', () => ({ default: () => null }));
vi.mock('../../../components/SetBandwidthModal', () => ({ default: () => null }));
vi.mock('../../../components/SetDefaultLayoutModal', () => ({ default: () => null }));
vi.mock('../../../components/TransferCmsModal', () => ({ default: () => null }));
vi.mock('../../../components/SendCommandModal', () => ({ default: () => null }));
vi.mock('../../../components/ManageGroupMembershipModal', () => ({ default: () => null }));
vi.mock('../../../components/AssignLayoutModal', () => ({ default: () => null }));
vi.mock('../../../components/AssignMediaModal', () => ({ default: () => null }));
vi.mock('@/components/ui/modals/MoveModal', () => ({ default: () => null }));
vi.mock('@/components/ui/modals/ShareModal', () => ({ default: () => null }));
vi.mock('@/components/ui/modals/ScheduleEventModal', () => ({ default: () => null }));

// =============================================================================
// Helpers
// =============================================================================

type ActiveModal =
  | 'authorise'
  | 'checkLicence'
  | 'requestScreenShot'
  | 'wakeOnLan'
  | 'purgeAll'
  | 'moveCmsCancel'
  | 'bulkAuthorise'
  | 'bulkCheckLicence'
  | 'bulkRequestScreenShot'
  | 'bulkCollectNow'
  | string;

const renderModals = ({
  activeModal,
  closeModal = vi.fn(),
  actionDisplay = mockDisplay,
  bulkActionItems = [mockDisplay],
  handlers = {},
  user = mockUser,
}: {
  activeModal: ActiveModal;
  closeModal?: () => void;
  actionDisplay?: ReturnType<typeof buildDisplay> | null;
  bulkActionItems?: ReturnType<typeof buildDisplay>[];
  handlers?: Partial<React.ComponentProps<typeof DisplayModals>['handlers']>;
  user?: User;
}) => {
  const defaultHandlers: React.ComponentProps<typeof DisplayModals>['handlers'] = {
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
    ...handlers,
  };

  return render(
    <MemoryRouter>
      <QueryClientProvider client={testQueryClient}>
        <UserProvider initialUser={user}>
          <DisplayModals
            actions={{
              activeModal,
              openModal: vi.fn(),
              closeModal,
              handleRefresh: vi.fn(),
              deleteError: null,
              isDeleting: false,
              isMoving: false,
              isActionPending: false,
              actionError: null,
            }}
            selection={{
              selectedDisplay: null,
              itemsToDelete: [],
              itemsToMove: [],
              actionDisplay,
              bulkActionItems,
              shareEntityIds: null,
              setShareEntityIds: vi.fn(),
            }}
            handlers={defaultHandlers}
          />
        </UserProvider>
      </QueryClientProvider>
    </MemoryRouter>,
  );
};

// =============================================================================
// Tests
// =============================================================================

describe('DisplayModals — inline action modals', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(fetchLicenceUsage).mockResolvedValue({
      maxLicensed: null,
      currentlyLicensed: 2,
      available: null,
    });
  });

  // ---------------------------------------------------------------------------
  // Authorise modal — every display licence in use
  // ---------------------------------------------------------------------------
  test('authorise modal with every licence in use links to Billing and disables confirm', async () => {
    vi.mocked(fetchLicenceUsage).mockResolvedValue({
      maxLicensed: 3,
      currentlyLicensed: 3,
      available: 0,
    });
    renderModals({ activeModal: 'authorise', actionDisplay: buildDisplay({ licensed: 0 }) });

    expect(await screen.findByText(/every display licence is in use \(3\)/i)).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Add more licences in Billing' })).toHaveAttribute(
      'href',
      '/administration/billing',
    );
    expect(screen.getByRole('button', { name: /yes, authorise/i })).toBeDisabled();
  });

  test('authorise modal hides the Billing link from users without Billing access', async () => {
    vi.mocked(fetchLicenceUsage).mockResolvedValue({
      maxLicensed: 3,
      currentlyLicensed: 3,
      available: 0,
    });
    const noBilling = buildCurrentUser(PERSONAS.user, {
      features: { 'displays.view': true, 'displays.modify': true },
    });
    renderModals({
      activeModal: 'authorise',
      actionDisplay: buildDisplay({ licensed: 0 }),
      user: noBilling,
    });

    expect(await screen.findByText(/every display licence is in use \(3\)/i)).toBeInTheDocument();
    expect(
      screen.queryByRole('link', { name: 'Add more licences in Billing' }),
    ).not.toBeInTheDocument();
  });

  test('authorise modal with a free licence keeps confirm enabled', async () => {
    vi.mocked(fetchLicenceUsage).mockResolvedValue({
      maxLicensed: 3,
      currentlyLicensed: 2,
      available: 1,
    });
    renderModals({ activeModal: 'authorise', actionDisplay: buildDisplay({ licensed: 0 }) });

    await vi.waitFor(() => expect(fetchLicenceUsage).toHaveBeenCalled());
    expect(screen.queryByText(/every display licence is in use/i)).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: /yes, authorise/i })).toBeEnabled();
  });

  // ---------------------------------------------------------------------------
  // Authorise modal — unlicensed display shows "Authorise Display"
  // ---------------------------------------------------------------------------
  test('authorise modal shows "Authorise Display" for an unlicensed display', () => {
    const unlicensed = buildDisplay({ licensed: 0 });
    renderModals({ activeModal: 'authorise', actionDisplay: unlicensed });

    screen.getByRole('heading', { name: /authorise display/i });
    screen.getByRole('button', { name: /yes, authorise/i });
  });

  // ---------------------------------------------------------------------------
  // Authorise modal — licensed display shows "Unauthorise Display"
  // ---------------------------------------------------------------------------
  test('authorise modal shows "Unauthorise Display" for a licensed display', () => {
    const licensed = buildDisplay({ licensed: 1 });
    renderModals({ activeModal: 'authorise', actionDisplay: licensed });

    screen.getByRole('heading', { name: /unauthorise display/i });
    screen.getByRole('button', { name: /yes, unauthorise/i });
  });

  // ---------------------------------------------------------------------------
  // Confirm button in the authorise modal calls confirmAuthorise.
  // ---------------------------------------------------------------------------
  test('clicking confirm in authorise modal calls confirmAuthorise', async () => {
    const confirmAuthorise = vi.fn();
    const user = userEvent.setup();
    const unlicensed = buildDisplay({ licensed: 0 });

    renderModals({
      activeModal: 'authorise',
      actionDisplay: unlicensed,
      handlers: { confirmAuthorise },
    });

    await user.click(screen.getByRole('button', { name: /yes, authorise/i }));

    expect(confirmAuthorise).toHaveBeenCalledWith(unlicensed);
  });

  // ---------------------------------------------------------------------------
  // Cancel in the authorise modal calls closeModal.
  // ---------------------------------------------------------------------------
  test('Cancel in authorise modal calls closeModal', async () => {
    const closeModal = vi.fn();
    const user = userEvent.setup();

    renderModals({ activeModal: 'authorise', closeModal });

    await user.click(screen.getByRole('button', { name: /^cancel$/i }));

    expect(closeModal).toHaveBeenCalledOnce();
  });

  // ---------------------------------------------------------------------------
  // Confirm in checkLicence calls confirmCheckLicence.
  // ---------------------------------------------------------------------------
  test('clicking confirm in checkLicence modal calls confirmCheckLicence', async () => {
    const confirmCheckLicence = vi.fn();
    const user = userEvent.setup();

    renderModals({ activeModal: 'checkLicence', handlers: { confirmCheckLicence } });

    await user.click(screen.getByRole('button', { name: /check licence/i }));

    expect(confirmCheckLicence).toHaveBeenCalledWith(mockDisplay);
  });

  // ---------------------------------------------------------------------------
  // Confirm in requestScreenShot calls confirmRequestScreenShot.
  // ---------------------------------------------------------------------------
  test('clicking confirm in requestScreenShot calls confirmRequestScreenShot', async () => {
    const confirmRequestScreenShot = vi.fn();
    const user = userEvent.setup();

    renderModals({ activeModal: 'requestScreenShot', handlers: { confirmRequestScreenShot } });

    await user.click(screen.getByRole('button', { name: /request screenshot/i }));

    expect(confirmRequestScreenShot).toHaveBeenCalledWith(mockDisplay);
  });

  // ---------------------------------------------------------------------------
  // Confirm in wakeOnLan calls confirmWakeOnLan.
  // ---------------------------------------------------------------------------
  test('clicking confirm in wakeOnLan calls confirmWakeOnLan', async () => {
    const confirmWakeOnLan = vi.fn();
    const user = userEvent.setup();

    renderModals({ activeModal: 'wakeOnLan', handlers: { confirmWakeOnLan } });

    await user.click(screen.getByRole('button', { name: /yes, wake on lan/i }));

    expect(confirmWakeOnLan).toHaveBeenCalledWith(mockDisplay);
  });

  // ---------------------------------------------------------------------------
  // Confirm in purgeAll calls confirmPurgeAll.
  // ---------------------------------------------------------------------------
  test('clicking confirm in purgeAll calls confirmPurgeAll', async () => {
    const confirmPurgeAll = vi.fn();
    const user = userEvent.setup();

    renderModals({ activeModal: 'purgeAll', handlers: { confirmPurgeAll } });

    await user.click(screen.getByRole('button', { name: /yes, purge all/i }));

    expect(confirmPurgeAll).toHaveBeenCalledWith(mockDisplay);
  });

  // ---------------------------------------------------------------------------
  // Confirm in moveCmsCancel calls confirmMoveCmsCancel.
  // ---------------------------------------------------------------------------
  test('clicking Yes in moveCmsCancel calls confirmMoveCmsCancel', async () => {
    const confirmMoveCmsCancel = vi.fn();
    const user = userEvent.setup();

    renderModals({ activeModal: 'moveCmsCancel', handlers: { confirmMoveCmsCancel } });

    await user.click(screen.getByRole('button', { name: /^yes$/i }));

    expect(confirmMoveCmsCancel).toHaveBeenCalledWith(mockDisplay);
  });
});

describe('DisplayModals — bulk inline action modals', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
  });

  // ---------------------------------------------------------------------------
  // bulkAuthorise — confirm calls handler with items; cancel calls closeModal.
  // ---------------------------------------------------------------------------
  test('clicking confirm in bulkAuthorise calls confirmBulkAuthorise with bulkActionItems', async () => {
    const confirmBulkAuthorise = vi.fn();
    const user = userEvent.setup();

    renderModals({ activeModal: 'bulkAuthorise', handlers: { confirmBulkAuthorise } });

    screen.getByRole('heading', { name: /toggle authorise/i });
    await user.click(screen.getByRole('button', { name: /yes, toggle authorise/i }));

    expect(confirmBulkAuthorise).toHaveBeenCalledWith([mockDisplay]);
  });

  test('Cancel in bulkAuthorise calls closeModal', async () => {
    const closeModal = vi.fn();
    const user = userEvent.setup();

    renderModals({ activeModal: 'bulkAuthorise', closeModal });

    await user.click(screen.getByRole('button', { name: /^cancel$/i }));

    expect(closeModal).toHaveBeenCalledOnce();
  });

  // ---------------------------------------------------------------------------
  // bulkCheckLicence — confirm calls handler with items; cancel calls closeModal.
  // ---------------------------------------------------------------------------
  test('clicking confirm in bulkCheckLicence calls confirmBulkCheckLicence with bulkActionItems', async () => {
    const confirmBulkCheckLicence = vi.fn();
    const user = userEvent.setup();

    renderModals({ activeModal: 'bulkCheckLicence', handlers: { confirmBulkCheckLicence } });

    screen.getByRole('heading', { name: /check commercial licence/i });
    await user.click(screen.getByRole('button', { name: /^check licence$/i }));

    expect(confirmBulkCheckLicence).toHaveBeenCalledWith([mockDisplay]);
  });

  test('Cancel in bulkCheckLicence calls closeModal', async () => {
    const closeModal = vi.fn();
    const user = userEvent.setup();

    renderModals({ activeModal: 'bulkCheckLicence', closeModal });

    await user.click(screen.getByRole('button', { name: /^cancel$/i }));

    expect(closeModal).toHaveBeenCalledOnce();
  });

  // ---------------------------------------------------------------------------
  // bulkRequestScreenShot — confirm calls handler with items; cancel calls closeModal.
  // ---------------------------------------------------------------------------
  test('clicking confirm in bulkRequestScreenShot calls confirmBulkRequestScreenShot with bulkActionItems', async () => {
    const confirmBulkRequestScreenShot = vi.fn();
    const user = userEvent.setup();

    renderModals({
      activeModal: 'bulkRequestScreenShot',
      handlers: { confirmBulkRequestScreenShot },
    });

    screen.getByRole('heading', { name: /request screen shot/i });
    await user.click(screen.getByRole('button', { name: /request screenshot/i }));

    expect(confirmBulkRequestScreenShot).toHaveBeenCalledWith([mockDisplay]);
  });

  test('Cancel in bulkRequestScreenShot calls closeModal', async () => {
    const closeModal = vi.fn();
    const user = userEvent.setup();

    renderModals({ activeModal: 'bulkRequestScreenShot', closeModal });

    await user.click(screen.getByRole('button', { name: /^cancel$/i }));

    expect(closeModal).toHaveBeenCalledOnce();
  });

  // ---------------------------------------------------------------------------
  // bulkCollectNow — confirm calls handler with items; cancel calls closeModal.
  // ---------------------------------------------------------------------------
  test('clicking confirm in bulkCollectNow calls confirmBulkCollectNow with bulkActionItems', async () => {
    const confirmBulkCollectNow = vi.fn();
    const user = userEvent.setup();

    renderModals({ activeModal: 'bulkCollectNow', handlers: { confirmBulkCollectNow } });

    screen.getByRole('heading', { name: /^collect now$/i });
    await user.click(screen.getByRole('button', { name: /^collect now$/i }));

    expect(confirmBulkCollectNow).toHaveBeenCalledWith([mockDisplay]);
  });

  test('Cancel in bulkCollectNow calls closeModal', async () => {
    const closeModal = vi.fn();
    const user = userEvent.setup();

    renderModals({ activeModal: 'bulkCollectNow', closeModal });

    await user.click(screen.getByRole('button', { name: /^cancel$/i }));

    expect(closeModal).toHaveBeenCalledOnce();
  });
});
