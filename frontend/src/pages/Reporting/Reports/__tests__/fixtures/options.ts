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

// Two pages per option list, so paging and load-more are reachable.

type Page<T> = { rows: T[]; totalCount: number };

const pages = <T>(items: T[], pageSize: number): [Page<T>, Page<T>] => [
  { rows: items.slice(0, pageSize), totalCount: items.length },
  { rows: items.slice(pageSize), totalCount: items.length },
];

const many = <T>(count: number, make: (i: number) => T) =>
  Array.from({ length: count }, (_, i) => make(i + 1));

// First items get readable names the tests pick by; the rest fill the pages.
export const DISPLAYS = many(22, (i) => ({
  displayId: i,
  display: i === 1 ? 'Lobby Screen' : i === 2 ? 'Back Office' : `Display ${i}`,
  displayGroupId: 100 + i,
}));
export const DISPLAY_GROUPS = many(22, (i) => ({
  displayGroupId: 200 + i,
  displayGroup: i === 1 ? 'Ground Floor' : `Group ${i}`,
}));
export const LAYOUTS = many(22, (i) => ({
  layoutId: 300 + i,
  layout: i === 1 ? 'Lobby Layout' : `Layout ${i}`,
}));
export const MEDIA = many(22, (i) => ({
  mediaId: 400 + i,
  name: i === 1 ? 'Promo Video' : `Media ${i}`,
}));
export const CAMPAIGNS = many(22, (i) => ({
  campaignId: 500 + i,
  campaign: i === 1 ? 'Spring Sale' : `Campaign ${i}`,
}));
export const USERS = many(12, (i) => ({
  userId: i,
  userName: i === 1 ? 'alice' : i === 2 ? 'bob' : `user${i}`,
}));
export const USER_GROUPS = many(12, (i) => ({
  groupId: 600 + i,
  group: i === 1 ? 'Editors' : `Group ${i}`,
}));

// Page sizes the hooks request (20 shared, 10 for users and groups).
export const DISPLAY_PAGES = pages(DISPLAYS, 20);
export const DISPLAY_GROUP_PAGES = pages(DISPLAY_GROUPS, 20);
export const LAYOUT_PAGES = pages(LAYOUTS, 20);
export const MEDIA_PAGES = pages(MEDIA, 20);
export const CAMPAIGN_PAGES = pages(CAMPAIGNS, 20);
export const USER_PAGES = pages(USERS, 10);
export const USER_GROUP_PAGES = pages(USER_GROUPS, 10);

// A lookup by id returns just that item; an unknown id returns nothing.
export const single = <T>(item: T): Page<T> => ({ rows: [item], totalCount: 1 });
export const EMPTY_PAGE: Page<never> = { rows: [], totalCount: 0 };
