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

import { render, screen, waitFor } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import type { ComponentProps } from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import AssignCampaignModal from '../../components/AssignCampaignModal';

import { fetchCampaigns } from '@/services/campaignApi';
import type { FetchCampaignResponse } from '@/services/campaignApi';
import type { Campaign } from '@/types/campaign';

// ─── Mocks ────────────────────────────────────────────────────────────────────

// The real SelectDropdown is used on purpose: stubbing it as a native <select>
// is how four dropdown faults went unnoticed elsewhere.
vi.mock('@/components/ui/modals/Modal');
vi.mock('@/services/campaignApi', () => ({
  fetchCampaigns: vi.fn(),
}));

// ─── Fixtures ─────────────────────────────────────────────────────────────────

const campaign = (campaignId: number, name: string) =>
  ({ campaignId, campaign: name }) as unknown as Campaign;

// Two campaigns, so picking one proves the id comes from the clicked row and
// not from whichever happens to be first.
const TWO_CAMPAIGNS = {
  rows: [campaign(31, 'Spring Promo'), campaign(47, 'Lobby Loop')],
  totalCount: 2,
} as unknown as FetchCampaignResponse;

// ─── Helpers ──────────────────────────────────────────────────────────────────

const renderModal = (props: Partial<ComponentProps<typeof AssignCampaignModal>> = {}) => {
  const onClose = vi.fn();
  const onConfirm = vi.fn();
  const utils = render(<AssignCampaignModal onClose={onClose} onConfirm={onConfirm} {...props} />);
  return { onClose, onConfirm, ...utils };
};

const pickCampaign = async (user: UserEvent, name: string) => {
  // The placeholder only changes once the campaigns have arrived.
  await screen.findByText('Select campaign');
  await user.click(screen.getByRole('combobox', { name: 'Campaign' }));
  await user.click(await screen.findByRole('option', { name }));
};

// ─── Tests ────────────────────────────────────────────────────────────────────

describe('AssignCampaignModal', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(fetchCampaigns).mockResolvedValue(TWO_CAMPAIGNS);
  });

  test('opening the modal loads the campaigns to choose from', async () => {
    const user = userEvent.setup();
    renderModal();

    await screen.findByText('Select campaign');
    await user.click(screen.getByRole('combobox', { name: 'Campaign' }));

    expect(await screen.findByRole('option', { name: 'Spring Promo' })).toBeInTheDocument();
    expect(screen.getByRole('option', { name: 'Lobby Loop' })).toBeInTheDocument();
    expect(fetchCampaigns).toHaveBeenCalledOnce();
  });

  test('saving sends the id of the campaign the user picked, as a number', async () => {
    const user = userEvent.setup();
    const { onConfirm } = renderModal();

    await pickCampaign(user, 'Lobby Loop');
    await user.click(screen.getByRole('button', { name: 'Save' }));

    expect(onConfirm).toHaveBeenCalledWith(47);
    // The dropdown works in strings; the page's assign call expects a number.
    expect(typeof vi.mocked(onConfirm).mock.calls[0]![0]).toBe('number');
  });

  test('saving without picking a campaign asks the user to pick one and sends nothing', async () => {
    const user = userEvent.setup();
    const { onConfirm } = renderModal();

    await screen.findByText('Select campaign');
    await user.click(screen.getByRole('button', { name: 'Save' }));

    expect(await screen.findByText('Please select a campaign')).toBeInTheDocument();
    expect(onConfirm).not.toHaveBeenCalled();
  });

  test('picking a campaign after the warning clears it', async () => {
    const user = userEvent.setup();
    renderModal();

    await screen.findByText('Select campaign');
    await user.click(screen.getByRole('button', { name: 'Save' }));
    await screen.findByText('Please select a campaign');

    await pickCampaign(user, 'Spring Promo');

    expect(screen.queryByText('Please select a campaign')).not.toBeInTheDocument();
  });

  test('when the campaigns cannot be loaded the modal says so', async () => {
    vi.spyOn(console, 'error').mockImplementation(() => {});
    vi.mocked(fetchCampaigns).mockRejectedValue(new Error('Network down'));
    renderModal();

    expect(await screen.findByText('Failed to load campaigns')).toBeInTheDocument();
  });

  test('with no campaigns at all the dropdown says there is nothing to choose', async () => {
    vi.mocked(fetchCampaigns).mockResolvedValue({
      rows: [],
      totalCount: 0,
    } as unknown as FetchCampaignResponse);
    renderModal();

    expect(await screen.findByText('No campaigns available')).toBeInTheDocument();
  });

  test('reopening the modal forgets the previous choice and reloads the list', async () => {
    const user = userEvent.setup();
    const { onConfirm, rerender, onClose } = renderModal();

    await pickCampaign(user, 'Lobby Loop');

    rerender(<AssignCampaignModal isOpen={false} onClose={onClose} onConfirm={onConfirm} />);
    rerender(<AssignCampaignModal isOpen onClose={onClose} onConfirm={onConfirm} />);

    await waitFor(() => expect(fetchCampaigns).toHaveBeenCalledTimes(2));
    await screen.findByText('Select campaign');
    await user.click(screen.getByRole('button', { name: 'Save' }));

    expect(onConfirm).not.toHaveBeenCalled();
    expect(await screen.findByText('Please select a campaign')).toBeInTheDocument();
  });

  test('while the assignment is being saved both buttons are locked', async () => {
    renderModal({ isLoading: true });

    await screen.findByText('Select campaign');
    expect(screen.getByRole('button', { name: 'Saving…' })).toBeDisabled();
    expect(screen.getByRole('button', { name: 'Cancel' })).toBeDisabled();
  });

  test('Cancel closes the modal without assigning anything', async () => {
    const user = userEvent.setup();
    const { onClose, onConfirm } = renderModal();

    await screen.findByText('Select campaign');
    await user.click(screen.getByRole('button', { name: 'Cancel' }));

    expect(onClose).toHaveBeenCalledOnce();
    expect(onConfirm).not.toHaveBeenCalled();
  });
});
