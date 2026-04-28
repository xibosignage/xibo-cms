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
} from '@/components/ui/table/cells';
import type { Display } from '@/types/display';
import type { ActionItem, BaseModalType } from '@/types/table';
import type { Tag } from '@/types/tag';
import type { UIStatus } from '@/types/uiStatus';

export interface DisplayFilterInput {
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
  | null;

export const INITIAL_FILTER_STATE: DisplayFilterInput = {
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
      return 'warning';
    case 3:
      return 'danger';
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
  { label: t('Android'), value: 'android' },
  { label: t('Windows'), value: 'windows' },
  { label: t('Linux'), value: 'linux' },
  { label: t('webOS'), value: 'lg' },
  { label: t('Tizen'), value: 'sssp' },
  { label: t('ChromeOS'), value: 'chromeOS' },
];

export const getBaseFilterKeys = (t: TFunction): FilterConfigItem<DisplayFilterInput>[] => [
  {
    label: t('Status'),
    name: 'mediaInventoryStatus',
    className: '',
    shouldTranslateOptions: false,
    showAllOption: true,
    options: [
      { label: t('Up to date'), value: '1' },
      { label: t('Downloading'), value: '2' },
      { label: t('Out of date'), value: '3' },
    ],
  },
  {
    label: t('Logged In'),
    name: 'loggedIn',
    className: '',
    shouldTranslateOptions: false,
    showAllOption: true,
    options: [
      { label: t('Yes'), value: '1' },
      { label: t('No'), value: '0' },
    ],
  },
  {
    label: t('Authorised'),
    name: 'authorised',
    className: '',
    shouldTranslateOptions: false,
    showAllOption: true,
    options: [
      { label: t('Yes'), value: '1' },
      { label: t('No'), value: '0' },
    ],
  },
  {
    label: t('XMR Registered'),
    name: 'xmrRegistered',
    className: '',
    shouldTranslateOptions: false,
    showAllOption: true,
    options: [
      { label: t('Yes'), value: '1' },
      { label: t('No'), value: '0' },
    ],
  },
  {
    label: t('Player Type'),
    name: 'clientType',
    className: '',
    shouldTranslateOptions: false,
    showAllOption: true,
    options: getClientTypeOptions(t),
  },
  {
    label: t('Display Group'),
    name: 'displayGroupId',
    className: '',
    shouldTranslateOptions: false,
    showAllOption: true,
    options: [],
  },
  {
    label: t('Display Profile'),
    name: 'displayProfileId',
    className: '',
    shouldTranslateOptions: false,
    showAllOption: true,
    options: [],
  },
  {
    label: t('Orientation'),
    name: 'orientation',
    className: '',
    shouldTranslateOptions: false,
    showAllOption: true,
    options: [
      { label: t('Landscape'), value: 'landscape' },
      { label: t('Portrait'), value: 'portrait' },
    ],
  },
  {
    label: t('Commercial Licence'),
    name: 'commercialLicence',
    className: '',
    shouldTranslateOptions: false,
    showAllOption: true,
    options: [
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
    shouldTranslateOptions: false,
    showAllOption: true,
    options: [
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
    type: 'text',
    className: '',
    placeholder: t('YYYY-MM-DD'),
  },
];

export interface DisplayActionsProps {
  t: TFunction;
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
}

export const getDisplayItemActions = ({
  t,
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
}: DisplayActionsProps): ((display: Display) => ActionItem[]) => {
  return (display: Display) => {
    const actions: ActionItem[] = [];

    const canEdit = display.userPermissions?.edit ?? 1;
    const canDelete = display.userPermissions?.delete ?? 1;
    const canShare = display.userPermissions?.modifyPermissions ?? 1;

    // Quick actions
    if (canEdit) {
      actions.push({
        label: t('Edit'),
        icon: Edit,
        onClick: () => openEditModal(display),
        isQuickAction: true,
        variant: 'primary' as const,
      });
    }
    // Dropdown menu actions
    if (canEdit) {
      actions.push({
        label: t('Edit'),
        icon: Edit,
        onClick: () => openEditModal(display),
      });
    }

    if (canEdit && openMoveModal) {
      actions.push({
        label: t('Move'),
        icon: FolderInput,
        onClick: () => openMoveModal(display),
      });
    }

    if (canShare && openShareModal) {
      actions.push({
        label: t('Share'),
        icon: UserPlus2,
        onClick: () => openShareModal(display.displayGroupId),
      });
    }

    actions.push({
      label: t('Schedule'),
      icon: CalendarDays,
      onClick: () => console.log('Schedule', display.displayGroupId),
    });

    actions.push({
      label: t('Update Thumbnail'),
      icon: RotateCw,
      onClick: () => onRequestScreenShot(display),
    });

    actions.push({
      label: t('Add to Group'),
      icon: PlusSquare,
      onClick: () => onAddToGroup(display),
    });

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

    actions.push({ isSeparator: true });

    actions.push({
      label: t('Scheduled Layouts'),
      rightIcon: ArrowRight,
      onClick: () => onJumpToScheduledLayouts && onJumpToScheduledLayouts(display.displayGroupId),
    });

    actions.push({
      label: t('Assign Layouts'),
      onClick: () => onAssignLayouts(display),
    });

    actions.push({
      label: t('Assign Files'),
      onClick: () => onAssignFiles(display),
    });

    actions.push({
      label: t('Collect Now'),
      onClick: () => onCollectNow(display),
    });

    actions.push({
      label: t('Trigger a web hook'),
      onClick: () => onTriggerWebhook(display),
    });

    actions.push({
      label: t('Wake on LAN'),
      onClick: () => onWakeOnLan(display),
    });

    actions.push({
      label: t('Send Command'),
      onClick: () => onSendCommand(display),
    });

    actions.push({
      label: t('Check Licence'),
      onClick: () => onCheckLicence(display),
    });

    actions.push({
      label: t('Default Layout'),
      onClick: () => onSetDefaultLayout(display),
    });

    actions.push({ isSeparator: true });

    actions.push({
      label: t('Purge All Media'),
      icon: FileX,
      onClick: () => onPurgeAll(display),
    });

    actions.push({
      label: t('Transfer to another CMS'),
      icon: Forward,
      onClick: () => onMoveCms(display),
    });

    actions.push({
      label: t('Cancel CMS Transfer'),
      icon: XCircle,
      onClick: () => onMoveCmsCancel(display),
    });

    if (canDelete) {
      actions.push({ isSeparator: true });

      actions.push({
        label: t('Delete'),
        icon: Trash2,
        onClick: () => onDelete(display.displayId),
        variant: 'danger' as const,
      });
    }

    return actions;
  };
};

export const getDisplayColumns = (props: DisplayActionsProps): ColumnDef<Display>[] => {
  const { t } = props;
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
    {
      accessorKey: 'tags',
      header: t('Tags'),
      size: 150,
      enableSorting: false,
      cell: (info) => {
        const tags = info.getValue<Tag[]>() ?? [];
        return <TagsCell tags={tags.map((tag) => ({ id: tag.tagId, label: tag.tag }))} />;
      },
    },
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
      cell: (info) => <TextCell>{info.getValue<string | null>() ?? ''}</TextCell>,
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
      size: 110,
      cell: (info) => <TextCell>{info.getValue<string | null>() ?? ''}</TextCell>,
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
          thumb={`/display/screenshot/${row.original.displayId}`}
          alt={row.original.display}
          mediaType="image"
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
    {
      accessorKey: 'groupsWithPermissions',
      header: t('Sharing'),
      size: 150,
      cell: (info) => <TextCell>{info.getValue<string | null>() ?? ''}</TextCell>,
    },
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
      cell: (info) => <TextCell>{info.getValue<string | null>() ?? ''}</TextCell>,
    },
    {
      accessorKey: 'modifiedDt',
      header: t('Modified Date'),
      size: 170,
      cell: (info) => <TextCell>{info.getValue<string | null>() ?? ''}</TextCell>,
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
