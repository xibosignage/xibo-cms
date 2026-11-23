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

// Render and lookup helpers for the ShareModal suites. Actions and DOM state
// only — no assertions.
//
// Test files using this helper must declare (vi.mock is hoisted per file):
//   vi.mock('@/components/ui/modals/Modal');
//   vi.mock('@/hooks/useDebounce');
//   vi.mock('@/components/ui/Notification', ...)
//   vi.mock('@/services/permissionsApi', ...)
//   vi.mock('@/services/userApi', ...)

import { render, screen, within } from '@testing-library/react';
import type { UserEvent } from '@testing-library/user-event';
import type { ComponentProps } from 'react';
import { MemoryRouter } from 'react-router-dom';
import { vi } from 'vitest';

import ShareModal from '@/components/ui/modals/ShareModal';

type ShareModalProps = ComponentProps<typeof ShareModal>;

export const SHARE_TITLE = 'Share Layout';

// DataTable reads the URL, so the modal needs a router around it.
export const shareModalElement = (props: Partial<ShareModalProps> & { onClose: () => void }) => (
  <MemoryRouter>
    <ShareModal title={SHARE_TITLE} entityType="Layout" entityId={7} {...props} />
  </MemoryRouter>
);

export const renderShareModal = (props: Partial<ShareModalProps> = {}) => {
  const onClose = vi.fn();
  const result = render(shareModalElement({ onClose, ...props }));
  return {
    ...result,
    onClose,
    rerenderWith: (next: Partial<ShareModalProps>) =>
      result.rerender(shareModalElement({ onClose, ...props, ...next })),
  };
};

// Waits for a permission row to load and returns it.
export const findPermissionRow = (name: string) =>
  screen.findByRole('row', { name: new RegExp(name) });

// Accessibility fallback (xibosignage/xibo#4066): the permission checkboxes
// have no accessible name, so they are found by position inside their row.
const PERMISSION_INDEX = { View: 0, Edit: 1, Delete: 2 } as const;
export type Permission = keyof typeof PERMISSION_INDEX;

export const permissionBox = (rowName: string, permission: Permission) => {
  const row = screen.getByRole('row', { name: new RegExp(rowName) });
  return within(row).getAllByRole('checkbox')[PERMISSION_INDEX[permission]] as HTMLInputElement;
};

// Accessibility fallback (xibosignage/xibo#4066): the name search has no
// accessible name; it is the only textbox while no dropdown is open.
export const nameSearch = () => screen.getByRole('textbox');

export const saveButton = () => screen.getByRole('button', { name: /^Save/ });

// Opens a SelectDropdown by its label and picks an option.
export const chooseOption = async (user: UserEvent, dropdown: string, option: string) => {
  await user.click(screen.getByRole('combobox', { name: dropdown }));
  await user.click(await screen.findByRole('option', { name: option }));
};
