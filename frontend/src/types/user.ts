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

import type { UserGroup } from './userGroup';

export enum UserType {
  SuperAdmin = 1,
  GroupAdmin = 2,
  User = 3,
}

export type UserFeatures = Record<string, boolean>;

export interface UserSettings {
  defaultTimezone: string;
  defaultLanguage: string;
  DATE_FORMAT_JS: string;
  TIME_FORMAT_JS: string;
  homeFolder?: string;
  [key: string]: string | number | boolean | object | null | undefined;
}

export interface User {
  userId: number;
  userName: string;
  userTypeId: UserType;

  email?: string;
  firstName?: string;
  lastName?: string;
  phone?: string;

  features: UserFeatures;
  settings: UserSettings;

  groupId: number;
  group?: string;

  groups?: UserGroup[];
}
