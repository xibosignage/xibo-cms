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
import type { TFunction } from 'i18next';
import {
  ArrowRightLeft,
  CopyCheck,
  Edit,
  Goal,
  Lock,
  MapPin,
  Monitor,
  MousePointer2,
  OctagonPause,
  PictureInPicture2,
  RefreshCw,
  Repeat2,
  Trash2,
  Wrench,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { type ComponentProps } from 'react';

import type { FilterConfigItem } from '@/components/ui/FilterInputs';
import type { DataTableBulkAction } from '@/components/ui/table/DataTableBulkActions';
import { TextCell, ActionsCell, CheckMarkCell, StatusCell } from '@/components/ui/table/cells';
import type { Event } from '@/types/event';
import { EventTypeId } from '@/types/event';
import type { ActionItem, BaseModalType } from '@/types/table';
import type { UIStatus } from '@/types/uiStatus';
import { formatDateTime } from '@/utils/date';

export interface EventFilterInput {
  eventTypeId?: number | null;
  name?: string | null;
  geoAware?: number | null;
  campaignId?: number | null;
  layoutCampaignId?: number | null;
  displaySpecificGroupIds?: number[] | null;
  displayGroupIds?: number[] | null;
  recurring?: number | null;
  directSchedule?: number | null;
  sharedSchedule?: number | null;
  fromDt?: string | null;
  toDt?: string | null;
}

export type ModalType = BaseModalType | 'schedule' | null;

export const INITIAL_FILTER_STATE: EventFilterInput = {};

const EVENT_TYPE_LABELS: Record<number, string> = {
  [EventTypeId.Layout]: 'Layout',
  [EventTypeId.Command]: 'Command',
  [EventTypeId.Overlay]: 'Overlay Layout',
  [EventTypeId.Interrupt]: 'Interrupt Layout',
  [EventTypeId.Campaign]: 'Campaign',
  [EventTypeId.Action]: 'Action',
  [EventTypeId.Media]: 'Media',
  [EventTypeId.Playlist]: 'Playlist',
  [EventTypeId.Sync]: 'Synchronised Event',
  [EventTypeId.DataConnector]: 'Data Connector',
};

const YES_NO_OPTIONS = [
  { value: 0, label: 'No' },
  { value: 1, label: 'Yes' },
];

const EVENT_TYPE_STATUS: Record<number, UIStatus> = {
  [EventTypeId.Layout]: 'info',
  [EventTypeId.Command]: 'neutral',
  [EventTypeId.Overlay]: 'danger',
  [EventTypeId.Interrupt]: 'danger',
  [EventTypeId.Campaign]: 'info',
  [EventTypeId.Action]: 'success',
  [EventTypeId.Media]: 'info',
  [EventTypeId.Playlist]: 'info',
  [EventTypeId.Sync]: 'success',
  [EventTypeId.DataConnector]: 'success',
};

export const getBaseFilterKeys = (t: TFunction): FilterConfigItem<EventFilterInput>[] => [
  {
    label: t('Name'),
    name: 'name',
    type: 'text',
    className: '',
    placeholder: t('Event Name'),
  },
  {
    label: t('Event Type'),
    name: 'eventTypeId',
    showAllOption: true,
    options: Object.entries(EVENT_TYPE_LABELS).map(([value, label]) => ({
      value: Number(value),
      label: t(label),
    })),
  },
  {
    label: t('Layout'),
    name: 'layoutCampaignId',
    type: 'paged-select',
    placeholder: t('All'),
    options: [],
  },
  {
    label: t('Campaign'),
    name: 'campaignId',
    type: 'paged-select',
    placeholder: t('All'),
    options: [],
  },
  {
    label: t('Geo Aware'),
    name: 'geoAware',
    showAllOption: true,
    allLabel: t('All'),
    shouldTranslateOptions: true,
    options: YES_NO_OPTIONS,
  },
  {
    label: t('Recurring'),
    name: 'recurring',
    showAllOption: true,
    allLabel: t('All'),
    shouldTranslateOptions: true,
    options: YES_NO_OPTIONS,
  },
  {
    label: t('Direct Schedule'),
    name: 'directSchedule',
    showAllOption: true,
    allLabel: t('All'),
    shouldTranslateOptions: true,
    options: YES_NO_OPTIONS,
  },
  {
    label: t('Shared Schedule'),
    name: 'sharedSchedule',
    showAllOption: true,
    allLabel: t('All'),
    shouldTranslateOptions: true,
    options: YES_NO_OPTIONS,
  },
];

export interface EventActionsProps {
  t: TFunction;
  timezone: string;
  onDelete: (id: number) => void;
  openAddEditModal: (row: Event) => void;
  copyEvent?: (row: number) => void;
}

export const getEventItemActions = ({
  t,
  onDelete,
  openAddEditModal,
  copyEvent,
}: EventActionsProps): ((scheduleEvent: Event) => ActionItem[]) => {
  return (scheduleEvent: Event) => {
    const actions: ActionItem[] = [
      {
        label: t('Edit'),
        icon: Edit,
        onClick: () => openAddEditModal(scheduleEvent),
        isQuickAction: true,
        variant: 'primary' as const,
      },
      {
        label: t('Make a Copy'),
        icon: CopyCheck,
        isQuickAction: true,
        onClick: () => copyEvent && copyEvent(scheduleEvent.eventId),
      },
      {
        label: t('Edit'),
        icon: Edit,
        onClick: () => openAddEditModal(scheduleEvent),
        variant: 'primary' as const,
      },
      {
        label: t('Make a Copy'),
        icon: CopyCheck,
        onClick: () => copyEvent && copyEvent(scheduleEvent.eventId),
      },
      {
        label: t('Delete'),
        icon: Trash2,
        onClick: () => onDelete(scheduleEvent.eventId),
        variant: 'danger' as const,
      },
    ];

    return actions;
  };
};

export type EventBadge = {
  icon: LucideIcon;
  label: string;
  bg: string;
  text: string;
};

export const BADGE_VIEW_ONLY: EventBadge = {
  icon: Lock,
  label: 'View Only',
  bg: 'bg-red-100',
  text: 'text-red-800',
};
export const BADGE_SYNCHRONISED: EventBadge = {
  icon: ArrowRightLeft,
  label: 'Synchronised',
  bg: 'bg-teal-100',
  text: 'text-teal-800',
};
export const BADGE_INTERACTIVE: EventBadge = {
  icon: MousePointer2,
  label: 'Interactive',
  bg: 'bg-gray-100',
  text: 'text-gray-800',
};
export const BADGE_GEO_LOCATION: EventBadge = {
  icon: MapPin,
  label: 'Geo Location',
  bg: 'bg-gray-100',
  text: 'text-gray-800',
};
export const BADGE_INTERRUPT: EventBadge = {
  icon: OctagonPause,
  label: 'Interrupt',
  bg: 'bg-red-100',
  text: 'text-red-800',
};
export const BADGE_COMMAND: EventBadge = {
  icon: Wrench,
  label: 'Command',
  bg: 'bg-gray-100',
  text: 'text-gray-800',
};
export const BADGE_PRIORITY: EventBadge = {
  icon: Goal,
  label: 'Priority',
  bg: 'bg-red-100',
  text: 'text-red-800',
};
export const BADGE_RECURRING: EventBadge = {
  icon: RefreshCw,
  label: 'Recurring',
  bg: 'bg-teal-100',
  text: 'text-teal-800',
};
export const BADGE_ALWAYS_SHOWING: EventBadge = {
  icon: Repeat2,
  label: 'Always Showing',
  bg: 'bg-teal-100',
  text: 'text-teal-800',
};
export const BADGE_SINGLE_DISPLAY: EventBadge = {
  icon: Monitor,
  label: 'Single Display',
  bg: 'bg-blue-100',
  text: 'text-blue-800',
};
export const BADGE_MULTI_DISPLAY: EventBadge = {
  icon: PictureInPicture2,
  label: 'Multi Display',
  bg: 'bg-blue-100',
  text: 'text-blue-800',
};

export const EVENT_LEGEND_BADGES: EventBadge[][] = [
  [BADGE_SINGLE_DISPLAY, BADGE_MULTI_DISPLAY],
  [BADGE_ALWAYS_SHOWING, BADGE_RECURRING, BADGE_SYNCHRONISED],
  [BADGE_PRIORITY, BADGE_INTERRUPT, BADGE_VIEW_ONLY],
  [BADGE_COMMAND, BADGE_GEO_LOCATION, BADGE_INTERACTIVE],
];

export const getEventBadge = (event: Event): EventBadge => {
  if (event.isEditable === false) {
    return BADGE_VIEW_ONLY;
  }
  if (event.eventTypeId === EventTypeId.Sync) {
    return BADGE_SYNCHRONISED;
  }
  if (event.eventTypeId === EventTypeId.Action) {
    return BADGE_INTERACTIVE;
  }
  if (event.isGeoAware === 1) {
    return BADGE_GEO_LOCATION;
  }
  if (event.eventTypeId === EventTypeId.Interrupt) {
    return BADGE_INTERRUPT;
  }
  if (event.eventTypeId === EventTypeId.Command) {
    return BADGE_COMMAND;
  }
  if (event.isPriority >= 1) {
    return BADGE_PRIORITY;
  }
  if (event.recurrenceType && event.recurrenceType !== 'None') {
    return BADGE_RECURRING;
  }
  if (event.isAlways === 1) {
    return BADGE_ALWAYS_SHOWING;
  }
  if (event.displayGroups.length <= 1) {
    return BADGE_SINGLE_DISPLAY;
  }
  return BADGE_MULTI_DISPLAY;
};

const formatUnixTimestamp = (ts: number | null | undefined, timezone: string): string => {
  if (!ts) return '—';
  return formatDateTime(new Date(ts * 1000), timezone);
};

export const getEventColumns = (props: EventActionsProps): ColumnDef<Event>[] => {
  const { t, timezone } = props;
  const getActions = getEventItemActions(props);
  return [
    {
      id: 'eventBadge',
      header: '',
      size: 44,
      minSize: 44,
      maxSize: 44,
      enableHiding: false,
      enableResizing: false,
      enableSorting: false,
      cell: ({ row }) => {
        const badge = getEventBadge(row.original);
        const Icon = badge.icon;
        return (
          <div
            className={`inline-flex items-center justify-center w-7 h-7 rounded-full ${badge.bg} ${badge.text}`}
            title={t(badge.label)}
          >
            <Icon className="w-4 h-4" />
          </div>
        );
      },
    },
    {
      accessorKey: 'eventId',
      header: t('ID'),
      size: 80,
      cell: (info) => <TextCell>{info.getValue<number>()}</TextCell>,
    },
    {
      accessorKey: 'eventTypeId',
      header: t('Event Type'),
      size: 140,
      cell: (info) => {
        const id = info.getValue<number>();
        const label = EVENT_TYPE_LABELS[id] ?? String(id);
        return <StatusCell label={t(label)} type={EVENT_TYPE_STATUS[id] ?? 'neutral'} />;
      },
    },
    {
      accessorKey: 'name',
      header: t('Name'),
      size: 200,
      enableHiding: false,
      cell: (info) => (
        <TextCell weight="bold" truncate>
          {info.getValue<string>() || '—'}
        </TextCell>
      ),
    },
    {
      accessorKey: 'fromDt',
      header: t('Start'),
      size: 160,
      cell: ({ row }) => {
        if (row.original.isAlways === 1) {
          return <StatusCell label={t('Always')} variation="outline" type="neutral" />;
        }
        return <TextCell>{formatUnixTimestamp(row.original.fromDt, timezone)}</TextCell>;
      },
    },
    {
      accessorKey: 'toDt',
      header: t('End'),
      size: 160,
      cell: ({ row }) => {
        if (row.original.isAlways === 1) {
          return <StatusCell label={t('Always')} variation="outline" type="neutral" />;
        }
        return <TextCell>{formatUnixTimestamp(row.original.toDt, timezone)}</TextCell>;
      },
    },
    {
      id: 'event',
      header: t('Event'),
      size: 200,
      cell: ({ row }) => (
        <TextCell truncate>{row.original.campaign ?? row.original.command ?? '—'}</TextCell>
      ),
    },
    {
      accessorKey: 'campaignId',
      header: t('Campaign ID'),
      size: 110,
      cell: (info) => <TextCell>{info.getValue<number | null>() ?? '—'}</TextCell>,
    },
    {
      id: 'displayGroups',
      header: t('Display Groups'),
      size: 200,
      cell: ({ row }) => (
        <TextCell>
          {row.original.displayGroups.map((dg) => dg.displayGroup).join(', ') || '—'}
        </TextCell>
      ),
    },
    {
      accessorKey: 'shareOfVoice',
      header: t('SoV'),
      size: 80,
      cell: (info) => <TextCell>{info.getValue<number | null>() ?? '—'}</TextCell>,
    },
    {
      accessorKey: 'maxPlaysPerHour',
      header: t('Max Plays per Hour'),
      size: 160,
      cell: (info) => <TextCell>{info.getValue<number>()}</TextCell>,
    },
    {
      accessorKey: 'isGeoAware',
      header: t('Geo Aware'),
      size: 110,
      cell: (info) => <CheckMarkCell active={info.getValue<number>() === 1} />,
    },
    {
      id: 'recurring',
      header: t('Recurring'),
      size: 100,
      cell: ({ row }) => <CheckMarkCell active={Boolean(row.original.recurringEvent)} />,
    },
    {
      accessorKey: 'recurringEventDescription',
      header: t('Recurrence Description'),
      size: 200,
      cell: (info) => <TextCell>{info.getValue<string>() || '—'}</TextCell>,
    },
    {
      accessorKey: 'recurrenceType',
      header: t('Recurrence Type'),
      size: 140,
      cell: (info) => <TextCell>{info.getValue<string>() || '—'}</TextCell>,
    },
    {
      accessorKey: 'recurrenceDetail',
      header: t('Recurrence Interval'),
      size: 150,
      cell: (info) => <TextCell>{info.getValue<number | null>() ?? '—'}</TextCell>,
    },
    {
      accessorKey: 'recurrenceRepeatsOn',
      header: t('Recurrence Repeats On'),
      size: 180,
      cell: (info) => <TextCell>{info.getValue<string | null>() || '—'}</TextCell>,
    },
    {
      accessorKey: 'recurrenceRange',
      header: t('Recurrence End'),
      size: 130,
      cell: (info) => <TextCell>{formatUnixTimestamp(info.getValue<number>(), timezone)}</TextCell>,
    },
    {
      accessorKey: 'isPriority',
      header: t('Priority'),
      size: 90,
      cell: (info) => <CheckMarkCell active={info.getValue<number>() === 1} />,
    },
    {
      id: 'criteria',
      header: t('Criteria'),
      size: 90,
      cell: ({ row }) => <CheckMarkCell active={row.original.criteria.length > 0} />,
    },
    {
      accessorKey: 'createdOn',
      header: t('Created On'),
      size: 160,
      cell: (info) => {
        const val = info.getValue<string | undefined>();
        return <TextCell>{val ? formatDateTime(new Date(val), timezone) : '—'}</TextCell>;
      },
    },
    {
      accessorKey: 'updatedOn',
      header: t('Updated On'),
      size: 160,
      cell: (info) => {
        const val = info.getValue<string | null | undefined>();
        return <TextCell>{val ? formatDateTime(new Date(val), timezone) : '—'}</TextCell>;
      },
    },
    {
      accessorKey: 'modifiedByName',
      header: t('Modified By'),
      size: 140,
      cell: (info) => <TextCell>{info.getValue<string | null>() || '—'}</TextCell>,
    },
    {
      id: 'tableActions',
      header: '',
      size: 80,
      minSize: 80,
      maxSize: 80,
      enableHiding: false,
      enableResizing: false,
      cell: ({ row }) => (
        <ActionsCell
          row={row}
          actions={getActions(row.original) as ComponentProps<typeof ActionsCell>['actions']}
        />
      ),
    },
  ];
};

interface GetBulkActionsProps {
  t: TFunction;
  onDelete: () => void;
}

export const getBulkActions = ({
  t,
  onDelete,
}: GetBulkActionsProps): DataTableBulkAction<Event>[] => {
  return [
    {
      label: t('Delete Selected'),
      icon: Trash2,
      onClick: onDelete,
    },
  ];
};
