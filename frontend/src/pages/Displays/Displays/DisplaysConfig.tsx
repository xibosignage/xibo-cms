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
  Edit,
  Trash2,
  LayoutTemplate,
  BadgeCheck,
  CalendarDays,
  Camera,
  RefreshCw,
  Webhook,
  Terminal,
  ArrowRightLeft,
  XCircle,
  Gauge,
  FolderInput,
  RotateCw,
  PlusSquare,
  MonitorCheck,
  MonitorXIcon,
  Info,
  ArrowRight,
  FileX,
  Forward,
  UserPlus2,
} from 'lucide-react';
import { type ComponentProps } from 'react';

import type { FilterConfigItem } from '@/components/ui/FilterInputs';
import type { DataTableBulkAction } from '@/components/ui/table/DataTableBulkActions';
import {
  TextCell,
  ActionsCell,
  CheckMarkCell,
  StatusCell,
  TagsCell,
  MediaCell,
  getSharingColumn,
} from '@/components/ui/table/cells';
import type { Display } from '@/types/display';
import type { ActionItem, BaseModalType } from '@/types/table';
import type { Tag } from '@/types/tag';
import type { UIStatus } from '@/types/uiStatus';
import type { DateLike } from '@/utils/date';

export interface DisplayFilterInput {
  displayId?: number | null;
  name?: string;
  tags?: Tag[];
  mediaInventoryStatus: string | null;
  loggedIn: string | null;
  authorised: string | null;
  xmrRegistered: string | null;
  clientType: string | null;
  displayGroupId: string | null;
  displayProfileId: string | null;
  orientation: string | null;
  commercialLicence: string | null;
  isPlayerSupported: string | null;
  clientCode: string | null;
  customId: string | null;
  macAddress: string | null;
  clientAddress: string | null;
  lastAccessed: string | null;
}

export type ModalType =
  | BaseModalType
  | 'add'
  | 'edit'
  | 'manage'
  | 'authorise'
  | 'checkLicence'
  | 'requestScreenShot'
  | 'collectNow'
  | 'wakeOnLan'
  | 'purgeAll'
  | 'triggerWebhook'
  | 'defaultLayout'
  | 'moveCms'
  | 'moveCmsCancel'
  | 'setBandwidth'
  | 'share'
  | 'sendCommand'
  | 'assignLayout'
  | 'assignMedia'
  | 'manageGroups'
  | 'bulkAuthorise'
  | 'bulkCheckLicence'
  | 'bulkRequestScreenShot'
  | 'bulkCollectNow'
  | 'bulkTriggerWebhook'
  | 'bulkDefaultLayout'
  | 'bulkSendCommand'
  | 'bulkMoveCms'
  | 'schedule'
  | null;

export const INITIAL_FILTER_STATE: DisplayFilterInput = {
  displayId: null,
  name: '',
  tags: [],
  mediaInventoryStatus: null,
  loggedIn: null,
  authorised: null,
  xmrRegistered: null,
  clientType: null,
  displayGroupId: null,
  displayProfileId: null,
  orientation: null,
  commercialLicence: null,
  isPlayerSupported: null,
  clientCode: null,
  customId: null,
  macAddress: null,
  clientAddress: null,
  lastAccessed: null,
};

const getCommercialLicenceLabel = (t: TFunction, value: number): string => {
  switch (value) {
    case 1:
      return t('Licensed fully');
    case 2:
      return t('Trial');
    case 0:
      return t('Not licenced');
    case 3:
      return t('Not applicable');
    default:
      return '';
  }
};

const getCommercialLicenceStatus = (value: number): UIStatus => {
  switch (value) {
    case 1:
      return 'success';
    case 2:
      return 'warning';
    case 0:
      return 'danger';
    default:
      return 'neutral';
  }
};

const getLastCommandLabel = (t: TFunction, value: number): string => {
  switch (value) {
    case 1:
      return t('Success');
    case 0:
      return t('Failed');
    default:
      return t('Unknown');
  }
};

