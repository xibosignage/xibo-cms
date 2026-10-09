/*
 * Copyright (C) 2026 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */

import type { ColumnDef } from '@tanstack/react-table';
import { describe, expect, test } from 'vitest';

import { EPOCH, SQL_DATETIME, formatDateTime, propsWith, t } from './helpers';

import { getTagColumns } from '@/pages/Administration/Tags/TagsConfig';
import { buildTag } from '@/pages/Administration/Tags/__tests__/fixtures/tag';
import { getTaskColumns } from '@/pages/Administration/Tasks/TasksConfig';
import { buildTask } from '@/pages/Administration/Tasks/__tests__/fixtures/task';
import { getUserGroupColumns } from '@/pages/Administration/UserGroups/UserGroupsConfig';
import { getUserColumns } from '@/pages/Administration/Users/UsersConfig';
import { buildUser } from '@/pages/Administration/Users/__tests__/fixtures/user';
import { getAuditTrailColumns } from '@/pages/Advanced/AuditTrail/AuditTrailConfig';
import { getSessionColumns } from '@/pages/Advanced/Sessions/SessionsConfig';
import { getCampaignColumn } from '@/pages/Design/Campaigns/CampaignConfig';
import { getLayoutColumns } from '@/pages/Design/Layouts/LayoutConfig';
import { getTemplateColumn } from '@/pages/Design/Templates/TemplatesConfig';
import { getDisplayGroupColumns } from '@/pages/Displays/DisplayGroup/DisplayGroupConfig';
import { buildDisplayGroup } from '@/pages/Displays/DisplayGroup/__tests__/fixtures/displayGroup';
import { getDisplayColumns } from '@/pages/Displays/Displays/DisplaysConfig';
import { buildDisplay } from '@/pages/Displays/Displays/__tests__/fixtures/display';
import { getSyncGroupColumns } from '@/pages/Displays/SyncGroups/SyncGroupsConfig';
import { buildSyncGroup } from '@/pages/Displays/SyncGroups/__tests__/fixtures/syncGroup';
import { getMediaColumns } from '@/pages/Library/Media/MediaConfig';
import { getMenuBoardColumns } from '@/pages/Library/MenuBoard/MenuBoardConfig';
import { getPlaylistColumns } from '@/pages/Library/Playlists/PlaylistsConfig';
import { getNotificationColumns } from '@/pages/Notification/NotificationConfig';
import { getReportScheduleColumns } from '@/pages/Reporting/ReportSchedules/ReportSchedulesConfig';
import { getSavedReportColumns } from '@/pages/Reporting/SavedReports/SavedReportsConfig';
import { getEventColumns } from '@/pages/Schedule/Schedule/EventsConfig';
import { buildEvent } from '@/pages/Schedule/Schedule/__tests__/fixtures/event';
import { exportColumnValue, exportedColumns, renderColumnText } from '@/testUtils/columnContract';
import { knownFailure } from '@/testUtils/knownFailure';

/**
 * Export contract: a column exports to CSV what it shows on screen.
 *
 * The CSV button reads `meta.getExportValue` (or the raw value), not the `cell` renderer, so a
 * column can display correctly and export something else.
 *
 * How it is checked: for every column that declares `getExportValue`, the exported text must equal
 * the text the cell shows, for a fully populated row and a row of empty values. Icon-only cells
 * (nothing visible to compare) are skipped. Known mismatches are pinned below with `knownFailure`,
 * and the sweep's rows avoid them, so each one has exactly one test that names it.
 *
 * Exports run through `getExportCellValue` (components/ui/table/exportCsv.ts), the same function
 * the CSV button uses.
 */

const collapse = (text: string) => text.replace(/\s+/g, ' ').trim();

/** Every column that declares getExportValue and exports something other than what it shows. */
function exportMismatches<T>(columns: ColumnDef<T>[], rows: Record<string, object>): string[] {
  const mismatches: string[] = [];
  let compared = 0;
  for (const column of exportedColumns(columns)) {
    if (!column.declaresExportValue) continue;
    for (const [label, row] of Object.entries(rows)) {
      const shown = collapse(renderColumnText(columns, column.id, row as T));
      if (shown === '') continue; // icon-only cell: nothing visible to compare
      const exported = collapse(exportColumnValue(columns, column.id, row as T));
      compared++;
      if (shown !== exported) {
        mismatches.push(`${column.id} (${label} row): shows "${shown}", exports "${exported}"`);
      }
    }
  }
  // A sweep that compares nothing would pass vacuously; report it as a failure instead.
  if (compared === 0) mismatches.push('no exported column showed any text for these rows');
  return mismatches;
}

const shownAndExported = <T,>(columns: ColumnDef<T>[], id: string, row: object) => ({
  shown: collapse(renderColumnText(columns, id, row as T)),
  exported: collapse(exportColumnValue(columns, id, row as T)),
});

