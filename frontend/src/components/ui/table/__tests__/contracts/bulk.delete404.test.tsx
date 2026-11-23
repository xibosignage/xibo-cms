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

// =============================================================================
// Test type: Contract test
// Every page's bulk delete sends every selected item, and counts one that
// someone else already deleted (a 404) as deleted, not as a failure.
// xibo-cms PR #3892 added that rule to each page's action hook separately, so
// every hook carries its own copy of the counting and message code. This file
// covers all 24 page hooks with that confirmDelete shape, each with a 404 and
// a real server error. Deletes made inside page components (dataset columns,
// data and RSS, menu board categories and products, module templates) are not
// hooks and are not covered here.
//
// After a partial failure the selection is cleared and the modal stays open
// for Retry. Media's hook narrows what Retry sends to the items that failed;
// every other hook leaves the queue alone, so Retry sends them all again and
// the ones already gone come back as 404s, counted as deleted.
//
// Each row mocks only its own delete call. The hooks are rendered directly,
// so a failure names the page and the hook, not a modal three layers up.
// =============================================================================

// The shared notify mock must be imported before any hook that uses notify.
// eslint-disable-next-line import/order -- must load before the hooks so their notify is the mock
import { mockNotifySuccess } from '@/testUtils/notifyMock';

import { act, renderHook } from '@testing-library/react';
import type { TFunction } from 'i18next';
import type { ReactNode } from 'react';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, test, vi, type Mock } from 'vitest';

// vi.mock factories are hoisted above this line, so the mocks they use must be hoisted too.
type Procedure = (...args: unknown[]) => unknown;
const del = vi.hoisted(() => ({
  application: vi.fn<Procedure>(),
  campaign: vi.fn<Procedure>(),
  media: vi.fn<Procedure>(),
  font: vi.fn<Procedure>(),
  reportSchedule: vi.fn<Procedure>(),
  savedReport: vi.fn<Procedure>(),
  tag: vi.fn<Procedure>(),
  task: vi.fn<Procedure>(),
  userGroup: vi.fn<Procedure>(),
  resolution: vi.fn<Procedure>(),
  layout: vi.fn<Procedure>(),
  command: vi.fn<Procedure>(),
  displayGroup: vi.fn<Procedure>(),
  displayProfile: vi.fn<Procedure>(),
  display: vi.fn<Procedure>(),
  playerVersion: vi.fn<Procedure>(),
  syncGroup: vi.fn<Procedure>(),
  dataset: vi.fn<Procedure>(),
  menuBoard: vi.fn<Procedure>(),
  playlist: vi.fn<Procedure>(),
  notification: vi.fn<Procedure>(),
  daypart: vi.fn<Procedure>(),
  event: vi.fn<Procedure>(),
}));

// A service module with its delete function swapped for one of the mocks above.
const override = vi.hoisted(
  () =>
    (name: string, fn: (...args: unknown[]) => unknown) =>
    async (importOriginal: () => Promise<object>) => ({
      ...(await importOriginal()),
      [name]: (...args: unknown[]) => fn(...args),
    }),
);

vi.mock('@/services/applicationApi', override('deleteApplication', del.application));
vi.mock('@/services/campaignApi', override('deleteCampaign', del.campaign));
vi.mock('@/services/mediaApi', override('deleteMedia', del.media));
vi.mock('@/services/fontApi', override('deleteFont', del.font));
vi.mock('@/services/reportScheduleApi', override('deleteReportSchedule', del.reportSchedule));
vi.mock('@/services/savedReportApi', override('deleteSavedReport', del.savedReport));
vi.mock('@/services/tagApi', override('deleteTag', del.tag));
vi.mock('@/services/taskApi', override('deleteTask', del.task));
vi.mock('@/services/userGroupApi', override('deleteUserGroup', del.userGroup));
vi.mock('@/services/resolutionApi', override('deleteResolution', del.resolution));
vi.mock('@/services/layoutsApi', override('deleteLayout', del.layout));
vi.mock('@/services/commandApi', override('deleteCommand', del.command));
vi.mock('@/services/displayGroupApi', override('deleteDisplayGroup', del.displayGroup));
vi.mock('@/services/displayProfileApi', override('deleteDisplayProfile', del.displayProfile));
vi.mock('@/services/displaysApi', override('deleteDisplay', del.display));
vi.mock('@/services/playerVersionApi', override('deletePlayerVersion', del.playerVersion));
vi.mock('@/services/syncGroupApi', override('deleteSyncGroup', del.syncGroup));
vi.mock('@/services/datasetApi', override('deleteDataset', del.dataset));
vi.mock('@/services/menuBoardApi', override('deleteMenuBoard', del.menuBoard));
vi.mock('@/services/playlistApi', override('deletePlaylist', del.playlist));
vi.mock('@/services/notificationApi', override('deleteNotification', del.notification));
vi.mock('@/services/daypartApi', override('deleteDaypart', del.daypart));
vi.mock('@/services/eventApi', override('deleteEvent', del.event));