const getInventoryStatusLabel = (t: TFunction, status: number): string => {
  switch (status) {
    case 1:
      return t('Up to date');
    case 2:
      return t('Downloading');
    case 3:
      return t('Out of date');
    default:
      return t('Unknown');
  }
};

const getInventoryStatusType = (status: number): UIStatus => {
  switch (status) {
    case 1:
      return 'success';
    case 2:
      return 'danger';
    case 3:
      return 'warning';
    default:
      return 'neutral';
  }
};

export const getClientTypeLabel = (t: TFunction, clientType: string | null): string => {
  switch (clientType) {
    case 'android':
      return t('Android');
    case 'windows':
      return t('Windows');
    case 'linux':
      return t('Linux');
    case 'lg':
      return t('webOS');
    case 'sssp':
      return t('Tizen');
    case 'chromeOS':
      return t('ChromeOS');
    default:
      return clientType ?? '';
  }
};

export const getClientTypeOptions = (t: TFunction): { label: string; value: string }[] => [
  { label: t('All'), value: '' },
  { label: t('Android'), value: 'android' },
  { label: t('Windows'), value: 'windows' },
  { label: t('Linux'), value: 'linux' },
  { label: t('webOS'), value: 'lg' },
  { label: t('Tizen'), value: 'sssp' },
  { label: t('ChromeOS'), value: 'chromeOS' },
];

export const getBaseFilterKeys = (
  t: TFunction,
  canTag = false,
): FilterConfigItem<DisplayFilterInput>[] => [
  {
    label: t('ID'),
    placeholder: ' ',
    name: 'displayId',
    type: 'number',
  },
  {
    label: t('Name'),
    name: 'name',
    type: 'text',
    className: '',
    placeholder: ' ',
  },
  ...(canTag
    ? ([
        {
          label: t('Tags'),
          name: 'tags',
          type: 'tags',
          placeholder: ' ',
          className: '',
        },
      ] as FilterConfigItem<DisplayFilterInput>[])
    : []),
  {
    label: t('Status'),
    name: 'mediaInventoryStatus',
    className: '',
    options: [
      { label: t('All'), value: '' },
      { label: t('Up to date'), value: '1' },
      { label: t('Downloading'), value: '2' },
      { label: t('Out of date'), value: '3' },
    ],
  },
  {
    label: t('Logged In'),
    name: 'loggedIn',
    className: '',
    options: [
      { label: t('All'), value: '' },
      { label: t('Yes'), value: '1' },
      { label: t('No'), value: '0' },
    ],
  },
  {
    label: t('Authorised'),
    name: 'authorised',
    className: '',
    options: [
      { label: t('All'), value: '' },
      { label: t('Yes'), value: '1' },
      { label: t('No'), value: '0' },
    ],
  },
  {
    label: t('XMR Registered'),
    name: 'xmrRegistered',
    className: '',
    options: [
      { label: t('All'), value: '' },
      { label: t('Yes'), value: '1' },
      { label: t('No'), value: '0' },
    ],
  },
  {
    label: t('Player Type'),
    name: 'clientType',
    className: '',
    options: getClientTypeOptions(t),
  },
  {
    label: t('Display Group'),
    name: 'displayGroupId',
    className: '',
    placeholder: t('All'),
    options: [],
  },
  {
    label: t('Display Profile'),
    name: 'displayProfileId',
    className: '',
    placeholder: t('All'),
    options: [],
  },
  {
    label: t('Orientation'),
    name: 'orientation',
    className: '',
    options: [
      { label: t('All'), value: '' },
      { label: t('Landscape'), value: 'landscape' },
      { label: t('Portrait'), value: 'portrait' },
    ],
  },
  {
    label: t('Commercial Licence'),
    name: 'commercialLicence',
    className: '',
    options: [
      { label: t('All'), value: '' },
      { label: t('Licensed fully'), value: '1' },
      { label: t('Trial'), value: '2' },
      { label: t('Not licenced'), value: '0' },
      { label: t('Not applicable'), value: '3' },
    ],
  },
  {
    label: t('Player Supported'),
    name: 'isPlayerSupported',
    className: '',
    options: [
      { label: t('All'), value: '' },
      { label: t('Yes'), value: '1' },
      { label: t('No'), value: '0' },
    ],
  },
  {
    label: t('Player Code'),
    name: 'clientCode',
    type: 'text',
    className: '',
    placeholder: t('Player Code'),
  },
  {
    label: t('Custom ID'),
    name: 'customId',
    type: 'text',
    className: '',
    placeholder: t('Custom ID'),
  },
  {
    label: t('Mac Address'),
    name: 'macAddress',
    type: 'text',
    className: '',
    placeholder: t('Mac Address'),
  },
  {
    label: t('IP Address'),
    name: 'clientAddress',
    type: 'text',
    className: '',
    placeholder: t('IP Address'),
  },
  {
    label: t('Last Accessed'),
    name: 'lastAccessed',
    type: 'date',
    className: '',
  },
];