describe('every declared export matches what the grid shows', () => {
  test('Layouts', () => {
    const columns = getLayoutColumns(propsWith());
    const base = {
      layoutId: 1,
      layout: 'L',
      tags: [],
      publishedStatus: 'Draft',
      publishedDate: null,
    };
    expect(
      exportMismatches(columns, {
        full: { ...base, duration: 95, modifiedDt: SQL_DATETIME },
        empty: { ...base, duration: 0, modifiedDt: null },
      }),
    ).toEqual([]);
  });

  test('Campaigns', () => {
    const columns = getCampaignColumn(propsWith({ canAccessAdCampaign: true }));
    const base = { campaignId: 1, campaign: 'C', tags: [] };
    expect(
      exportMismatches(columns, {
        full: { ...base, startDt: EPOCH, endDt: EPOCH, cyclePlaybackEnabled: 1 },
        // Keeps its dates: a campaign with no dates is a known mismatch, pinned below.
        empty: { ...base, startDt: EPOCH, endDt: EPOCH, cyclePlaybackEnabled: 0 },
      }),
    ).toEqual([]);
  });

  test('Templates', () => {
    const columns = getTemplateColumn(propsWith());
    expect(
      exportMismatches(columns, {
        full: { layoutId: 1, layout: 'T', tags: [], modifiedDt: SQL_DATETIME },
      }),
    ).toEqual([]);
  });

  test('Displays', () => {
    const columns = getDisplayColumns(propsWith());
    expect(
      exportMismatches(columns, {
        full: {
          ...buildDisplay(),
          tags: [],
          mediaInventoryStatus: 1,
          clientType: 'android',
          clientVersion: '4',
          clientCode: 400,
          lastAccessed: EPOCH,
          lastCommandSuccess: 1,
          commercialLicence: 2,
          createdDt: SQL_DATETIME,
          modifiedDt: SQL_DATETIME,
        },
        empty: {
          ...buildDisplay(),
          tags: [],
          mediaInventoryStatus: null,
          clientType: null,
          lastAccessed: 0,
          lastCommandSuccess: 0,
          commercialLicence: 0,
        },
      }),
    ).toEqual([]);
  });

  test('Display Groups', () => {
    const columns = getDisplayGroupColumns(propsWith());
    expect(
      exportMismatches(columns, {
        full: {
          ...buildDisplayGroup(),
          tags: [],
          createdDt: SQL_DATETIME,
          modifiedDt: SQL_DATETIME,
        },
      }),
    ).toEqual([]);
  });

  test('Sync Groups', () => {
    const columns = getSyncGroupColumns(propsWith());
    expect(
      exportMismatches(columns, {
        full: { ...buildSyncGroup(), createdDt: SQL_DATETIME, modifiedDt: SQL_DATETIME },
      }),
    ).toEqual([]);
  });

  test('Media', () => {
    const columns = getMediaColumns(propsWith());
    const base = { mediaId: 1, name: 'a.jpg', fileSize: 1024, tags: [] };
    expect(
      exportMismatches(columns, {
        full: { ...base, duration: 95, createdDt: SQL_DATETIME, modifiedDt: SQL_DATETIME },
      }),
    ).toEqual([]);
  });

  test('Playlists', () => {
    const columns = getPlaylistColumns(propsWith());
    const base = {
      playlistId: 1,
      name: 'P',
      tags: [],
      createdDt: SQL_DATETIME,
      modifiedDt: SQL_DATETIME,
    };
    expect(
      exportMismatches(columns, {
        upToDate: { ...base, duration: 95, requiresDurationUpdate: 0 },
      }),
    ).toEqual([]);
  });

  test('Menu Boards', () => {
    const columns = getMenuBoardColumns(propsWith());
    expect(
      exportMismatches(columns, { full: { menuId: 1, name: 'M', modifiedDt: EPOCH } }),
    ).toEqual([]);
  });

  test('Events', () => {
    const columns = getEventColumns(propsWith());
    expect(
      exportMismatches(columns, {
        dated: buildEvent({ createdOn: SQL_DATETIME, updatedOn: SQL_DATETIME }),
        always: buildEvent({ isAlways: 1, createdOn: SQL_DATETIME, updatedOn: SQL_DATETIME }),
      }),
    ).toEqual([]);
  });

  test('Report Schedules', () => {
    const columns = getReportScheduleColumns(propsWith());
    const base = { id: 1, name: 'R', reportName: 'proofofplayReport' };
    expect(
      exportMismatches(columns, {
        full: {
          ...base,
          lastRunDt: EPOCH,
          nextRunDt: EPOCH,
          previousRunDt: EPOCH,
          fromDt: EPOCH,
          toDt: EPOCH,
          createdDt: EPOCH,
          isActive: 1,
        },
        empty: {
          ...base,
          lastRunDt: 0,
          nextRunDt: 0,
          fromDt: 0,
          toDt: 0,
          createdDt: EPOCH,
          isActive: 0,
        },
      }),
    ).toEqual([]);
  });

  test('Saved Reports', () => {
    const columns = getSavedReportColumns(propsWith());
    expect(
      exportMismatches(columns, {
        full: { savedReportId: 1, reportName: 'proofofplayReport', generatedOn: EPOCH },
      }),
    ).toEqual([]);
  });

  test('Notifications', () => {
    const columns = getNotificationColumns(propsWith());
    expect(
      exportMismatches(columns, { full: { notificationId: 1, subject: 'S', releaseDt: EPOCH } }),
    ).toEqual([]);
  });

  test('Tasks', () => {
    const columns = getTaskColumns(propsWith());
    expect(
      exportMismatches(columns, {
        full: { ...buildTask(), status: 2, nextRunDt: EPOCH, lastRunDt: EPOCH },
        empty: { ...buildTask(), status: 1, nextRunDt: 0, lastRunDt: 0 },
      }),
    ).toEqual([]);
  });

  test('Users', () => {
    const columns = getUserColumns(propsWith());
    expect(exportMismatches(columns, { full: { ...buildUser(), userTypeId: 2 } })).toEqual([]);
  });

  test('User Groups', () => {
    const columns = getUserGroupColumns(propsWith());
    expect(
      exportMismatches(columns, {
        quota: { groupId: 1, group: 'G', libraryQuota: 1024 },
        unlimited: { groupId: 2, group: 'H', libraryQuota: 0 },
      }),
    ).toEqual([]);
  });

  test('Tags', () => {
    const columns = getTagColumns(propsWith());
    expect(
      exportMismatches(columns, { full: { ...buildTag(), options: '["red","blue"]' } }),
    ).toEqual([]);
  });

  test('Audit Trail', () => {
    const columns = getAuditTrailColumns(t, formatDateTime);
    expect(exportMismatches(columns, { full: { logId: 1, message: 'm', logDate: EPOCH } })).toEqual(
      [],
    );
  });

  test('Sessions', () => {
    const columns = getSessionColumns(propsWith());
    expect(
      exportMismatches(columns, {
        full: { userId: 1, userName: 'u', lastAccessed: SQL_DATETIME, expiresAt: SQL_DATETIME },
      }),
    ).toEqual([]);
  });
});