import { UserProvider } from '@/context/UserContext';
import { useApplicationActions } from '@/pages/Administration/Applications/hooks/useApplicationsActions';
import { useFontActions } from '@/pages/Administration/Fonts/hooks/useFontActions';
import { useTagsActions } from '@/pages/Administration/Tags/hooks/useTagsActions';
import { useTasksActions } from '@/pages/Administration/Tasks/hooks/useTasksActions';
import { useUserGroupActions } from '@/pages/Administration/UserGroups/hooks/useUserGroupActions';
import { useCampaignActions } from '@/pages/Design/Campaigns/hooks/useCampaignActions';
import { useLayoutActions } from '@/pages/Design/Layouts/hooks/useLayoutActions';
import { useResolutionActions } from '@/pages/Design/Resolutions/hooks/useResolutionActions';
import { useTemplateActions } from '@/pages/Design/Templates/hooks/useTemplateActions';
import { useCommandActions } from '@/pages/Displays/Commands/hooks/useCommandActions';
import { useDisplayGroupActions } from '@/pages/Displays/DisplayGroup/hooks/useDisplayGroupActions';
import { useDisplayProfileActions } from '@/pages/Displays/DisplayProfile/hooks/useDisplayProfileActions';
import { useDisplaysActions } from '@/pages/Displays/Displays/hooks/useDisplaysActions';
import { usePlayerVersionActions } from '@/pages/Displays/PlayerVersions/hooks/usePlayerVersionsActions';
import { useSyncGroupActions } from '@/pages/Displays/SyncGroups/hooks/useSyncGroupActions';
import { useDatasetActions } from '@/pages/Library/Dataset/hooks/useDatasetActions';
import { useMediaActions } from '@/pages/Library/Media/hooks/useMediaActions';
import { useMenuBoardActions } from '@/pages/Library/MenuBoard/hooks/useMenuBoardActions';
import { usePlaylistActions } from '@/pages/Library/Playlists/hooks/usePlaylistActions';
import { useNotificationActions } from '@/pages/Notification/hooks/useNotificationActions';
import { useReportScheduleActions } from '@/pages/Reporting/ReportSchedules/hooks/useReportScheduleActions';
import { useSavedReportActions } from '@/pages/Reporting/SavedReports/hooks/useSavedReportActions';
import { useDaypartActions } from '@/pages/Schedule/Daypart/hooks/useDaypartActions';
import { useEventActions } from '@/pages/Schedule/Schedule/hooks/useEventActions';
import { alreadyDeleted, serverError } from '@/testUtils/apiErrors';
import { buildCurrentUser, PERSONAS } from '@/testUtils/personas';
import { trackSequentialCalls } from '@/testUtils/sequentialMock';

const t = vi.fn((key: string) => key) as unknown as TFunction;
const superAdmin = buildCurrentUser(PERSONAS.superAdmin, { userId: 1, userName: 'admin' });
// Two hooks navigate and one reads the current user, so every hook gets both.
const wrapper = ({ children }: { children: ReactNode }) => (
  <UserProvider initialUser={superAdmin}>
    <MemoryRouter>{children}</MemoryRouter>
  </UserProvider>
);