export interface DisplayActionsProps {
  t: TFunction;
  canModify?: boolean;
  canTag?: boolean;
  canUserShare?: boolean;
  canLimitedView?: boolean;
  canViewLayout?: boolean;
  scheduleWithView?: boolean;
  isSuperAdmin?: boolean;
  onDelete: (id: number) => void;
  openEditModal: (row: Display) => void;
  openMoveModal?: (row: Display | Display[]) => void;
  openShareModal?: (id: number) => void;
  onAuthorise: (display: Display) => void;
  onManage: (display: Display) => void;
  onCheckLicence: (display: Display) => void;
  onRequestScreenShot: (display: Display) => void;
  onCollectNow: (display: Display) => void;
  onWakeOnLan: (display: Display) => void;
  onPurgeAll: (display: Display) => void;
  onTriggerWebhook: (display: Display) => void;
  onSetDefaultLayout: (display: Display) => void;
  onMoveCms: (display: Display) => void;
  onMoveCmsCancel: (display: Display) => void;
  onAddToGroup: (display: Display) => void;
  onAssignLayouts: (display: Display) => void;
  onAssignFiles: (display: Display) => void;
  onSendCommand: (display: Display) => void;
  onJumpToScheduledLayouts?: (displayGroupId: number) => void;
  onSchedule?: (display: Display) => void;
  onPreviewScreenshot?: (display: Display) => void;
  formatDateTime: (value: DateLike) => string;
}

// Client types for which a commercial licence check is available (matches release44).
const LICENCE_CHECK_CLIENT_TYPES = ['android', 'lg', 'sssp', 'chromeOS'];

