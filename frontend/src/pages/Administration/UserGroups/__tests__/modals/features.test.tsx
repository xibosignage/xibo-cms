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

import { render, screen, waitFor, waitForElementToBeRemoved } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { TFunction } from 'i18next';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import {
  getFeatureDefinitions,
  getGroupDescriptions,
} from '../../../Users/config/featureDefinitions';
import UserGroupFeaturesModal from '../../components/UserGroupFeaturesModal';
import { buildUserGroup, mockUserGroup } from '../fixtures/userGroup';

import type * as CmsVisibilityModule from '@/hooks/useCmsVisibility';
import { fetchUserGroupById, updateGroupFeatures } from '@/services/userGroupApi';
import { apiError } from '@/testUtils/apiErrors';
import { deferred } from '@/testUtils/deferred';
import { knownFailure } from '@/testUtils/knownFailure';
import type { UserGroup } from '@/types/userGroup';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/userGroupApi');
vi.mock('@/components/ui/modals/Modal');
// The real hook can need a QueryClient, which this test doesn't render.
vi.mock('@/hooks/useCmsVisibility', async (importOriginal) => ({
  ...(await importOriginal<typeof CmsVisibilityModule>()),
  useServiceVisibility: () => () => 'shown',
}));
// featureDefinitions runs for real.

// =============================================================================
// Fixtures / helpers
// =============================================================================

const t = ((key: string) => key) as unknown as TFunction;
const FEATURES = getFeatureDefinitions(t);
const featuresIn = (group: string) => FEATURES.filter((f) => f.group === group);
const FOLDER_FEATURES = featuresIn('folders');
const LIBRARY_FEATURES = featuresIn('library');

const VIEW_FOLDER_TREE = 'View Folder Tree on Grids and Forms';
const RENAME_FOLDERS = 'Rename and Delete existing Folders';

const groupWith = (features: string[]): UserGroup => buildUserGroup({ features });

const renderFeaturesModal = async ({ wait = true }: { wait?: boolean } = {}) => {
  const onClose = vi.fn();
  const onSuccess = vi.fn();
  render(
    <UserGroupFeaturesModal userGroup={mockUserGroup} onClose={onClose} onSuccess={onSuccess} />,
  );
  if (wait) await waitForElementToBeRemoved(() => screen.queryByText('Loading...'));
  return { onClose, onSuccess, dialog: screen.getByRole('dialog') };
};

const groupCheckbox = (label: string) =>
  screen.getByRole('checkbox', { name: `Enable all in ${label}` }) as HTMLInputElement;

// =============================================================================
// Tests
// =============================================================================

