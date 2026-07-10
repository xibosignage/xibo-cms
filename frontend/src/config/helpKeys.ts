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

// Keys mirror the legacy Slim route names so an existing help-links.yaml keeps
// working. Longest matching prefix wins (dynamic/editor routes fall back to
// their list page's key).
const HELP_KEY_BY_PATH: Record<string, string> = {
  '/welcome': 'welcome.view',
  '/dashboard': 'statusdashboard.view',
  '/schedule/events': 'schedule.view',
  '/schedule/dayparting': 'daypart.view',
  '/design/campaign': 'campaign.view',
  '/design/layout': 'layout.view',
  '/design/templates': 'template.view',
  '/design/resolutions': 'resolution.view',
  '/library/playlists': 'playlist.view',
  '/library/media': 'library.view',
  '/library/datasets': 'dataset.view',
  '/library/menu-boards': 'menuBoard.view',
  '/displays/displays': 'display.view',
  '/displays/display-groups': 'displaygroup.view',
  '/displays/sync-groups': 'syncgroup.view',
  '/displays/settings': 'displayprofile.view',
  '/displays/player-versions': 'playersoftware.view',
  '/displays/commands': 'command.view',
  '/administration/users': 'user.view',
  '/administration/user-groups': 'group.view',
  '/administration/settings': 'admin.view',
  '/administration/applications': 'application.view',
  '/administration/connectors': 'connector.view',
  '/administration/modules': 'module.view',
  '/administration/transitions': 'transition.view',
  '/administration/tasks': 'task.view',
  '/administration/tags': 'tag.view',
  '/administration/folders': 'folders.view',
  '/administration/fonts': 'font.view',
  '/reporting/all-reports': 'report.view',
  '/reporting/report-schedules': 'reportschedule.view',
  '/reporting/saved-reports': 'savedreport.view',
  '/advanced/log': 'log.view',
  '/advanced/sessions': 'sessions.view',
  '/advanced/audit-trail': 'auditlog.view',
  '/developer/template': 'developer.view',
};

export function getHelpKeyForPath(pathname: string): string | undefined {
  const path = pathname.replace(/\/+$/, '') || '/';

  if (HELP_KEY_BY_PATH[path]) {
    return HELP_KEY_BY_PATH[path];
  }

  const match = Object.keys(HELP_KEY_BY_PATH)
    .filter((base) => path === base || path.startsWith(`${base}/`))
    .sort((a, b) => b.length - a.length)[0];

  return match ? HELP_KEY_BY_PATH[match] : undefined;
}
