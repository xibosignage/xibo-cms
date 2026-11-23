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

import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { renderAboutModal } from '../helpers/renderAbout';

import AboutModal from '@/components/layout/UserMenu/AboutModal';
import { testQueryClient } from '@/setupTests';

vi.mock('@/components/ui/modals/Modal');

describe('About window (CMS)', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
  });

  test('is titled About and shows the About content', async () => {
    const { dialog } = await renderAboutModal();

    within(dialog).getByRole('heading', { name: 'Licence' });
  });

  test('Close shuts the window', async () => {
    const user = userEvent.setup();
    const { onClose } = await renderAboutModal();

    await user.click(screen.getByRole('button', { name: 'Close' }));

    expect(onClose).toHaveBeenCalledTimes(1);
  });

  test('pressing Escape shuts the window', async () => {
    const user = userEvent.setup();
    const { onClose } = await renderAboutModal();

    await user.keyboard('{Escape}');

    expect(onClose).toHaveBeenCalledTimes(1);
  });

  test('nothing is shown when the window is told to stay closed', () => {
    render(<AboutModal isOpen={false} onClose={vi.fn()} />);

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    expect(fetch).not.toHaveBeenCalled();
  });

  test('Close is the only button in the window', async () => {
    const { dialog } = await renderAboutModal();

    expect(within(dialog).getAllByRole('button')).toHaveLength(1);
    within(dialog).getByRole('button', { name: 'Close' });
  });
});
