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

import type { TabNavItem } from '@/components/ui/TabNav';
import type { SettingsData, SettingsFormValues } from '@/types/settings';

export interface SettingsTabProps {
  formValues: SettingsFormValues;
  updateField: (key: string, value: string) => void;
  isVisible: (key: string) => boolean;
  isEditable: (key: string) => boolean;
  options: SettingsData['options'];
  relatedEntities: SettingsData['relatedEntities'];
  elevateLogUntil: string | null;
  phoneticKey: string;
}

export type DateFormatRow = { category: string } | { char: string; desc: string; example: string };

export const DATE_FORMAT_ROWS: DateFormatRow[] = [
  { category: 'Day' },
  { char: 'd', desc: 'Day of the month, 2 digits with leading zeros', example: '01 to 31' },
  {
    char: 'D',
    desc: 'A textual representation of a day, three letters',
    example: 'Mon through Sun',
  },
  { char: 'j', desc: 'Day of the month without leading zeros', example: '1 to 31' },
  {
    char: 'l',
    desc: 'A full textual representation of the day of the week',
    example: 'Sunday through Saturday',
  },
  {
    char: 'N',
    desc: 'ISO-8601 numeric representation of the day of the week',
    example: '1 (Mon) through 7 (Sun)',
  },
  {
    char: 'S',
    desc: 'English ordinal suffix for the day of the month',
    example: 'st, nd, rd or th',
  },
  {
    char: 'w',
    desc: 'Numeric representation of the day of the week',
    example: '0 (Sun) through 6 (Sat)',
  },
  { char: 'z', desc: 'The day of the year (starting from 0)', example: '0 through 365' },
  { category: 'Week' },
  { char: 'W', desc: 'ISO-8601 week number of year, weeks starting on Monday', example: '42' },
  { category: 'Month' },
  {
    char: 'F',
    desc: 'A full textual representation of a month',
    example: 'January through December',
  },
  {
    char: 'm',
    desc: 'Numeric representation of a month, with leading zeros',
    example: '01 through 12',
  },
  {
    char: 'M',
    desc: 'A short textual representation of a month, three letters',
    example: 'Jan through Dec',
  },
  {
    char: 'n',
    desc: 'Numeric representation of a month, without leading zeros',
    example: '1 through 12',
  },
  { char: 't', desc: 'Number of days in the given month', example: '28 through 31' },
  { category: 'Year' },
  { char: 'L', desc: "Whether it's a leap year", example: '1 if leap year, 0 otherwise' },
  { char: 'o', desc: 'ISO-8601 year number', example: '1999 or 2003' },
  { char: 'Y', desc: 'A full numeric representation of a year, 4 digits', example: '1999 or 2003' },
  { char: 'y', desc: 'A two digit representation of a year', example: '99 or 03' },
  { category: 'Time' },
  { char: 'a', desc: 'Lowercase Ante meridiem and Post meridiem', example: 'am or pm' },
  { char: 'A', desc: 'Uppercase Ante meridiem and Post meridiem', example: 'AM or PM' },
  { char: 'g', desc: '12-hour format of an hour without leading zeros', example: '1 through 12' },
  { char: 'G', desc: '24-hour format of an hour without leading zeros', example: '0 through 23' },
  { char: 'h', desc: '12-hour format of an hour with leading zeros', example: '01 through 12' },
  { char: 'H', desc: '24-hour format of an hour with leading zeros', example: '00 through 23' },
  { char: 'i', desc: 'Minutes with leading zeros', example: '00 to 59' },
  { char: 's', desc: 'Seconds, with leading zeros', example: '00 through 59' },
  { category: 'Timezone' },
  { char: 'e', desc: 'Timezone identifier', example: 'UTC, GMT, Atlantic/Azores' },
  { char: 'O', desc: 'Difference to GMT in hours', example: '+0200' },
  { char: 'P', desc: 'Difference to GMT with colon', example: '+02:00' },
  { char: 'T', desc: 'Timezone abbreviation', example: 'EST, MDT' },
  { category: 'Full Date/Time' },
  { char: 'c', desc: 'ISO 8601 date', example: '2004-02-12T15:19:21+00:00' },
  { char: 'r', desc: 'RFC 2822 formatted date', example: 'Thu, 21 Dec 2000 16:01:07 +0200' },
];

export const SETTINGS_TABS: TabNavItem[] = [
  { labelKey: 'Configuration', path: 'configuration' },
  { labelKey: 'Defaults', path: 'defaults' },
  { labelKey: 'Displays', path: 'displays' },
  { labelKey: 'General', path: 'general' },
  { labelKey: 'Maintenance', path: 'maintenance' },
  { labelKey: 'Network', path: 'network' },
  { labelKey: 'Regional', path: 'regional' },
  { labelKey: 'Sharing', path: 'sharing' },
  { labelKey: 'Troubleshooting', path: 'troubleshooting' },
  { labelKey: 'Users', path: 'users' },
];
