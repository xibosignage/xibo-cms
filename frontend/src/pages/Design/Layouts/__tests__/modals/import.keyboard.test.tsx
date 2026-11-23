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
import { render, screen, waitFor } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import ImportLayoutModal from '../../components/ImportLayoutModal';
import { mockUser } from '../layoutTestUtils';

import { UserProvider } from '@/context/UserContext';
import { importLayout, updateLayout } from '@/services/layoutsApi';
import type { ImportLayoutResponse } from '@/services/layoutsApi';
import { testQueryClient } from '@/setupTests';
import { deferred } from '@/testUtils/deferred';
import { knownFailure } from '@/testUtils/knownFailure';

// ─── Mocks ────────────────────────────────────────────────────────────────────

vi.mock('@/services/layoutsApi', () => ({
  importLayout: vi.fn(),
  updateLayout: vi.fn(),
}));
// The real Modal saves an auto-submit preference through userApi; nothing here uses it.
vi.mock('@/services/userApi', () => ({
  autoSubmitPrefQueryKey: (id: string) => ['userPref', 'autoSubmit', id],
  saveAutoSubmitPreference: vi.fn().mockResolvedValue(undefined),
}));
vi.mock('@/components/ui/forms/SelectFolder', () => ({ default: () => null }));
vi.mock('@/components/ui/forms/TagInput', () => ({ default: () => null }));

// ─── Fixtures and helpers ─────────────────────────────────────────────────────

const zip = (name: string) => new File(['PK'], name, { type: 'application/zip' });
const imported = (id: number) => ({ files: [{ id }] }) as unknown as ImportLayoutResponse;

const renderModal = () =>
  render(
    <QueryClientProvider client={testQueryClient}>
      <UserProvider initialUser={{ ...mockUser, homeFolderId: 7 }}>
        <ImportLayoutModal onClose={vi.fn()} onSuccess={vi.fn()} />
      </UserProvider>
    </QueryClientProvider>,
  );

const dropFiles = async (user: UserEvent, ...files: File[]) => {
  // react-dropzone's hidden file input: the only one in the modal.
  await user.upload(document.querySelector<HTMLInputElement>('input[type="file"]')!, files);
};

// Press Tab until the given button has focus, as a keyboard user would.
const tabTo = async (user: UserEvent, target: HTMLElement) => {
  for (let i = 0; i < 50 && document.activeElement !== target; i++) await user.tab();
  return document.activeElement === target;
};

// ─── Tests ────────────────────────────────────────────────────────────────────

describe('ImportLayoutModal — removing a file with the keyboard while importing', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(updateLayout).mockResolvedValue({} as never);
  });

  // Control: the keyboard really can reach and press Remove File mid-import, so the
  // knownFailure below describes a path users can take, not a test-only one.
  test('a keyboard user can reach Remove File on a waiting file while another imports', async () => {
    const user = userEvent.setup();
    const first = deferred<ImportLayoutResponse>();
    vi.mocked(importLayout).mockReturnValueOnce(first.promise).mockResolvedValue(imported(901));
    renderModal();

    await dropFiles(user, zip('One.zip'), zip('Two.zip'));
    await waitFor(() => expect(importLayout).toHaveBeenCalledOnce());
    const removeTwo = screen.getAllByRole('button', { name: 'Remove File' })[1]!;

    expect(removeTwo).toBeEnabled();
    expect(await tabTo(user, removeTwo)).toBe(true);
    await user.keyboard('{Enter}');

    expect(screen.queryByDisplayValue('Two')).not.toBeInTheDocument();
    first.resolve(imported(900));
    await waitFor(() => expect(screen.getByRole('button', { name: 'Done' })).toBeEnabled());
  });

  // Open — xibosignage/xibo#4059.
  knownFailure(
    'a waiting file removed with the keyboard is not imported',
    async () => {
      const user = userEvent.setup();
      const first = deferred<ImportLayoutResponse>();
      vi.mocked(importLayout).mockReturnValueOnce(first.promise).mockResolvedValue(imported(901));
      renderModal();

      await dropFiles(user, zip('One.zip'), zip('Two.zip'));
      await waitFor(() => expect(importLayout).toHaveBeenCalledOnce());
      const removeTwo = screen.getAllByRole('button', { name: 'Remove File' })[1]!;
      expect(await tabTo(user, removeTwo)).toBe(true);
      await user.keyboard('{Enter}');
      expect(screen.queryByDisplayValue('Two')).not.toBeInTheDocument();

      first.resolve(imported(900));
      await waitFor(() => expect(screen.getByRole('button', { name: 'Done' })).toBeEnabled());
      return vi.mocked(importLayout).mock.calls.map((c) => c[0].name);
    },
    (namesSent) => expect(namesSent).toEqual(['One']),
  );
});
