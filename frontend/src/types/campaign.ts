import type { Tag } from './tag';

export interface Campaign {
  campaignId: number;
  ownerId: number;
  type: string;

  campaign: string;

  isLayoutSpecific: number;
  numberLayouts: number;
  totalDuration: number;

  tags: Tag[];

  folderId: number;
  permissionsFolderId: number;

  cyclePlaybackEnabled: number;
  playCount: number;
  listPlayOrder: 'block' | string;

  targetType: string | null;
  target: number;

  startDt: number;
  endDt: number;

  plays: number;
  spend: number;
  impressions: number;

  lastPopId: number | null;

  ref1: string | null;
  ref2: string | null;
  ref3: string | null;
  ref4: string | null;
  ref5: string | null;

  createdAt: string;
  modifiedAt: string;
  modifiedBy: number;
  modifiedByName: string;

  displayGroupIds: number[];

  retired: number;
}
