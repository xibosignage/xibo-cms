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

export interface LatestNewsItem {
  title: string;
  description: string;
  link: string;
  date: string;
  image: string;
}

export interface LibraryType {
  title: string;
  count: number;
  size: number;
}

export interface MediaDashboardData {
  library: {
    countOf: number;
    size: string;
    types: LibraryType[];
    typesSuffix: string;
    typesBase: number;
  };
}

export interface SpotMediaFile {
  mediaId: number;
  fileName: string;
  mediaType: string;
  fileSize: string;
}

export interface SpotWidget {
  widgetId: number;
  type: string;
  playlistId: number;
  name: string;
  regionSpecific: number;
  moduleIcon: string;
  viewable: boolean;
  editable: boolean;
  deletable: boolean;
  mediaIds: number[];
  mediaFiles: SpotMediaFile[];
}

export interface PlaylistSpotsResponse {
  playlist: {
    playlistId: number;
    name: string;
    isDynamic: number;
    widgets: SpotWidget[];
  };
  spotsFound: number;
}

export interface StatusDashboardData {
  librarySize: string;
  countUsers: number;
  nowShowing: number;
  displayStatus: string;
  displayMediaStatus: string;
  bandwidthWidget: string;
  libraryWidgetLabels: string;
  libraryWidgetData: string;
  libraryLimitSet: boolean;
  libraryLimit: string;
  librarySuffix: string;
  xmdsLimitSet: boolean;
  bandwidthSuffix: string;
  latestNews: LatestNewsItem[];
}
