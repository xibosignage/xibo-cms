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

import {
  DndContext,
  DragOverlay,
  PointerSensor,
  closestCenter,
  useSensor,
  useSensors,
  type DragEndEvent,
  type DragStartEvent,
} from '@dnd-kit/core';
import {
  SortableContext,
  arrayMove,
  horizontalListSortingStrategy,
  useSortable,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import {
  FloatingPortal,
  autoUpdate,
  flip,
  offset,
  shift,
  useDismiss,
  useFloating,
  useHover,
  useInteractions,
} from '@floating-ui/react';
import { CircleMinus, GripVertical, Info, Plus, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

import type { SelectOption } from '@/components/ui/forms/SelectDropdown';
import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import Switch from '@/components/ui/forms/Switch';
import TextInput from '@/components/ui/forms/TextInput';
import type { ModuleTemplateProperty } from '@/types/moduleTemplates';

const CONTROL_TYPES: SelectOption[] = [
  'text',
  'checkbox',
  'number',
  'textArea',
  'dropdown',
  'date',
  'code',
  'color',
  'custom',
  'divider',
  'effectSelector',
  'fontSelector',
  'header',
  'hidden',
  'message',
  'richText',
  'snippet',
  'canvasWidgetsSelector',
  'commandBuilder',
  'commandSelector',
  'connectorProperties',
  'datasetColStyle',
  'datasetColStyleSelector',
  'datasetColumnSelector',
  'datasetField',
  'datasetFilter',
  'datasetOrder',
  'datasetSelector',
  'forecastUnitsSelector',
  'languageSelector',
  'mediaSelector',
  'menuBoardCategorySelector',
  'menuBoardSelector',
  'playlistMixer',
  'tickerTagSelector',
  'tickerTagStyle',
  'worldClock',
].map((t) => ({ label: t, value: t }));

// ── Shared section label ──────────────────────────────────────────────────────

function SectionLabel({ label, action }: { label: string; action?: React.ReactNode }) {
  return (
    <div className="flex items-center justify-between">
      <span className="text-sm font-semibold text-gray-500 tracking-wide gap-1.5 inline-flex">
        {label}
        {action}
      </span>
    </div>
  );
}

// ── Shared info tooltip ───────────────────────────────────────────────────────

function InfoTooltip({ text }: { text: string }) {
  const { t } = useTranslation();
  const [isOpen, setIsOpen] = useState(false);

  const { refs, floatingStyles, context } = useFloating({
    open: isOpen,
    onOpenChange: setIsOpen,
    placement: 'right-start',
    whileElementsMounted: autoUpdate,
    middleware: [offset(8), flip(), shift({ padding: 8 })],
  });

  const hover = useHover(context, { delay: { open: 0, close: 300 } });
  const dismiss = useDismiss(context);
  const { getReferenceProps, getFloatingProps } = useInteractions([hover, dismiss]);

  return (
    <>
      <button
        ref={refs.setReference}
        {...getReferenceProps()}
        type="button"
        className="inline-flex items-center text-gray-400 hover:text-xibo-blue-500 transition-colors"
        aria-label={t('More info')}
      >
        <Info size={14} />
      </button>
      <FloatingPortal>
        {isOpen && (
          <div
            ref={refs.setFloating}
            style={floatingStyles}
            {...getFloatingProps()}
            className="z-9999 bg-white shadow-xl rounded-lg border border-gray-100 max-w-xs p-3 text-xs text-gray-600"
          >
            {text}
          </div>
        )}
      </FloatingPortal>
    </>
  );
}

// ── Options ───────────────────────────────────────────────────────────────────

function OptionsSubControl({
  options,
  onChange,
}: {
  options: Array<{ title: string; name: string }>;
  onChange: (opts: Array<{ title: string; name: string }>) => void;
}) {
  const { t } = useTranslation();

  const add = () => onChange([...options, { title: '', name: '' }]);
  const update = (idx: number, key: 'title' | 'name', v: string) =>
    onChange(options.map((o, i) => (i === idx ? { ...o, [key]: v } : o)));
  const remove = (idx: number) => onChange(options.filter((_, i) => i !== idx));

  return (
    <div className="flex flex-col gap-2 border border-gray-200 rounded-md bg-gray-50 p-2">
      <SectionLabel
        label={t('Options')}
        action={<InfoTooltip text={t('Options for the dropdown control')} />}
      />

      {options.length > 0 && (
        <div className="flex flex-col gap-1.5">
          {/* Column headers */}
          <div className="flex items-center gap-2 px-1">
            <span className="flex-1 text-xs font-medium text-gray-400">{t('Title')}</span>
            <span className="flex-1 text-xs font-medium text-gray-400">{t('Name')}</span>
            <span className="w-5" />
          </div>
          {options.map((opt, idx) => (
            <div key={idx} className="flex items-center gap-2">
              <div className="flex-1 min-w-0">
                <TextInput
                  name={`opt-title-${idx}`}
                  value={opt.title}
                  onChange={(v) => update(idx, 'title', v)}
                />
              </div>
              <div className="flex-1 min-w-0">
                <TextInput
                  name={`opt-name-${idx}`}
                  value={opt.name}
                  onChange={(v) => update(idx, 'name', v)}
                />
              </div>
              <button
                type="button"
                onClick={() => remove(idx)}
                className="shrink-0 p-0.5 text-red-400 hover:text-red-600"
                title={t('Delete option')}
              >
                <CircleMinus size={16} />
              </button>
            </div>
          ))}
        </div>
      )}

      <button
        type="button"
        onClick={add}
        className="w-full flex items-center justify-center gap-1.5 p-2 text-xs font-semibold text-xibo-blue-700 bg-xibo-blue-100 hover:bg-xibo-blue-200 rounded-md transition-colors"
      >
        <Plus size={13} />
        {t('Add option')}
      </button>
    </div>
  );
}

// ── Visibility / Validation tests ─────────────────────────────────────────────

// Matches the condition type select in the previous developer-template-edit-page.twig
const CONDITION_TYPE_OPTIONS: SelectOption[] = [
  { label: 'ne', value: 'ne' },
  { label: 'eq', value: 'eq' },
  { label: 'neq', value: 'neq' },
  { label: 'gt', value: 'gt' },
  { label: 'lt', value: 'lt' },
  { label: 'egt', value: 'egt' },
  { label: 'elt', value: 'elt' },
  { label: 'isTopLevel', value: 'isTopLevel' },
];

type TestItem = {
  type: string;
  message: string;
  conditions: Array<{ field: string; type: string; value: string }>;
};

function TestsSubControl({
  sectionLabel,
  idPrefix,
  tests,
  onAdd,
  onRemoveTest,
  onUpdateTest,
  onAddCondition,
  onRemoveCondition,
  onUpdateCondition,
}: {
  sectionLabel?: string;
  idPrefix: string;
  tests: TestItem[];
  onAdd: () => void;
  onRemoveTest: (ti: number) => void;
  onUpdateTest: (ti: number, key: 'type' | 'message', value: string) => void;
  onAddCondition: (ti: number) => void;
  onRemoveCondition: (ti: number, ci: number) => void;
  onUpdateCondition: (
    ti: number,
    ci: number,
    key: 'field' | 'type' | 'value',
    value: string,
  ) => void;
}) {
  const { t } = useTranslation();

  const testTypeOptions: SelectOption[] = [
    { label: t('and'), value: 'and' },
    { label: t('or'), value: 'or' },
  ];

  const testItems = (
    <>
      {tests.map((test, ti) => (
        <div
          key={ti}
          className="flex flex-col gap-2 border border-gray-200 rounded-md bg-white p-2"
        >
          {/* Test header */}
          <div className="flex items-center justify-between">
            <SectionLabel label={t('Test')} />
            <button
              type="button"
              onClick={() => onRemoveTest(ti)}
              className="p-0.5 text-red-400 hover:text-red-600"
              title={t('Delete test')}
            >
              <CircleMinus size={15} />
            </button>
          </div>

          {/* Type + Message side by side */}
          <div className="flex items-center gap-2">
            <div className="flex-1 min-w-0">
              <SelectDropdown
                label={t('Type')}
                value={test.type}
                options={testTypeOptions}
                onSelect={(v) => onUpdateTest(ti, 'type', v)}
              />
            </div>
            <div className="flex-1 min-w-0">
              <TextInput
                name={`${idPrefix}-message-${ti}`}
                label={t('Message')}
                value={test.message}
                onChange={(v) => onUpdateTest(ti, 'message', v)}
              />
            </div>
          </div>

          {/* Conditions */}
          {test.conditions.length > 0 && (
            <div className="flex flex-col gap-1.5 pt-1 border-t border-gray-100">
              {test.conditions.map((cond, ci) => (
                <div
                  key={ci}
                  className="flex flex-col gap-1.5 border border-gray-100 rounded bg-gray-50 p-2"
                >
                  <div className="flex items-center justify-between mb-2">
                    <SectionLabel label={t('Condition')} />
                    <button
                      type="button"
                      onClick={() => onRemoveCondition(ti, ci)}
                      className="p-0.5 text-red-400 hover:text-red-600"
                      title={t('Delete condition')}
                    >
                      <CircleMinus size={16} />
                    </button>
                  </div>
                  <SelectDropdown
                    label={t('Type')}
                    value={cond.type}
                    options={CONDITION_TYPE_OPTIONS}
                    onSelect={(v) => onUpdateCondition(ti, ci, 'type', v)}
                  />
                  <TextInput
                    name={`${idPrefix}-cond-field-${ti}-${ci}`}
                    label={t('Field')}
                    value={cond.field}
                    onChange={(v) => onUpdateCondition(ti, ci, 'field', v)}
                  />
                  <TextInput
                    name={`${idPrefix}-cond-value-${ti}-${ci}`}
                    label={t('Value')}
                    value={cond.value}
                    onChange={(v) => onUpdateCondition(ti, ci, 'value', v)}
                  />
                </div>
              ))}
            </div>
          )}

          <button
            type="button"
            onClick={() => onAddCondition(ti)}
            className="w-full flex items-center justify-center gap-1.5 p-2 text-xs font-semibold text-xibo-blue-500 bg-xibo-seasalt hover:bg-xibo-blue-50 rounded-md transition-colors"
          >
            <Plus size={13} />
            {t('Add condition')}
          </button>
        </div>
      ))}
      <button
        type="button"
        onClick={onAdd}
        className="w-full flex items-center justify-center gap-1.5 p-2 text-xs font-semibold text-xibo-blue-700 bg-xibo-blue-100 hover:bg-xibo-blue-200 rounded-md transition-colors"
      >
        <Plus size={13} />
        {t('Add test')}
      </button>
    </>
  );

  if (sectionLabel) {
    return (
      <div className="flex flex-col gap-2 border border-gray-200 rounded-md bg-gray-50 p-2">
        <SectionLabel label={sectionLabel} />
        {testItems}
      </div>
    );
  }

  return <div className="flex flex-col gap-2">{testItems}</div>;
}

type VisibilityTest = TestItem;

function VisibilitySubControl({
  visibility,
  onChange,
}: {
  visibility: VisibilityTest[];
  onChange: (v: VisibilityTest[]) => void;
}) {
  const { t } = useTranslation();
  const update = (fn: (prev: VisibilityTest[]) => VisibilityTest[]) => onChange(fn(visibility));

  return (
    <TestsSubControl
      sectionLabel={t('Visibility')}
      idPrefix="vis"
      tests={visibility}
      onAdd={() =>
        update((p) => [
          ...p,
          { type: 'or', message: '', conditions: [{ field: '', type: 'eq', value: '' }] },
        ])
      }
      onRemoveTest={(ti) => update((p) => p.filter((_, i) => i !== ti))}
      onUpdateTest={(ti, key, v) =>
        update((p) => p.map((t, i) => (i === ti ? { ...t, [key]: v } : t)))
      }
      onAddCondition={(ti) =>
        update((p) =>
          p.map((t, i) =>
            i === ti
              ? { ...t, conditions: [...t.conditions, { field: '', type: 'eq', value: '' }] }
              : t,
          ),
        )
      }
      onRemoveCondition={(ti, ci) =>
        update((p) =>
          p.map((t, i) =>
            i === ti ? { ...t, conditions: t.conditions.filter((_, j) => j !== ci) } : t,
          ),
        )
      }
      onUpdateCondition={(ti, ci, key, v) =>
        update((p) =>
          p.map((t, i) =>
            i === ti
              ? {
                  ...t,
                  conditions: t.conditions.map((c, j) => (j === ci ? { ...c, [key]: v } : c)),
                }
              : t,
          ),
        )
      }
    />
  );
}

// ── Validation ────────────────────────────────────────────────────────────────

type ValidationTest = TestItem;
type ValidationValue = { onSave?: boolean; onStatus?: boolean; tests?: ValidationTest[] };

function ValidationSubControl({
  validation,
  onChange,
}: {
  validation: ValidationValue;
  onChange: (v: ValidationValue) => void;
}) {
  const { t } = useTranslation();

  const updateTests = (fn: (prev: ValidationTest[]) => ValidationTest[]) =>
    onChange({ ...validation, tests: fn(validation.tests ?? []) });

  const hasTests = (validation.tests ?? []).length > 0;

  return (
    <div className="flex flex-col gap-2 border border-gray-200 rounded-md bg-gray-50 p-2">
      <SectionLabel label={t('Validation')} />

      {hasTests && (
        <div className="flex gap-2">
          <label className="flex flex-1 items-center gap-2 cursor-pointer select-none bg-white border border-gray-200 rounded-md px-3 py-2.5">
            <input
              type="checkbox"
              className="w-4 h-4 rounded accent-xibo-blue-600 cursor-pointer"
              checked={!!validation.onSave}
              onChange={(e) => onChange({ ...validation, onSave: e.target.checked })}
            />
            <span className="text-sm font-medium text-gray-700">{t('On Save')}</span>
          </label>
          <label className="flex flex-1 items-center gap-2 cursor-pointer select-none bg-white border border-gray-200 rounded-md px-3 py-2.5">
            <input
              type="checkbox"
              className="w-4 h-4 rounded accent-xibo-blue-600 cursor-pointer"
              checked={!!validation.onStatus}
              onChange={(e) => onChange({ ...validation, onStatus: e.target.checked })}
            />
            <span className="text-sm font-medium text-gray-700">{t('On Status')}</span>
          </label>
        </div>
      )}

      <TestsSubControl
        idPrefix="val"
        tests={validation.tests ?? []}
        onAdd={() =>
          updateTests((p) => [
            ...p,
            { type: 'or', message: '', conditions: [{ field: '', type: 'eq', value: '' }] },
          ])
        }
        onRemoveTest={(ti) => updateTests((p) => p.filter((_, i) => i !== ti))}
        onUpdateTest={(ti, key, v) =>
          updateTests((p) => p.map((item, i) => (i === ti ? { ...item, [key]: v } : item)))
        }
        onAddCondition={(ti) =>
          updateTests((p) =>
            p.map((item, i) =>
              i === ti
                ? {
                    ...item,
                    conditions: [...item.conditions, { field: '', type: 'eq', value: '' }],
                  }
                : item,
            ),
          )
        }
        onRemoveCondition={(ti, ci) =>
          updateTests((p) =>
            p.map((item, i) =>
              i === ti ? { ...item, conditions: item.conditions.filter((_, j) => j !== ci) } : item,
            ),
          )
        }
        onUpdateCondition={(ti, ci, key, v) =>
          updateTests((p) =>
            p.map((item, i) =>
              i === ti
                ? {
                    ...item,
                    conditions: item.conditions.map((c, j) => (j === ci ? { ...c, [key]: v } : c)),
                  }
                : item,
            ),
          )
        }
      />
    </div>
  );
}

// ── Player Compatibility ──────────────────────────────────────────────────────

type PlayerCompatibilityValue = {
  windows?: string;
  android?: string;
  linux?: string;
  webos?: string;
  tizen?: string;
};

const PLAYER_PLATFORMS: Array<{ key: keyof PlayerCompatibilityValue; label: string }> = [
  { key: 'windows', label: 'Windows' },
  { key: 'android', label: 'Android' },
  { key: 'linux', label: 'Linux' },
  { key: 'webos', label: 'WebOS' },
  { key: 'tizen', label: 'Tizen' },
];

function PlayerCompatibilitySubControl({
  playerCompatibility,
  onChange,
}: {
  playerCompatibility: PlayerCompatibilityValue;
  onChange: (v: PlayerCompatibilityValue) => void;
}) {
  const { t } = useTranslation();
  const platformLabels: Record<keyof PlayerCompatibilityValue, string> = {
    windows: t('Windows'),
    android: t('Android'),
    linux: t('Linux'),
    webos: t('WebOS'),
    tizen: t('Tizen'),
  };

  return (
    <div className="flex flex-col gap-2 border border-gray-200 rounded-md bg-gray-50 p-3">
      <SectionLabel label={t('Player Compatibility')} />
      <div className="flex flex-col gap-3 pt-1">
        {PLAYER_PLATFORMS.map(({ key, label }) => (
          <TextInput
            key={key}
            name={`compat-${key}`}
            label={platformLabels[key] ?? label}
            value={playerCompatibility[key] ?? ''}
            onChange={(v) => onChange({ ...playerCompatibility, [key]: v || undefined })}
          />
        ))}
      </div>
    </div>
  );
}

// ── Property card ─────────────────────────────────────────────────────────────

interface PropertyCardProps {
  property: ModuleTemplateProperty;
  index: number;
  onChange: (index: number, updated: ModuleTemplateProperty) => void;
  onDelete: (index: number) => void;
  /** Rendered as the drag handle in the card header. */
  dragHandleNode?: React.ReactNode;
  /** Visual-only overlay clone while dragging. */
  isOverlay?: boolean;
  /** Briefly highlights the card right after it's added. */
  isNew?: boolean;
}

function PropertyCard({
  property,
  index,
  onChange,
  onDelete,
  dragHandleNode,
  isOverlay,
  isNew,
}: PropertyCardProps) {
  const { t } = useTranslation();

  const update = (key: keyof ModuleTemplateProperty, value: unknown) => {
    onChange(index, { ...property, [key]: value });
  };

  return (
    <div
      className={`w-[360px] shrink-0 border rounded-lg bg-white flex flex-col transition-[box-shadow,border-color] duration-1000 ${
        isOverlay
          ? 'border-xibo-blue-400 shadow-xl rotate-1'
          : isNew
            ? 'border-xibo-blue-400 ring-2 ring-xibo-blue-300'
            : 'border-gray-200'
      }`}
    >
      {/* Sticky header */}
      <div className="flex items-center justify-between px-4 py-2.5 border-b border-gray-100 shrink-0">
        <div className="flex items-center gap-2">{dragHandleNode}</div>
        <button
          type="button"
          onClick={() => onDelete(index)}
          className="p-1 text-red-400 hover:text-red-600"
          title={t('Delete')}
        >
          <Trash2 size={17} />
        </button>
      </div>

      <div className="p-4 flex flex-col gap-4">
        {/* Core fields */}
        <div className="flex flex-col gap-3">
          <SelectDropdown
            label={t('Type')}
            value={property.type ?? ''}
            options={CONTROL_TYPES}
            placeholder={t('Select type...')}
            onSelect={(v: string) => update('type', v)}
          />
          <TextInput
            name={`id-${index}`}
            label={t('ID')}
            value={property.id ?? ''}
            onChange={(v) => update('id', v)}
          />
          <TextInput
            name={`title-${index}`}
            label={t('Title')}
            value={property.title ?? ''}
            onChange={(v) => update('title', v)}
          />
          <TextInput
            name={`helpText-${index}`}
            label={t('Help Text')}
            value={property.helpText ?? ''}
            onChange={(v) => update('helpText', v)}
          />
          <TextInput
            name={`customPopOver-${index}`}
            label={t('Custom Pop-Over')}
            value={property.customPopOver ?? ''}
            onChange={(v) => update('customPopOver', v)}
          />
          <TextInput
            name={`default-${index}`}
            label={t('Default')}
            labelExtra={<InfoTooltip text={t('Default value')} />}
            value={property.default ?? ''}
            onChange={(v) => update('default', v)}
          />
          <TextInput
            name={`variant-${index}`}
            label={t('Variant')}
            value={property.variant ?? ''}
            onChange={(v) => update('variant', v)}
          />
          <TextInput
            name={`format-${index}`}
            label={t('Format')}
            value={property.format ?? ''}
            onChange={(v) => update('format', v)}
          />
          <TextInput
            name={`mode-${index}`}
            label={t('Mode')}
            value={property.mode ?? ''}
            onChange={(v) => update('mode', v)}
          />
          <TextInput
            name={`target-${index}`}
            label={t('Target')}
            value={property.target ?? ''}
            onChange={(v) => update('target', v)}
          />
          <TextInput
            name={`propertyGroupId-${index}`}
            label={t('Property Group ID')}
            value={property.propertyGroupId ?? ''}
            onChange={(v) => update('propertyGroupId', v)}
          />
          <TextInput
            name={`dependsOn-${index}`}
            label={t('Depends On')}
            value={property.dependsOn ?? ''}
            onChange={(v) => update('dependsOn', v)}
          />
        </div>

        {/* Boolean flags — inline label + switch */}
        <div className="border-t border-gray-100 pt-3 flex flex-col gap-2">
          {(
            [
              ['allowLibraryRefs', t('Allow Library Refs')],
              ['allowAssetRefs', t('Allow Asset Refs')],
              ['parseTranslations', t('Parse Translations')],
              ['includeInXlf', t('Include In XLF')],
            ] as [keyof ModuleTemplateProperty, string][]
          ).map(([key, label]) => (
            <div
              key={key}
              className="flex items-center justify-between rounded-lg border border-gray-200 p-2"
            >
              <span className="text-sm font-medium text-gray-700">{label}</span>
              <div className="w-fit shrink-0">
                <Switch
                  size="sm"
                  hideOnOff
                  checked={!!(property[key] as boolean | undefined)}
                  onChange={(v) => update(key, v)}
                />
              </div>
            </div>
          ))}
        </div>

        {/* Options */}
        <div className="border-t border-gray-100 pt-3">
          <OptionsSubControl
            options={property.options ?? []}
            onChange={(opts) => update('options', opts)}
          />
        </div>

        {/* Visibility */}
        <div className="border-t border-gray-100 pt-3">
          <VisibilitySubControl
            visibility={property.visibility ?? []}
            onChange={(v) => update('visibility', v)}
          />
        </div>

        {/* Validation */}
        <div className="border-t border-gray-100 pt-3">
          <ValidationSubControl
            validation={property.validation ?? {}}
            onChange={(v) => update('validation', v)}
          />
        </div>

        {/* Player Compatibility */}
        <div className="border-t border-gray-100 pt-3">
          <PlayerCompatibilitySubControl
            playerCompatibility={property.playerCompatibility ?? {}}
            onChange={(v) => update('playerCompatibility', v)}
          />
        </div>
      </div>
    </div>
  );
}

// ── Sortable wrapper ──────────────────────────────────────────────────────────

function SortablePropertyCard(
  props: Omit<PropertyCardProps, 'dragHandleNode' | 'isOverlay'> & { sortId: string },
) {
  const { t } = useTranslation();
  const { sortId, ...rest } = props;
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
    id: sortId,
  });

  return (
    <div
      ref={setNodeRef}
      style={{
        transform: CSS.Transform.toString(transform),
        transition,
        opacity: isDragging ? 0.3 : 1,
      }}
    >
      <PropertyCard
        {...rest}
        dragHandleNode={
          <div
            className="cursor-grab active:cursor-grabbing text-gray-300 hover:text-gray-500 touch-none"
            title={t('Move')}
            {...listeners}
            {...attributes}
          >
            <GripVertical size={16} />
          </div>
        }
      />
    </div>
  );
}