describe('UserGroupFeaturesModal - loading and navigation', () => {
  beforeEach(() => vi.clearAllMocks());

  test('title names the group', async () => {
    vi.mocked(fetchUserGroupById).mockResolvedValue(groupWith([]));
    await renderFeaturesModal();

    expect(screen.getByRole('dialog')).toHaveAccessibleName('Features for Editors');
    expect(fetchUserGroupById).toHaveBeenCalledWith(mockUserGroup.groupId);
  });

  test('shows "Loading..." and disables Save while features load', async () => {
    const pending = deferred<UserGroup>();
    vi.mocked(fetchUserGroupById).mockReturnValue(pending.promise);
    await renderFeaturesModal({ wait: false });

    expect(screen.getByText('Loading...')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Save' })).toBeDisabled();

    pending.resolve(groupWith([]));
    await waitFor(() => expect(screen.getByRole('button', { name: 'Save' })).toBeEnabled());
  });

  test('category tabs render with Content selected and its groups listed', async () => {
    vi.mocked(fetchUserGroupById).mockResolvedValue(groupWith([]));
    await renderFeaturesModal();

    for (const name of ['Content', 'Scheduling', 'Displays', 'Reports', 'Users', 'System']) {
      expect(screen.getByRole('tab', { name })).toBeInTheDocument();
    }
    expect(screen.getByRole('tab', { name: 'Content' })).toHaveAttribute('aria-selected', 'true');
    expect(groupCheckbox('Folders')).toBeInTheDocument();
    expect(groupCheckbox('Library')).toBeInTheDocument();
  });

  test('switching tab shows that category instead', async () => {
    const user = userEvent.setup();
    vi.mocked(fetchUserGroupById).mockResolvedValue(groupWith([]));
    await renderFeaturesModal();

    await user.click(screen.getByRole('tab', { name: 'Users' }));

    expect(screen.getByRole('tab', { name: 'Users' })).toHaveAttribute('aria-selected', 'true');
    expect(groupCheckbox('Notifications')).toBeInTheDocument();
    expect(
      screen.queryByRole('checkbox', { name: 'Enable all in Folders' }),
    ).not.toBeInTheDocument();
  });

  test('expanding a group lists its features with the current state', async () => {
    const user = userEvent.setup();
    vi.mocked(fetchUserGroupById).mockResolvedValue(groupWith(['folder.view']));
    await renderFeaturesModal();
    const description = getGroupDescriptions(t).folders!;
    expect(screen.getByText(description)).toBeInTheDocument();

    // No aria-expanded yet (xibosignage/xibo#4127), so assert via the child checkboxes appearing.
    await user.click(screen.getByRole('button', { name: /^folders/i }));

    expect(screen.getByRole('checkbox', { name: `Enable ${VIEW_FOLDER_TREE}` })).toBeChecked();
    expect(screen.getByRole('checkbox', { name: `Enable ${RENAME_FOLDERS}` })).not.toBeChecked();
    expect(screen.queryByText(description)).not.toBeInTheDocument();
  });

  // Known issue: xibosignage/xibo#4127
  describe('Pinned until https://github.com/xibosignage/xibo/issues/4127 is fixed', () => {
    // Group headings don't report whether they're expanded.
    knownFailure(
      'a feature group heading reports that it is collapsed',
      async () => {
        vi.mocked(fetchUserGroupById).mockResolvedValue(groupWith([]));
        await renderFeaturesModal();
        // Control: the heading button is there.
        return screen.getByRole('button', { name: /^folders/i });
      },
      (heading) => expect(heading).toHaveAttribute('aria-expanded', 'false'),
    );
  });
});

describe('UserGroupFeaturesModal - toggling', () => {
  beforeEach(() => vi.clearAllMocks());

  test('ticking a single feature changes only that feature', async () => {
    const user = userEvent.setup();
    vi.mocked(fetchUserGroupById).mockResolvedValue(groupWith([]));
    await renderFeaturesModal();
    await user.click(screen.getByRole('button', { name: /^folders/i }));

    await user.click(screen.getByRole('checkbox', { name: `Enable ${VIEW_FOLDER_TREE}` }));

    expect(screen.getByRole('checkbox', { name: `Enable ${VIEW_FOLDER_TREE}` })).toBeChecked();
    expect(screen.getByRole('checkbox', { name: `Enable ${RENAME_FOLDERS}` })).not.toBeChecked();
  });

  test('the group checkbox turns every feature on, then all off', async () => {
    const user = userEvent.setup();
    vi.mocked(fetchUserGroupById).mockResolvedValue(groupWith([]));
    await renderFeaturesModal();
    await user.click(screen.getByRole('button', { name: /^folders/i }));

    await user.click(groupCheckbox('Folders'));
    for (const f of FOLDER_FEATURES) {
      expect(screen.getByRole('checkbox', { name: `Enable ${f.title}` })).toBeChecked();
    }

    await user.click(groupCheckbox('Folders'));
    for (const f of FOLDER_FEATURES) {
      expect(screen.getByRole('checkbox', { name: `Enable ${f.title}` })).not.toBeChecked();
    }
  });

  test('a partly-enabled group shows an indeterminate checkbox', async () => {
    vi.mocked(fetchUserGroupById).mockResolvedValue(groupWith(['folder.view']));
    await renderFeaturesModal();

    expect(groupCheckbox('Folders')).not.toBeChecked();
    expect(groupCheckbox('Folders').indeterminate).toBe(true);
  });
});

describe('UserGroupFeaturesModal - save', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(updateGroupFeatures).mockResolvedValue(undefined);
  });

  test('Save sends every enabled feature', async () => {
    const user = userEvent.setup();
    vi.mocked(fetchUserGroupById).mockResolvedValue(groupWith(['folder.view']));
    await renderFeaturesModal();

    await user.click(groupCheckbox('Library'));
    await user.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() => expect(updateGroupFeatures).toHaveBeenCalledTimes(1));
    const [groupId, sent] = vi.mocked(updateGroupFeatures).mock.calls[0]!;
    const expected = ['folder.view', ...LIBRARY_FEATURES.map((f) => f.feature)];
    expect(groupId).toBe(mockUserGroup.groupId);
    expect(sent).toHaveLength(expected.length);
    expect(sent).toEqual(expect.arrayContaining(expected));
  });

  test('unticking one feature saves the others without it', async () => {
    const user = userEvent.setup();
    const all = FOLDER_FEATURES.map((f) => f.feature);
    vi.mocked(fetchUserGroupById).mockResolvedValue(groupWith(all));
    await renderFeaturesModal();
    await user.click(screen.getByRole('button', { name: /^folders/i }));

    await user.click(screen.getByRole('checkbox', { name: `Enable ${RENAME_FOLDERS}` }));
    await user.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() => expect(updateGroupFeatures).toHaveBeenCalledTimes(1));
    const sent = vi.mocked(updateGroupFeatures).mock.calls[0]![1];
    expect(sent).not.toContain('folder.modify');
    expect(sent).toHaveLength(all.length - 1);
    expect(sent).toEqual(expect.arrayContaining(all.filter((f) => f !== 'folder.modify')));
  });

  test('unticking a group checkbox saves none of its features', async () => {
    const user = userEvent.setup();
    const all = FOLDER_FEATURES.map((f) => f.feature);
    vi.mocked(fetchUserGroupById).mockResolvedValue(groupWith([...all, 'library.view']));
    await renderFeaturesModal();

    await user.click(groupCheckbox('Folders'));
    await user.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() => expect(updateGroupFeatures).toHaveBeenCalledTimes(1));
    expect(updateGroupFeatures).toHaveBeenCalledWith(mockUserGroup.groupId, ['library.view']);
  });

  test('success calls onSuccess then onClose', async () => {
    const user = userEvent.setup();
    vi.mocked(fetchUserGroupById).mockResolvedValue(groupWith([]));
    const { onClose, onSuccess } = await renderFeaturesModal();

    await user.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() => expect(onClose).toHaveBeenCalledTimes(1));
    expect(onSuccess).toHaveBeenCalledTimes(1);
    expect(onSuccess.mock.invocationCallOrder[0]!).toBeLessThan(
      onClose.mock.invocationCallOrder[0]!,
    );
  });

  test('Save shows "Saving..." and is disabled while in progress', async () => {
    const user = userEvent.setup();
    const pending = deferred<void>();
    vi.mocked(updateGroupFeatures).mockReturnValue(pending.promise);
    vi.mocked(fetchUserGroupById).mockResolvedValue(groupWith([]));
    const { onClose } = await renderFeaturesModal();

    await user.click(screen.getByRole('button', { name: 'Save' }));

    expect(await screen.findByRole('button', { name: 'Saving...' })).toBeDisabled();

    pending.resolve();
    await waitFor(() => expect(onClose).toHaveBeenCalled());
  });

  test('server error is shown and the modal stays open', async () => {
    const user = userEvent.setup();
    vi.mocked(updateGroupFeatures).mockRejectedValue(apiError('Not allowed', 403));
    vi.mocked(fetchUserGroupById).mockResolvedValue(groupWith([]));
    const { onClose } = await renderFeaturesModal();

    await user.click(screen.getByRole('button', { name: 'Save' }));

    expect(await screen.findByRole('alert')).toHaveTextContent('Not allowed');
    expect(updateGroupFeatures).toHaveBeenCalledWith(mockUserGroup.groupId, []);
    expect(onClose).not.toHaveBeenCalled();
  });

  // Known issue: xibosignage/xibo#4120
  describe('Pinned until https://github.com/xibosignage/xibo/issues/4120 is fixed', () => {
    // A failed load leaves every feature unticked and Save enabled, so saving wipes them all.
    const failedLoad = async () => {
      vi.mocked(fetchUserGroupById).mockRejectedValue(new Error('boom'));
      await renderFeaturesModal();
      // Control: the form finished loading and offers Save.
      return screen.getByRole('button', { name: 'Save' });
    };

    knownFailure('a failed load says the features could not be loaded', failedLoad, () =>
      expect(screen.getByRole('alert')).toBeInTheDocument(),
    );

    knownFailure('Save is disabled after a failed load', failedLoad, (save) =>
      expect(save).toBeDisabled(),
    );
  });

  test('Cancel closes without saving', async () => {
    const user = userEvent.setup();
    vi.mocked(fetchUserGroupById).mockResolvedValue(groupWith([]));
    const { onClose } = await renderFeaturesModal();

    await user.click(screen.getByRole('button', { name: 'Cancel' }));

    expect(onClose).toHaveBeenCalledTimes(1);
    expect(updateGroupFeatures).not.toHaveBeenCalled();
  });
});