export const getDisplayItemActions = ({
  t,
  canModify = false,
  canUserShare = false,
  canLimitedView = false,
  canViewLayout = false,
  scheduleWithView = false,
  isSuperAdmin = false,
  onDelete,
  openEditModal,
  openMoveModal,
  openShareModal,
  onAuthorise,
  onManage,
  onCheckLicence,
  onRequestScreenShot,
  onCollectNow,
  onWakeOnLan,
  onPurgeAll,
  onTriggerWebhook,
  onSetDefaultLayout,
  onMoveCms,
  onMoveCmsCancel,
  onAddToGroup,
  onAssignLayouts,
  onAssignFiles,
  onSendCommand,
  onJumpToScheduledLayouts,
  onSchedule,
}: DisplayActionsProps): ((display: Display) => ActionItem[]) => {
  return (display: Display) => {
    const canEdit = !!display.userPermissions?.edit;
    const canDelete = !!display.userPermissions?.delete;
    const canShare = !!display.userPermissions?.modifyPermissions;

    // "Limited view" block: visible with edit, or when the user has the
    // displays.limitedView feature (mirrors release44's grid button guards).
    const limitedBlock = (canModify && canEdit) || canLimitedView;
    // Items inside the limited-view block that still require edit permission.
    const limitedEdit = canEdit && (canModify || canLimitedView);

    const actions: ActionItem[] = [];

    const addSeparator = () => {
      if (actions.length > 0 && !actions[actions.length - 1]?.isSeparator) {
        actions.push({ isSeparator: true });
      }
    };

    // Quick action
    if (canModify && canEdit) {
      actions.push({
        label: t('Edit'),
        icon: Edit,
        onClick: () => openEditModal(display),
        isQuickAction: true,
        variant: 'primary' as const,
      });
    }

    // Dropdown menu actions
    if (canModify && canEdit) {
      actions.push({
        label: t('Edit'),
        icon: Edit,
        onClick: () => openEditModal(display),
      });
    }

    if (canModify && canEdit && openMoveModal) {
      actions.push({
        label: t('Move'),
        icon: FolderInput,
        onClick: () => openMoveModal(display),
      });
    }

    if (canModify && canShare && canUserShare && openShareModal) {
      actions.push({
        label: t('Share'),
        icon: UserPlus2,
        onClick: () => openShareModal(display.displayGroupId),
      });
    }

    if (onSchedule && (canEdit || scheduleWithView)) {
      actions.push({
        label: t('Schedule'),
        icon: CalendarDays,
        onClick: () => onSchedule(display),
      });
    }

    if (limitedBlock) {
      actions.push({
        label: t('Request Screenshot'),
        icon: RotateCw,
        onClick: () => onRequestScreenShot(display),
      });
    }

    if (canModify && canShare) {
      actions.push({
        label: t('Add to Group'),
        icon: PlusSquare,
        onClick: () => onAddToGroup(display),
      });
    }

    if (canModify && canEdit) {
      actions.push({
        label: display.licensed === 1 ? t('Unauthorise') : t('Authorise'),
        icon: display.licensed === 1 ? MonitorXIcon : MonitorCheck,
        onClick: () => onAuthorise(display),
      });

      actions.push({
        label: t('Manage'),
        icon: Info,
        rightIcon: ArrowRight,
        onClick: () => onManage(display),
      });
    }

    if (limitedEdit && canViewLayout && onJumpToScheduledLayouts) {
      addSeparator();
      actions.push({
        label: t('Scheduled Layouts'),
        rightIcon: ArrowRight,
        onClick: () => onJumpToScheduledLayouts(display.displayGroupId),
      });
    }

    if (limitedEdit) {
      addSeparator();
      actions.push({
        label: t('Assign Layouts'),
        onClick: () => onAssignLayouts(display),
      });

      actions.push({
        label: t('Assign Files'),
        onClick: () => onAssignFiles(display),
      });
    }

    if (limitedBlock) {
      addSeparator();
      actions.push({
        label: t('Collect Now'),
        onClick: () => onCollectNow(display),
      });
    }

    if (limitedEdit) {
      actions.push({
        label: t('Trigger a web hook'),
        onClick: () => onTriggerWebhook(display),
      });

      actions.push({
        label: t('Wake on LAN'),
        onClick: () => onWakeOnLan(display),
      });
    }

    if (limitedBlock) {
      actions.push({
        label: t('Send Command'),
        onClick: () => onSendCommand(display),
      });
    }

    if (
      canModify &&
      canEdit &&
      display.clientType &&
      LICENCE_CHECK_CLIENT_TYPES.includes(display.clientType)
    ) {
      actions.push({
        label: t('Check Licence'),
        onClick: () => onCheckLicence(display),
      });
    }

    if (canModify && canEdit) {
      actions.push({
        label: t('Default Layout'),
        onClick: () => onSetDefaultLayout(display),
      });
    }

    if (isSuperAdmin && limitedEdit) {
      addSeparator();
      actions.push({
        label: t('Purge All Media'),
        icon: FileX,
        onClick: () => onPurgeAll(display),
      });
    }

    if (limitedEdit) {
      addSeparator();
      actions.push({
        label: t('Transfer to another CMS'),
        icon: Forward,
        onClick: () => onMoveCms(display),
      });

      if (display.newCmsAddress) {
        actions.push({
          label: t('Cancel CMS Transfer'),
          icon: XCircle,
          onClick: () => onMoveCmsCancel(display),
        });
      }
    }

    if (canModify && canDelete) {
      addSeparator();
      actions.push({
        label: t('Delete'),
        icon: Trash2,
        onClick: () => onDelete(display.displayId),
        variant: 'danger' as const,
      });
    }

    // Drop a trailing separator if the last visible group was gated out.
    if (actions.length > 0 && actions[actions.length - 1]?.isSeparator) {
      actions.pop();
    }

    return actions;
  };
};