// ── PropertiesTab ─────────────────────────────────────────────────────────────

interface PropertiesTabProps {
  properties: ModuleTemplateProperty[];
  onChange: (properties: ModuleTemplateProperty[]) => void;
}

export default function PropertiesTab({ properties, onChange }: PropertiesTabProps) {
  const { t } = useTranslation();

  const [sortIds, setSortIds] = useState<string[]>(() => properties.map((_, i) => `prop-${i}`));
  const counterRef = useRef(properties.length);
  const [activeId, setActiveId] = useState<string | null>(null);
  const lastSetPropertiesRef = useRef<ModuleTemplateProperty[]>(properties);
  const scrollContainerRef = useRef<HTMLDivElement>(null);
  const [justAddedId, setJustAddedId] = useState<string | null>(null);

  // Re-sync sortIds when properties changes externally (e.g. form reset via Cancel).
  // Uses referential equality: if the incoming prop is not the array we last passed
  // to onChange, the change came from outside this component.
  useEffect(() => {
    if (properties === lastSetPropertiesRef.current) return;
    setSortIds(properties.map(() => `prop-${counterRef.current++}`));
    lastSetPropertiesRef.current = properties;
  }, [properties]);

  const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 5 } }));

  const callOnChange = (newProperties: ModuleTemplateProperty[]) => {
    lastSetPropertiesRef.current = newProperties;
    onChange(newProperties);
  };

  const addProperty = () => {
    const newId = `prop-${counterRef.current++}`;
    setSortIds((prev) => [...prev, newId]);
    callOnChange([...properties, { type: 'text', id: '' }]);
    setJustAddedId(newId);
  };

  // Scroll the newly added card into view and briefly highlight it, since the
  // list scrolls horizontally and an appended card otherwise renders off-screen
  // with no visible sign anything happened.
  useEffect(() => {
    if (!justAddedId) return;
    scrollContainerRef.current?.scrollTo({
      left: scrollContainerRef.current.scrollWidth,
      behavior: 'smooth',
    });
    const timer = setTimeout(() => setJustAddedId(null), 1500);
    return () => clearTimeout(timer);
  }, [justAddedId]);

  const updateProperty = (index: number, updated: ModuleTemplateProperty) =>
    callOnChange(properties.map((p, i) => (i === index ? updated : p)));

  const deleteProperty = (index: number) => {
    setSortIds((prev) => prev.filter((_, i) => i !== index));
    callOnChange(properties.filter((_, i) => i !== index));
  };

  const handleDragStart = (event: DragStartEvent) => setActiveId(String(event.active.id));

  const handleDragEnd = (event: DragEndEvent) => {
    setActiveId(null);
    const { active, over } = event;
    if (!over || active.id === over.id) return;

    const oldIndex = sortIds.indexOf(String(active.id));
    const newIndex = sortIds.indexOf(String(over.id));
    if (oldIndex === -1 || newIndex === -1) return;

    setSortIds((prev) => arrayMove(prev, oldIndex, newIndex));
    callOnChange(arrayMove(properties, oldIndex, newIndex));
  };

  const activeIndex = activeId !== null ? sortIds.indexOf(activeId) : -1;

  return (
    <div className="flex flex-col gap-4">
      <div className="flex justify-end">
        <button
          type="button"
          onClick={addProperty}
          className="flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-xibo-blue-600 border border-xibo-blue-300 rounded-lg hover:bg-xibo-blue-50 transition-colors"
        >
          <Plus size={15} />
          {t('Add')}
        </button>
      </div>

      {properties.length === 0 ? (
        <div className="flex items-center justify-center py-12 text-sm text-gray-400 border border-dashed border-gray-200 rounded-lg">
          {t('No properties, click Add to create one!')}
        </div>
      ) : (
        <DndContext
          sensors={sensors}
          collisionDetection={closestCenter}
          onDragStart={handleDragStart}
          onDragEnd={handleDragEnd}
        >
          <SortableContext items={sortIds} strategy={horizontalListSortingStrategy}>
            <div
              ref={scrollContainerRef}
              className="flex flex-row items-start gap-4 overflow-x-auto overflow-y-auto max-h-[calc(100vh-300px)] pb-2"
            >
              {properties.map((prop, idx) => (
                <SortablePropertyCard
                  key={sortIds[idx]}
                  sortId={sortIds[idx]!}
                  property={prop}
                  index={idx}
                  onChange={updateProperty}
                  onDelete={deleteProperty}
                  isNew={sortIds[idx] === justAddedId}
                />
              ))}
            </div>
          </SortableContext>

          <DragOverlay>
            {activeId !== null && activeIndex !== -1 && (
              <PropertyCard
                property={properties[activeIndex]!}
                index={activeIndex}
                onChange={() => {}}
                onDelete={() => {}}
                isOverlay
              />
            )}
          </DragOverlay>
        </DndContext>
      )}
    </div>
  );
}