const hookProps = () => ({
  t,
  handleRefresh: vi.fn(),
  closeModal: vi.fn(),
  setRowSelection: vi.fn(),
  setItemsToMove: vi.fn(),
  setItemsToDelete: vi.fn(),
  timezone: 'UTC',
  folderId: null,
  showThumbnailColumn: false,
  revealThumbnailColumns: vi.fn(),
});
type HookProps = ReturnType<typeof hookProps>;

/**
 * The parts of an action hook this contract uses. Every row's hook must fit it, so a change to
 * a hook's props or confirmDelete signature fails typecheck here rather than passing unseen.
 */
interface DeletingHook {
  confirmDelete: (items: never[], options: never) => Promise<void>;
  deleteError: string | null;
}

interface Row {
  page: string;
  useActions: (props: HookProps) => DeletingHook;
  deleteFn: Mock;
  /** Builds a grid row whose delete is sent for `id`. */
  item: (id: number | string) => object;
  /** The options the user chose in the delete modal, for pages that have them. */
  options?: object;
  /** What the delete call must carry for those options. */
  sentWith?: object;
  ids?: (number | string)[];
  /** The hook narrows Retry to the items that failed, rather than leaving the queue alone. */
  requeuesFailed?: true;
}

const ROWS: Row[] = [
  {
    page: 'Applications',
    useActions: useApplicationActions,
    deleteFn: del.application,
    item: (key) => ({ key }),
    ids: ['app-1', 'app-2', 'app-3'],
  },
  {
    page: 'Campaigns',
    useActions: useCampaignActions,
    deleteFn: del.campaign,
    item: (campaignId) => ({ campaignId }),
  },
  {
    page: 'Layouts',
    useActions: useLayoutActions,
    deleteFn: del.layout,
    item: (layoutId) => ({ layoutId }),
  },
  {
    page: 'Media',
    useActions: useMediaActions,
    deleteFn: del.media,
    item: (mediaId) => ({ mediaId }),
    options: { allLayouts: true, purgeList: true },
    sentWith: { forceDelete: true, purge: true },
    requeuesFailed: true,
  },
  { page: 'Fonts', useActions: useFontActions, deleteFn: del.font, item: (id) => ({ id }) },
  { page: 'Tags', useActions: useTagsActions, deleteFn: del.tag, item: (tagId) => ({ tagId }) },
  {
    page: 'Tasks',
    useActions: useTasksActions,
    deleteFn: del.task,
    item: (taskId) => ({ taskId }),
  },
  {
    page: 'User Groups',
    useActions: useUserGroupActions,
    deleteFn: del.userGroup,
    item: (groupId) => ({ groupId }),
  },
  {
    page: 'Resolutions',
    useActions: useResolutionActions,
    deleteFn: del.resolution,
    item: (resolutionId) => ({ resolutionId }),
  },
  {
    page: 'Templates',
    useActions: useTemplateActions,
    deleteFn: del.layout,
    item: (layoutId) => ({ layoutId }),
  },
  {
    page: 'Commands',
    useActions: useCommandActions,
    deleteFn: del.command,
    item: (commandId) => ({ commandId }),
  },
  {
    page: 'Display Groups',
    useActions: useDisplayGroupActions,
    deleteFn: del.displayGroup,
    item: (displayGroupId) => ({ displayGroupId }),
  },
  {
    page: 'Display Profiles',
    useActions: useDisplayProfileActions,
    deleteFn: del.displayProfile,
    item: (displayProfileId) => ({ displayProfileId }),
  },
  {
    page: 'Displays',
    useActions: useDisplaysActions,
    deleteFn: del.display,
    item: (displayId) => ({ displayId }),
  },
  {
    page: 'Player Versions',
    useActions: usePlayerVersionActions,
    deleteFn: del.playerVersion,
    item: (versionId) => ({ versionId }),
  },
  {
    page: 'Sync Groups',
    useActions: useSyncGroupActions,
    deleteFn: del.syncGroup,
    item: (syncGroupId) => ({ syncGroupId }),
  },
  {
    page: 'Datasets',
    useActions: useDatasetActions,
    deleteFn: del.dataset,
    item: (dataSetId) => ({ dataSetId }),
    options: { deleteData: true },
    sentWith: { deleteData: true },
  },
  {
    page: 'Menu Boards',
    useActions: useMenuBoardActions,
    deleteFn: del.menuBoard,
    item: (menuId) => ({ menuId }),
  },
  {
    page: 'Playlists',
    useActions: usePlaylistActions,
    deleteFn: del.playlist,
    item: (playlistId) => ({ playlistId }),
  },
  {
    page: 'Notifications',
    useActions: useNotificationActions,
    deleteFn: del.notification,
    item: (notificationId) => ({ notificationId }),
  },
  {
    page: 'Dayparts',
    useActions: useDaypartActions,
    deleteFn: del.daypart,
    item: (dayPartId) => ({ dayPartId }),
  },
  {
    page: 'Report Schedules',
    useActions: useReportScheduleActions,
    deleteFn: del.reportSchedule,
    item: (reportScheduleId) => ({ reportScheduleId }),
  },
  {
    page: 'Saved Reports',
    useActions: useSavedReportActions,
    deleteFn: del.savedReport,
    item: (savedReportId) => ({ savedReportId }),
  },
  {
    page: 'Schedule events',
    useActions: useEventActions,
    deleteFn: del.event,
    item: (eventId) => ({ eventId }),
  },
];

