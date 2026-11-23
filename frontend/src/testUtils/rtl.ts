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

import { screen, waitFor } from '@testing-library/react';
import type { UserEvent } from '@testing-library/user-event';
import { expect } from 'vitest';

// Wait for a confirmation dialog (identified by its heading/body text) to
// close — the only signal available when the modal is an internal
// implementation detail of the page under test (no onClose prop to hook).
export const waitForDialogToClose = async (text: string | RegExp): Promise<void> => {
  await waitFor(() => {
    expect(screen.queryByText(text)).not.toBeInTheDocument();
  });
};

// Wait for an isolated modal's onClose to have been called — the precise
// signal when the test owns the modal's onClose prop directly.
export const waitForClose = async (onClose: (...args: unknown[]) => unknown): Promise<void> => {
  await waitFor(() => {
    expect(onClose).toHaveBeenCalled();
  });
};

// Wait for a row's text, open the first row's "More actions" menu, and wait
// for the menu to render. `sharedAction` must be a menu action that is also
// each row's quick action (Edit, Download), so an open menu shows one more of
// it than before. Then an absent action means the menu rendered without it,
// not that it hadn't opened yet.
export const openFirstRowMenu = async (
  user: UserEvent,
  rowText: string,
  sharedAction: RegExp = /^edit$/i,
): Promise<void> => {
  await screen.findByText(rowText);
  const before = screen.getAllByRole('button', { name: sharedAction }).length;
  await user.click(screen.getAllByRole('button', { name: /more actions/i })[0]!);
  await waitFor(() =>
    expect(screen.getAllByRole('button', { name: sharedAction })).toHaveLength(before + 1),
  );
};

// Wait for a row's text, tick "Select all rows", and wait for it to read as
// ticked. The bulk toolbar renders in the same update as the selection, so an
// absent bulk action is then absent, not late. Don't wait for a bulk action or
// the "N Selected" count instead: a user with no bulk actions gets no toolbar.
export const selectAllRowsAndWait = async (user: UserEvent, rowText: string): Promise<void> => {
  await screen.findByText(rowText);
  const selectAll = screen.getByRole('checkbox', { name: /select all rows/i });
  await user.click(selectAll);
  await waitFor(() => expect(selectAll).toBeChecked());
};
