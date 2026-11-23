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

import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ComponentProps } from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import EnableStatsMediaModal from '../../components/EnableStatsMediaModal';
import { mockEditMedia } from '../mediaTestUtils';

// Single-item "Enable Stats Collection" for one media file. The modal hands the
// chosen value to the page; setMediaEnableStat is tested in
// hooks/useMediaActions.copy-stats.test.tsx. SelectDropdown is real.

vi.mock('@/components/ui/modals/Modal');

const renderModal = (props: Partial<ComponentProps<typeof EnableStatsMediaModal>> = {}) => {
  const onClose = vi.fn();
  const onConfirm = vi.fn();
  const utils = render(
    <EnableStatsMediaModal
      media={{ ...mockEditMedia, enableStat: 'Inherit' }}
      onClose={onClose}
      onConfirm={onConfirm}
      {...props}
    />,
  );
  return { onClose, onConfirm, ...utils };
};

const statsDropdown = () =>
  screen.getByRole('combobox', { name: 'Enable Media Stats Collection?' });

describe('EnableStatsMediaModal', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  test('opens on the setting the media currently has', () => {
    renderModal({ media: { ...mockEditMedia, enableStat: 'On' } });

    expect(statsDropdown()).toHaveTextContent('On');
  });

  test('saving without a change sends the current setting back unchanged', async () => {
    const user = userEvent.setup();
    const { onConfirm } = renderModal({ media: { ...mockEditMedia, enableStat: 'Off' } });

    await user.click(screen.getByRole('button', { name: 'Save' }));

    expect(onConfirm).toHaveBeenCalledWith('Off');
  });

  test.each(['On', 'Off', 'Inherit'])('choosing %s saves that setting', async (choice) => {
    const user = userEvent.setup();
    const start = choice === 'Inherit' ? 'On' : 'Inherit';
    const { onConfirm } = renderModal({ media: { ...mockEditMedia, enableStat: start } });

    await user.click(statsDropdown());
    await user.click(await screen.findByRole('option', { name: choice }));
    await user.click(screen.getByRole('button', { name: 'Save' }));

    expect(onConfirm).toHaveBeenCalledWith(choice);
  });

  test('opening it for a different media item shows that item’s setting', () => {
    const { rerender, onClose, onConfirm } = renderModal({
      media: { ...mockEditMedia, enableStat: 'On' },
    });

    rerender(
      <EnableStatsMediaModal
        media={{ ...mockEditMedia, mediaId: 7, enableStat: 'Off' }}
        onClose={onClose}
        onConfirm={onConfirm}
      />,
    );

    expect(statsDropdown()).toHaveTextContent('Off');
  });

  test('with no media chosen nothing is shown', () => {
    const { container } = renderModal({ media: null });

    expect(container).toBeEmptyDOMElement();
  });

  test('while saving, both buttons are locked and Escape does not close it', async () => {
    const user = userEvent.setup();
    const { onClose } = renderModal({ isLoading: true });

    expect(screen.getByRole('button', { name: 'Save' })).toBeDisabled();
    expect(screen.getByRole('button', { name: 'Cancel' })).toBeDisabled();
    await user.keyboard('{Escape}');

    expect(onClose).not.toHaveBeenCalled();
  });

  test('Cancel closes the modal without saving', async () => {
    const user = userEvent.setup();
    const { onClose, onConfirm } = renderModal();

    await user.click(screen.getByRole('button', { name: 'Cancel' }));

    expect(onClose).toHaveBeenCalledOnce();
    expect(onConfirm).not.toHaveBeenCalled();
  });
});
