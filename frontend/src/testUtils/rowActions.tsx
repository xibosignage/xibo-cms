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

import { screen, waitFor, within } from '@testing-library/react';
import type { UserEvent } from '@testing-library/user-event';

// Actions exist as icon and menu item, so lookups are scoped to a row or the menu.

// Exact cell text, so 'Schedule 1' matches neither 'Schedule 10' nor 'Weekly Schedule 1'.
export const getRow = (name: string) => {
  const rows = screen
    .getAllByRole('row')
    .filter((row) => within(row).queryAllByText(name).length > 0);
  if (rows.length !== 1) {
    throw new Error(`Expected one row named "${name}", found ${rows.length}`);
  }
  return rows[0]!;
};

// The first grid load in a file can take several seconds when the whole suite is running.
export const findRow = (name: string) => waitFor(() => getRow(name), { timeout: 10_000 });

export const clickQuickAction = async (user: UserEvent, rowName: string, label: RegExp) => {
  const row = await findRow(rowName);
  await user.click(within(row).getByRole('button', { name: label }));
};

// Role queries scoped to the open menu stay off the whole page, which is slow in jsdom.
const getOpenMenu = () => screen.getByRole('group', { name: /^row actions$/i });

export const openRowMenu = async (user: UserEvent, rowName: string) => {
  const row = await findRow(rowName);
  await user.click(within(row).getByRole('button', { name: /more actions/i }));
  await waitFor(getOpenMenu);
  return row;
};

export const queryMenuItems = (label: RegExp) =>
  within(getOpenMenu()).queryAllByRole('button', { name: label });

export const clickMenuAction = async (user: UserEvent, rowName: string, label: RegExp) => {
  await openRowMenu(user, rowName);
  const [item] = queryMenuItems(label);
  if (!item) {
    throw new Error(`No menu item matching ${label} for row "${rowName}"`);
  }
  await user.click(item);
};

// Confirmations have no title (xibosignage/xibo#4039).
export const openViaQuickAction = async (
  user: UserEvent,
  rowName: string,
  label: RegExp,
  dialogName?: string,
) => {
  await clickQuickAction(user, rowName, label);
  return screen.findByRole('dialog', dialogName ? { name: dialogName } : {});
};

export const openViaMenu = async (
  user: UserEvent,
  rowName: string,
  label: RegExp,
  dialogName?: string,
) => {
  await clickMenuAction(user, rowName, label);
  return screen.findByRole('dialog', dialogName ? { name: dialogName } : {});
};

export const selectAllRows = async (user: UserEvent) => {
  const checkboxes = screen.getAllByRole('checkbox', { name: /select/i });
  await user.click(checkboxes[0]!);
};

export const selectRow = async (user: UserEvent, rowName: string) => {
  const row = await findRow(rowName);
  await user.click(within(row).getByRole('checkbox'));
};
