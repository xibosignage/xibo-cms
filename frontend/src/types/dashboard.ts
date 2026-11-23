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
