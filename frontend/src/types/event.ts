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

export type RecurrenceType = 'None' | 'Minute' | 'Hour' | 'Day' | 'Week' | 'Month' | 'Year';

export enum EventTypeId {
  Layout = 1,
  Command = 2,
  Overlay = 3,
  Interrupt = 4,
  Campaign = 5,
  Action = 6,
  Media = 7,
  Playlist = 8,
  Sync = 9,
  DataConnector = 10,
}

export enum ReminderType {
  Minute = 1,
  Hour = 2,
  Day = 3,
  Week = 4,
  Month = 5,
}

export enum ReminderOption {
  BeforeStart = 1,
  AfterStart = 2,
  BeforeEnd = 3,
  AfterEnd = 4,
}

export type CriteriaCondition =
  | 'set'
  | 'lt'
  | 'lte'
  | 'eq'
  | 'neq'
  | 'gt'
  | 'gte'
  | 'contains'
  | 'ncontains';

export interface Tag {
  tagId: number;
  tag: string;
  options?: string[] | null;
  isRequired: number;
}

export interface TagLink {
  tagId: number;
  tag: string;
  value?: string | null;
}

export interface DisplayGroup {
  displayGroupId: number;
  displayGroup: string;
  description?: string | null;
  isDisplaySpecific: number;
  isDynamic: number;
  dynamicCriteria?: string | null;
  dynamicCriteriaLogicalOperator?: 'OR' | 'AND' | null;
  dynamicCriteriaTags?: string | null;
  dynamicCriteriaExactTags?: number | null;
  dynamicCriteriaTagsLogicalOperator?: 'OR' | 'AND' | null;
  userId: number;
  tags: TagLink[];
  bandwidthLimit?: number | null;
  groupsWithPermissions?: string | null;
  createdDt: string;
  modifiedDt: string;
  folderId: number;
  permissionsFolderId: number;
  ref1?: string | null;
  ref2?: string | null;
  ref3?: string | null;
  ref4?: string | null;
  ref5?: string | null;
}

export interface ScheduleReminder {
  scheduleReminderId: number;
  eventId: number;
  value: number;
  type: ReminderType;
  option: ReminderOption;
  isEmail: number;
  reminderDt: number;
  lastReminderDt: number;
}

export interface ScheduleCriteria {
  id: number;
  eventId: number;
  type: string;
  metric: string;
  condition: CriteriaCondition;
  value: string;
}

export interface Event {
  eventId: number;
  eventTypeId: EventTypeId;
  userId: number;
  fromDt: number;
  toDt: number;
  isPriority: number;
  displayOrder: number;
  dayPartId: number;
  isAlways: number;
  isCustom: number;
  syncEvent: number;
  syncTimezone: number;
  maxPlaysPerHour: number;
  isGeoAware: number;

  name?: string | null;
  campaignId?: number | null;
  commandId?: number | null;

  displayGroups: DisplayGroup[];
  scheduleReminders: ScheduleReminder[];
  criteria: ScheduleCriteria[];
  scheduleExclusions?: { fromDt: number; toDt: number }[];

  recurrenceType?: RecurrenceType | null;
  recurrenceDetail?: number | null;
  recurrenceRange?: number | null;
  recurrenceRepeatsOn?: string | null;
  recurrenceMonthlyRepeatsOn?: number | null;

  campaign?: string;
  command?: string;

  shareOfVoice?: number | null;
  geoLocation?: string | null;

  actionTriggerCode?: string | null;
  actionType?: string | null;
  actionLayoutCode?: string | null;

  parentCampaignId?: number | null;
  syncGroupId?: number | null;
  dataSetId?: number | null;
  dataSetParams?: string | null;

  recurringEvent?: boolean;
  recurringEventDescription?: string;

  modifiedBy?: number | null;
  modifiedByName?: string | null;
  createdOn?: string;
  updatedOn?: string | null;

  resolutionId?: number;
  layoutDuration?: number;
  backgroundColor?: string;
  mediaId?: number | null;
  playlistId?: number | null;
  fullScreenCampaignId?: number | null;
  isEditable?: boolean;
}