describe('known export mismatches', () => {
  // The cell shows "-" for a campaign with no start/end date; the CSV is blank.
  knownFailure(
    'a campaign with no dates exports what the grid shows',
    () =>
      shownAndExported(getCampaignColumn(propsWith({ canAccessAdCampaign: true })), 'startDt', {
        campaignId: 1,
        campaign: 'C',
        startDt: 0,
      }),
    ({ shown, exported }) => expect(exported).toBe(shown),
  );

  // Events with no created/updated date show "—"; the CSV is blank.
  knownFailure(
    'an event with no created date exports what the grid shows',
    () =>
      shownAndExported(getEventColumns(propsWith()), 'createdOn', buildEvent({ createdOn: '' })),
    ({ shown, exported }) => expect(exported).toBe(shown),
  );

  // These Events columns have no accessor, so they export an empty value while the
  // grid shows the campaign and display-group names.
  knownFailure(
    'the Events Event column exports the name the grid shows',
    () =>
      shownAndExported(
        getEventColumns(propsWith()),
        'event',
        buildEvent({ campaign: 'Spring promo' }),
      ),
    ({ exported }) => expect(exported).not.toBe(''),
  );

  knownFailure(
    'the Events Display Groups column exports the names the grid shows',
    () =>
      shownAndExported(
        getEventColumns(propsWith()),
        'displayGroups',
        buildEvent({ campaign: 'Spring promo' }),
      ),
    ({ exported }) => expect(exported).not.toBe(''),
  );

  // The Type column shows "Layout", "Command"...; the CSV holds the raw type number.
  knownFailure(
    'the Events Type column exports the type name the grid shows',
    () =>
      shownAndExported(getEventColumns(propsWith()), 'eventTypeId', buildEvent({ eventTypeId: 2 })),
    ({ shown, exported }) => expect(exported).toBe(shown),
  );

  // The Sharing column shows group names but exports the raw array as JSON, e.g. ["Group A"].
  knownFailure(
    'the Sharing column exports the group names, not JSON',
    () => {
      const columns = getMediaColumns(propsWith());
      const sharing = exportedColumns(columns).find((c) => /sharing/i.test(c.header));
      expect(sharing).toBeDefined();
      return exportColumnValue(columns, sharing!.id, {
        mediaId: 1,
        name: 'a.jpg',
        fileSize: 1,
        groupsWithPermissions: 'Group A',
        groupsWithPermissionsList: ['Group A'],
      } as never);
    },
    (exported) => expect(exported).not.toMatch(/^\[/),
  );

  // The Events badge column has an empty header and is not excluded, so the CSV gets a
  // nameless column.
  knownFailure(
    'every column in the Events CSV has a header',
    () => exportedColumns(getEventColumns(propsWith())),
    (columns) => expect(columns.filter((c) => c.header.trim() === '')).toEqual([]),
  );
});
