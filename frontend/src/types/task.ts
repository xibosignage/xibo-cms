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

export interface Task {
  taskId: number;
  name: string;
  configFile: string;
  class: string;
  status: number;
  pid: number;
  options: Record<string, string>;
  schedule: string;
  isActive: number;
  runNow: number;
  lastRunDt: number;
  lastRunStartDt: number;
  lastRunMessage: string;
  lastRunStatus: number;
  lastRunDuration: number;
  lastRunExitCode: number;
  nextRunDt: number;
  isConfigLocked: boolean;
}

export interface TaskAvailable {
  name: string;
  class: string;
  options: Record<string, string>;
  file: string;
}
