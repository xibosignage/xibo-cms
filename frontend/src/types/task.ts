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