export const getDisplayColumns = (props: DisplayActionsProps): ColumnDef<Display>[] => {
  const { t, formatDateTime, canTag = false } = props;
  const getActions = getDisplayItemActions(props);

  return [
    {
      accessorKey: 'displayId',
      header: t('ID'),
      size: 70,
      cell: (info) => <TextCell>{info.getValue<number>()}</TextCell>,
    },
    {
      accessorKey: 'display',
      header: t('Display'),
      size: 200,
      enableHiding: false,
      cell: (info) => <TextCell weight="bold">{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'mediaInventoryStatus',
      header: t('Status'),
      size: 130,
      cell: (info) => {
        const status = info.getValue<number>();
        return (
          <StatusCell
            label={getInventoryStatusLabel(t, status)}
            type={getInventoryStatusType(status)}
          />
        );
      },
    },
    {
      accessorKey: 'clientType',
      header: t('Player Type'),
      size: 120,
      cell: (info) => <TextCell>{getClientTypeLabel(t, info.getValue<string | null>())}</TextCell>,
    },
    {
      accessorKey: 'clientAddress',
      header: t('IP Address'),
      size: 140,
      cell: (info) => <TextCell>{info.getValue<string | null>() ?? ''}</TextCell>,
    },
    {
      accessorKey: 'licensed',
      header: t('Authorised'),
      size: 100,
      cell: (info) => <CheckMarkCell active={info.getValue() === 1} />,
    },
    {
      accessorKey: 'loggedIn',
      header: t('Logged In'),
      size: 100,
      cell: (info) => <CheckMarkCell active={info.getValue() === 1} />,
    },
    {
      accessorKey: 'currentLayout',
      header: t('Current Layout'),
      size: 180,
      cell: (info) => <TextCell>{info.getValue<string | null>() ?? ''}</TextCell>,
    },
    {
      accessorKey: 'deviceName',
      header: t('Device Name'),
      size: 160,
      cell: (info) => <TextCell>{info.getValue<string | null>() ?? ''}</TextCell>,
    },
    {
      accessorKey: 'address',
      header: t('Address'),
      size: 200,
      cell: (info) => <TextCell>{info.getValue<string | null>() ?? ''}</TextCell>,
    },
    {
      accessorKey: 'storageAvailableSpace',
      header: t('Storage Available'),
      size: 150,
      cell: (info) => <TextCell>{info.getValue<number | null>()?.toLocaleString() ?? ''}</TextCell>,
    },
    {
      accessorKey: 'storageTotalSpace',
      header: t('Storage Total'),
      size: 130,
      cell: (info) => <TextCell>{info.getValue<number | null>()?.toLocaleString() ?? ''}</TextCell>,
    },
    {
      id: 'storageFree',
      header: t('Storage Free %'),
      size: 130,
      accessorFn: (row) => {
        const avail = row.storageAvailableSpace;
        const total = row.storageTotalSpace;
        if (avail === null || total === null || total === 0) {
          return '';
        }
        return ((avail / total) * 100).toFixed(1) + '%';
      },
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'description',
      header: t('Description'),
      size: 200,
      cell: (info) => <TextCell>{info.getValue<string | null>() ?? ''}</TextCell>,
    },
    {
      accessorKey: 'orientation',
      header: t('Orientation'),
      size: 110,
      cell: (info) => <TextCell>{info.getValue<string | null>() ?? ''}</TextCell>,
    },
    {
      accessorKey: 'resolution',
      header: t('Resolution'),
      size: 120,
      cell: (info) => <TextCell>{info.getValue<string | null>() ?? ''}</TextCell>,
    },
    ...(canTag
      ? ([
          {
            accessorKey: 'tags',
            header: t('Tags'),
            size: 150,
            enableSorting: false,
            cell: (info) => {
              const tags = info.getValue<Tag[]>() ?? [];
              return (
                <TagsCell
                  tags={tags.map((tag) => ({
                    id: tag.tagId,
                    label: tag.value ? `${tag.tag}|${tag.value}` : tag.tag,
                  }))}
                />
              );
            },
          },
        ] as ColumnDef<Display>[])
      : []),
    {
      accessorKey: 'defaultLayout',
      header: t('Default Layout'),
      size: 180,
      cell: (info) => <TextCell>{info.getValue<string | null>() ?? ''}</TextCell>,
    },
    {
      accessorKey: 'incSchedule',
      header: t('Interleave Default'),
      size: 160,
      cell: (info) => <CheckMarkCell active={info.getValue() === 1} />,
    },
    {
      accessorKey: 'emailAlert',
      header: t('Email Alert'),
      size: 110,
      cell: (info) => <CheckMarkCell active={info.getValue() === 1} />,
    },
    {
      accessorKey: 'lastAccessed',
      header: t('Last Accessed'),
      size: 180,
      cell: (info) => {
        const value = info.getValue<number | null>();
        return <TextCell>{value ? formatDateTime(new Date(Number(value) * 1000)) : ''}</TextCell>;
      },
    },
    {
      accessorKey: 'displayProfile',
      header: t('Display Profile'),
      size: 160,
      cell: (info) => <TextCell>{info.getValue<string | null>() ?? ''}</TextCell>,
    },
    {
      accessorKey: 'clientVersion',
      header: t('Version'),
      size: 180,
      cell: (info) => {
        const { clientType, clientVersion, clientCode } = info.row.original;
        const typeAndVersion = [getClientTypeLabel(t, clientType), clientVersion]
          .filter(Boolean)
          .join(' ');
        const value =
          clientCode != null && typeAndVersion !== ''
            ? `${typeAndVersion}-${clientCode}`
            : typeAndVersion;
        return <TextCell>{value}</TextCell>;
      },
    },
    {
      accessorKey: 'isPlayerSupported',
      header: t('Supported?'),
      size: 110,
      cell: (info) => <CheckMarkCell active={info.getValue() === 1} />,
    },
    {
      accessorKey: 'macAddress',
      header: t('Mac Address'),
      size: 150,
      cell: (info) => <TextCell>{info.getValue<string | null>() ?? ''}</TextCell>,
    },
    {
      accessorKey: 'timeZone',
      header: t('Timezone'),
      size: 160,
      cell: (info) => <TextCell>{info.getValue<string | null>() ?? ''}</TextCell>,
    },
    {
      accessorKey: 'languages',
      header: t('Languages'),
      size: 130,
      cell: (info) => <TextCell>{info.getValue<string | null>() ?? ''}</TextCell>,
    },
    {
      accessorKey: 'latitude',
      header: t('Latitude'),
      size: 110,
      cell: (info) => <TextCell>{info.getValue<number | null>()?.toString() ?? ''}</TextCell>,
    },
    {
      accessorKey: 'longitude',
      header: t('Longitude'),
      size: 110,
      cell: (info) => <TextCell>{info.getValue<number | null>()?.toString() ?? ''}</TextCell>,
    },
    {
      accessorKey: 'screenShotRequested',
      header: t('Screen shot?'),
      size: 120,
      cell: (info) => <CheckMarkCell active={info.getValue() === 1} />,
    },
    {
      id: 'thumbnail',
      header: t('Thumbnail'),
      size: 120,
      enableSorting: false,
      cell: ({ row }) => (
        <MediaCell
          thumb={row.original.thumbnail || undefined}
          alt={row.original.display}
          mediaType="image"
          onPreview={
            props.onPreviewScreenshot ? () => props.onPreviewScreenshot!(row.original) : undefined
          }
        />
      ),
    },
    {
      id: 'cmsTransfer',
      header: t('CMS Transfer?'),
      size: 130,
      accessorFn: (row) => row.newCmsAddress,
      cell: (info) => <CheckMarkCell active={info.getValue<string | null>() !== null} />,
    },
    {
      accessorKey: 'bandwidthLimit',
      header: t('Bandwidth Limit'),
      size: 140,
      cell: (info) => <TextCell>{info.getValue<number | null>()?.toLocaleString() ?? ''}</TextCell>,
    },
    {
      accessorKey: 'lastCommandSuccess',
      header: t('Last Command'),
      size: 130,
      cell: (info) => <TextCell>{getLastCommandLabel(t, info.getValue<number>())}</TextCell>,
    },
    {
      id: 'xmrRegistered',
      header: t('XMR Registered'),
      size: 140,
      accessorFn: (row) => row.xmrChannel,
      cell: (info) => <CheckMarkCell active={info.getValue<string | null>() !== null} />,
    },
    {
      accessorKey: 'commercialLicence',
      header: t('Commercial Licence'),
      size: 160,
      cell: (info) => {
        const value = info.getValue<number>();
        return (
          <StatusCell
            label={getCommercialLicenceLabel(t, value)}
            type={getCommercialLicenceStatus(value)}
          />
        );
      },
    },
    {
      id: 'remote',
      header: t('Remote'),
      size: 130,
      accessorFn: (row) => row.teamViewerSerial ?? row.webkeySerial ?? '',
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    getSharingColumn<Display>(t),
    {
      accessorKey: 'screenSize',
      header: t('Screen Size'),
      size: 120,
      cell: (info) => <TextCell>{info.getValue<number | null>()?.toString() ?? ''}</TextCell>,
    },
    {
      accessorKey: 'isMobile',
      header: t('Is Mobile?'),
      size: 110,
      cell: (info) => <CheckMarkCell active={info.getValue() === 1} />,
    },
    {
      accessorKey: 'isOutdoor',
      header: t('Outdoor?'),
      size: 100,
      cell: (info) => <CheckMarkCell active={info.getValue() === 1} />,
    },
    {
      accessorKey: 'ref1',
      header: t('Reference 1'),
      size: 130,
      cell: (info) => <TextCell>{info.getValue<string | null>() ?? ''}</TextCell>,
    },
    {
      accessorKey: 'ref2',
      header: t('Reference 2'),
      size: 130,
      cell: (info) => <TextCell>{info.getValue<string | null>() ?? ''}</TextCell>,
    },
    {
      accessorKey: 'ref3',
      header: t('Reference 3'),
      size: 130,
      cell: (info) => <TextCell>{info.getValue<string | null>() ?? ''}</TextCell>,
    },
    {
      accessorKey: 'ref4',
      header: t('Reference 4'),
      size: 130,
      cell: (info) => <TextCell>{info.getValue<string | null>() ?? ''}</TextCell>,
    },
    {
      accessorKey: 'ref5',
      header: t('Reference 5'),
      size: 130,
      cell: (info) => <TextCell>{info.getValue<string | null>() ?? ''}</TextCell>,
    },
    {
      accessorKey: 'customId',
      header: t('Custom ID'),
      size: 120,
      cell: (info) => <TextCell>{info.getValue<string | null>() ?? ''}</TextCell>,
    },
    {
      accessorKey: 'costPerPlay',
      header: t('Cost Per Play'),
      size: 130,
      cell: (info) => <TextCell>{info.getValue<number | null>()?.toString() ?? ''}</TextCell>,
    },
    {
      accessorKey: 'impressionsPerPlay',
      header: t('Impressions Per Play'),
      size: 170,
      cell: (info) => <TextCell>{info.getValue<number | null>()?.toString() ?? ''}</TextCell>,
    },
    {
      accessorKey: 'createdDt',
      header: t('Created Date'),
      size: 170,
      cell: (info) => <TextCell>{formatDateTime(info.getValue<string | null>())}</TextCell>,
    },
    {
      accessorKey: 'modifiedDt',
      header: t('Modified Date'),
      size: 170,
      cell: (info) => <TextCell>{formatDateTime(info.getValue<string | null>())}</TextCell>,
    },
    {
      accessorKey: 'countFaults',
      header: t('Faults?'),
      size: 100,
      cell: (info) => <TextCell>{info.getValue<number>().toString()}</TextCell>,
    },
    {
      accessorKey: 'osVersion',
      header: t('OS Version'),
      size: 130,
      cell: (info) => <TextCell>{info.getValue<string | null>() ?? ''}</TextCell>,
    },
    {
      accessorKey: 'osSdk',
      header: t('OS SDK'),
      size: 110,
      cell: (info) => <TextCell>{info.getValue<string | null>() ?? ''}</TextCell>,
    },
    {
      accessorKey: 'manufacturer',
      header: t('Manufacturer'),
      size: 140,
      cell: (info) => <TextCell>{info.getValue<string | null>() ?? ''}</TextCell>,
    },
    {
      accessorKey: 'brand',
      header: t('Brand'),
      size: 110,
      cell: (info) => <TextCell>{info.getValue<string | null>() ?? ''}</TextCell>,
    },
    {
      accessorKey: 'model',
      header: t('Model'),
      size: 130,
      cell: (info) => <TextCell>{info.getValue<string | null>() ?? ''}</TextCell>,
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
  onMove?: () => void;
  onShare?: () => void;
  onBulkAuthorise?: () => void;
  onBulkSetDefaultLayout?: () => void;
  onBulkCheckLicence?: () => void;
  onBulkRequestScreenShot?: () => void;
  onBulkCollectNow?: () => void;
  onBulkTriggerWebhook?: () => void;
  onSetBandwidth?: () => void;
  onBulkSendCommand?: () => void;
  onBulkMoveCms?: () => void;
}

export const getBulkActions = ({
  t,
  onDelete,
  onMove,
  onShare,
  onBulkAuthorise,
  onBulkSetDefaultLayout,
  onBulkCheckLicence,
  onBulkRequestScreenShot,
  onBulkCollectNow,
  onBulkTriggerWebhook,
  onSetBandwidth,
  onBulkSendCommand,
  onBulkMoveCms,
}: GetBulkActionsProps): DataTableBulkAction<Display>[] => {
  return [
    {
      label: t('Toggle Authorise'),
      icon: MonitorCheck,
      onClick: () => onBulkAuthorise && onBulkAuthorise(),
    },
    {
      label: t('Set Default Layout'),
      icon: LayoutTemplate,
      onClick: () => onBulkSetDefaultLayout && onBulkSetDefaultLayout(),
    },
    ...(onMove
      ? [
          {
            label: t('Move'),
            icon: FolderInput,
            onClick: onMove,
          },
        ]
      : []),
    {
      label: t('Check Licence'),
      icon: BadgeCheck,
      onClick: () => onBulkCheckLicence && onBulkCheckLicence(),
    },
    {
      label: t('Request Screen Shot'),
      icon: Camera,
      onClick: () => onBulkRequestScreenShot && onBulkRequestScreenShot(),
    },
    {
      label: t('Collect Now'),
      icon: RefreshCw,
      onClick: () => onBulkCollectNow && onBulkCollectNow(),
    },
    {
      label: t('Trigger a web hook'),
      icon: Webhook,
      onClick: () => onBulkTriggerWebhook && onBulkTriggerWebhook(),
    },
    ...(onShare
      ? [
          {
            label: t('Share'),
            icon: UserPlus2,
            onClick: onShare,
          },
        ]
      : []),
    {
      label: t('Send Command'),
      icon: Terminal,
      onClick: () => onBulkSendCommand && onBulkSendCommand(),
    },
    {
      label: t('Transfer to another CMS'),
      icon: ArrowRightLeft,
      onClick: () => onBulkMoveCms && onBulkMoveCms(),
    },
    {
      label: t('Set Bandwidth'),
      icon: Gauge,
      onClick: () => onSetBandwidth && onSetBandwidth(),
    },
    {
      label: t('Delete Selected'),
      icon: Trash2,
      onClick: onDelete,
    },
  ];
};
