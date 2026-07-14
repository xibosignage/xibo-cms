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

import { ChevronDown, ChevronRight } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import TextInput from '@/components/ui/forms/TextInput';
import CommandBuilder from '@/pages/Displays/Commands/components/CommandBuilder/CommandBuilder';
import type { DisplayProfileCommand } from '@/types/displayProfile';

type CommandOverrideMutableField = 'commandString' | 'validationString' | 'createAlertOn';

export interface CommandOverrideDraft {
  commandId: number;
  command: string;
  code: string;
  description: string | null;
  baseCommandString: string;
  baseValidationString: string;
  baseCreateAlertOn: string;
  commandString: string;
  validationString: string;
  createAlertOn: string;
}

export function buildCommandDrafts(commands: DisplayProfileCommand[]): {
  drafts: CommandOverrideDraft[];
  expandedIds: Set<number>;
} {
  const drafts = commands.map((cmd) => ({
    commandId: cmd.commandId,
    command: cmd.command,
    code: cmd.code ?? '',
    description: cmd.description ?? null,
    baseCommandString: cmd.commandString ?? '',
    baseValidationString: cmd.validationString ?? '',
    baseCreateAlertOn: cmd.createAlertOn ?? 'never',
    commandString: cmd.commandStringDisplayProfile ?? '',
    validationString: cmd.validationStringDisplayProfile ?? '',
    createAlertOn: cmd.createAlertOnDisplayProfile ?? '',
  }));
  const expandedIds = new Set(drafts.filter((c) => c.commandString).map((c) => c.commandId));
  return { drafts, expandedIds };
}

interface CommandsTabProps {
  commandDrafts: CommandOverrideDraft[];
  onCommandDraftsChange: (drafts: CommandOverrideDraft[]) => void;
  initialExpandedIds?: Set<number>;
}

export default function CommandsTab({
  commandDrafts,
  onCommandDraftsChange,
  initialExpandedIds,
}: CommandsTabProps) {
  const { t } = useTranslation();
  const [expandedCommands, setExpandedCommands] = useState<Set<number>>(
    () => initialExpandedIds ?? new Set(),
  );

  const toggleExpanded = (commandId: number) => {
    setExpandedCommands((prev) => {
      const next = new Set(prev);
      if (next.has(commandId)) {
        next.delete(commandId);
      } else {
        next.add(commandId);
      }
      return next;
    });
  };

  const updateDraft = (commandId: number, field: CommandOverrideMutableField, value: string) => {
    const updated = commandDrafts.map((c) => {
      if (c.commandId !== commandId) return c;
      const draft = { ...c, [field]: value };
      // Reset dependent fields when the command string is cleared
      if (field === 'commandString' && !value) {
        draft.validationString = '';
        draft.createAlertOn = '';
      }
      return draft;
    });
    onCommandDraftsChange(updated);
  };

  return (
    <div className="space-y-2">
      {commandDrafts.map((cmd) => {
        const isExpanded = expandedCommands.has(cmd.commandId);
        const hasOverride = !!cmd.commandString;
        return (
          <div key={cmd.commandId} className="rounded-lg border overflow-hidden border-gray-200">
            <button
              type="button"
              className="flex w-full items-center gap-2 px-4 py-3 text-left hover:bg-gray-50 transition-colors"
              aria-expanded={isExpanded}
              onClick={() => toggleExpanded(cmd.commandId)}
            >
              {isExpanded ? (
                <ChevronDown size={16} className="shrink-0 text-gray-500" />
              ) : (
                <ChevronRight size={16} className="shrink-0 text-gray-500" />
              )}
              <span className="font-medium">{cmd.command}</span>
              <span className="text-sm text-gray-500">({cmd.code})</span>
              {hasOverride && (
                <span className="ml-auto rounded-full bg-blue-100 px-2 py-0.5 text-xs text-blue-700">
                  {t('Overridden')}
                </span>
              )}
            </button>
            {isExpanded && (
              <div className="border-t border-gray-200 px-4 py-3 space-y-3">
                {cmd.description && <p className="text-sm text-gray-400">{cmd.description}</p>}
                <CommandBuilder
                  value={cmd.commandString}
                  onChange={(val) => updateDraft(cmd.commandId, 'commandString', val)}
                />
                <TextInput
                  name={`validationString_${cmd.commandId}`}
                  label={t('Validation String')}
                  helpText={t('A regular expression to validate the output of the command.')}
                  placeholder={cmd.baseValidationString || undefined}
                  value={cmd.validationString}
                  onChange={(val) => updateDraft(cmd.commandId, 'validationString', val)}
                />
                <SelectDropdown
                  label={t('Create Alert On')}
                  value={cmd.createAlertOn || cmd.baseCreateAlertOn || 'never'}
                  options={[
                    { value: 'never', label: t('Never') },
                    { value: 'success', label: t('Success') },
                    { value: 'failure', label: t('Failure') },
                    { value: 'always', label: t('Always') },
                  ]}
                  onSelect={(val) => updateDraft(cmd.commandId, 'createAlertOn', val || 'never')}
                />
              </div>
            )}
          </div>
        );
      })}
    </div>
  );
}
