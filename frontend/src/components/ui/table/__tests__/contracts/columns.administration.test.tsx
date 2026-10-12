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

import { describe, expect, test } from 'vitest';

import { EPOCH, SQL_DATETIME, propsWith, statesOf, textOf } from './helpers';

import { getFontColumns } from '@/pages/Administration/Fonts/FontsConfig';
import { getModuleColumns } from '@/pages/Administration/Modules/ModulesConfig';
import { buildModule } from '@/pages/Administration/Modules/__tests__/fixtures/module';
import { getTagColumns } from '@/pages/Administration/Tags/TagsConfig';
import { buildTag } from '@/pages/Administration/Tags/__tests__/fixtures/tag';
import { getTaskColumns } from '@/pages/Administration/Tasks/TasksConfig';
import { buildTask } from '@/pages/Administration/Tasks/__tests__/fixtures/task';
import { getTransitionColumns } from '@/pages/Administration/Transitions/TransitionsConfig';
import { getUserGroupColumns } from '@/pages/Administration/UserGroups/UserGroupsConfig';
import { getUserColumns } from '@/pages/Administration/Users/UsersConfig';
import { buildUser } from '@/pages/Administration/Users/__tests__/fixtures/user';
import { knownFailure } from '@/testUtils/knownFailure';

describe('Tasks grid', () => {
  const columns = getTaskColumns(propsWith());
  const task = (fields: object) => ({ ...buildTask(), lastRunMessage: '', ...fields });

  // task.status: 1 running, 2 idle, 3 error, 4 success, 5 timed out (Entity/Task.php).
  test('the Status column shows each of the five task states distinctly', () => {
    const rows = Object.fromEntries(
      [1, 2, 3, 4, 5].map((s) => [`status${s}`, task({ status: s })]),
    );

    expect(new Set(Object.values(statesOf(columns, 'status', rows))).size).toBe(5);
  });

  test('the Last Status column shows a successful run differently from a failed one', () => {
    const states = statesOf(columns, 'lastRunStatus', {
      failed: task({ lastRunStatus: 3 }),
      succeeded: task({ lastRunStatus: 4 }),
    });

    expect(states.succeeded).not.toBe(states.failed);
  });

  // lastRunStatus only ever holds 0 (never run), 3 (error) or 4 (success).
  knownFailure(
    'the Last Status column shows a task whose last run failed differently from one that has never run',
    () =>
      statesOf(columns, 'lastRunStatus', {
        neverRun: task({ lastRunStatus: 0 }),
        failed: task({ lastRunStatus: 3 }),
      }),
    (states) => expect(states.failed).not.toBe(states.neverRun),
  );

  test('the Next Run column is empty when no run is scheduled, and a CMS date otherwise', () => {
    expect(textOf(columns, 'nextRunDt', task({ nextRunDt: 0 }))).toBe('');
    expect(textOf(columns, 'nextRunDt', task({ nextRunDt: EPOCH }))).toMatch(/^formatted:/);
  });
});

describe('Users grid', () => {
  const columns = getUserColumns(propsWith());
  const user = (fields: object) => ({ ...buildUser(), ...fields });

  // userTypeId: 1 super admin, 2 group admin, 3 user.
  test('the User Type column shows each user type distinctly', () => {
    const states = statesOf(columns, 'userTypeId', {
      superAdmin: user({ userTypeId: 1 }),
      groupAdmin: user({ userTypeId: 2 }),
      plainUser: user({ userTypeId: 3 }),
    });

    expect(new Set(Object.values(states)).size).toBe(3);
  });

  test('the Retired column shows a retired user differently from an active one', () => {
    const states = statesOf(columns, 'retired', {
      retired: user({ retired: 1 }),
      active: user({ retired: 0 }),
    });

    expect(states.retired).not.toBe(states.active);
  });

  // The getter has no CMS formatter, so the server's string is printed as-is.
  knownFailure(
    'the Last Accessed column shows the date through the CMS formatter',
    () => textOf(columns, 'lastAccessed', user({ lastAccessed: SQL_DATETIME })),
    (text) => expect(text).toMatch(/^formatted:/),
  );
});

describe('User Groups grid', () => {
  const columns = getUserGroupColumns(propsWith());
  const group = (fields: object) => ({ groupId: 1, group: 'G', ...fields });

  // libraryQuota 0 means unlimited, and so does no value.
  test('the Library Quota column shows unlimited the same for 0 and null, and a size otherwise', () => {
    const states = statesOf(columns, 'libraryQuota', {
      zero: group({ libraryQuota: 0 }),
      none: group({ libraryQuota: null }),
      quota: group({ libraryQuota: 1024 }),
    });

    expect(states.zero).toBe(states.none);
    expect(states.quota).not.toBe(states.zero);
  });
});

describe('Fonts grid', () => {
  // The getter has no CMS formatter, so the server's string is printed as-is.
  const columns = getFontColumns(propsWith());
  const row = { id: 1, name: 'F', createdAt: SQL_DATETIME, modifiedAt: SQL_DATETIME, size: 10 };

  knownFailure(
    'the Created column shows the date through the CMS formatter',
    () => textOf(columns, 'createdAt', row),
    (text) => expect(text).toMatch(/^formatted:/),
  );

  knownFailure(
    'the Modified column shows the date through the CMS formatter',
    () => textOf(columns, 'modifiedAt', row),
    (text) => expect(text).toMatch(/^formatted:/),
  );
});

describe('Modules grid', () => {
  test('the Library Media column shows a library module differently from a region-specific one', () => {
    const columns = getModuleColumns(propsWith());
    const states = statesOf(columns, 'regionSpecific', {
      library: { ...buildModule(), regionSpecific: 0 },
      region: { ...buildModule(), regionSpecific: 1 },
    });

    expect(states.library).not.toBe(states.region);
  });
});

describe('Tags grid', () => {
  // options is a JSON string or null; both null and an empty list mean "no options".
  test('the Options column shows no options the same for null and an empty list', () => {
    const columns = getTagColumns(propsWith());
    const states = statesOf(columns, 'options', {
      none: { ...buildTag(), options: null },
      emptyList: { ...buildTag(), options: '[]' },
      some: { ...buildTag(), options: '["red","blue"]' },
    });

    expect(states.emptyList).toBe(states.none);
    expect(states.some).not.toBe(states.none);
  });
});

describe('Transitions grid', () => {
  test('the Has Direction column shows a directional transition differently from one without', () => {
    const columns = getTransitionColumns(propsWith());
    const states = statesOf(columns, 'hasDirection', {
      directional: { transitionId: 1, transition: 'Fly', hasDirection: 1 },
      plain: { transitionId: 2, transition: 'Fade', hasDirection: 0 },
    });

    expect(states.directional).not.toBe(states.plain);
  });
});