/** Render a row's hook and delete three items, the middle one failing with `failure`. */
const deleteThree = async (row: Row, failure: Error) => {
  const ids = row.ids ?? [1, 2, 3];
  const tracker = trackSequentialCalls(row.deleteFn, undefined, {
    key: (id: number | string) => id,
    respond: (id: number | string) => (id === ids[1] ? Promise.reject(failure) : undefined),
  });
  const props = hookProps();
  const { result } = renderHook(() => row.useActions(props), { wrapper });

  await act(() => result.current.confirmDelete(ids.map(row.item) as never[], row.options as never));

  return { ids, tracker, props, result };
};

beforeEach(() => {
  vi.clearAllMocks();
});

/** The success message, however each page words it, was told how many items went. */
const reportedDeleted = (count: number) =>
  expect(t).toHaveBeenCalledWith(expect.stringContaining('deleted successfully'), { count });

describe.each(ROWS.map((row) => [row.page, row] as const))('%s bulk delete', (_, row) => {
  test('sends every selected item, and counts one already deleted as deleted', async () => {
    const { ids, tracker, props, result } = await deleteThree(row, alreadyDeleted());

    expect([...tracker.callOrder].sort()).toEqual([...ids].sort());
    reportedDeleted(3);
    if (row.sentWith) {
      for (const id of ids) {
        expect(row.deleteFn).toHaveBeenCalledWith(id, row.sentWith);
      }
    }
    expect(result.current.deleteError).toBeNull();
    expect(props.closeModal).toHaveBeenCalled();
    expect(mockNotifySuccess).toHaveBeenCalled();
    expect(props.setRowSelection).toHaveBeenCalledWith({});
    expect(props.setItemsToDelete).not.toHaveBeenCalled();
  });

  test('a server error on one item is reported with the others counted as deleted', async () => {
    const { ids, tracker, props, result } = await deleteThree(
      row,
      serverError('Item 2 is in use.'),
    );

    expect([...tracker.callOrder].sort()).toEqual([...ids].sort());
    reportedDeleted(2);
    // The error reads "<2 items> deleted successfully. <the server's message>".
    expect(result.current.deleteError).toMatch(/deleted successfully\. Item 2 is in use\.$/);
    expect(props.closeModal).not.toHaveBeenCalled();
    expect(mockNotifySuccess).not.toHaveBeenCalled();
    expect(props.setRowSelection).toHaveBeenCalledWith({});
    // Retry must not drop the failed item, nor resend only items already deleted.
    if (row.requeuesFailed) {
      expect(props.setItemsToDelete).toHaveBeenCalledTimes(1);
      expect(props.setItemsToDelete).toHaveBeenCalledWith([row.item(ids[1]!)]);
    } else {
      expect(props.setItemsToDelete).not.toHaveBeenCalled();
    }
  });
});
