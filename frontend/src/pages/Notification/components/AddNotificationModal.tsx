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

import { useQuery } from '@tanstack/react-query';
import { Extension } from '@tiptap/core';
import Color from '@tiptap/extension-color';
import FontFamily from '@tiptap/extension-font-family';
import Highlight from '@tiptap/extension-highlight';
import Placeholder from '@tiptap/extension-placeholder';
import Subscript from '@tiptap/extension-subscript';
import Superscript from '@tiptap/extension-superscript';
import { Table } from '@tiptap/extension-table';
import { TableCell } from '@tiptap/extension-table-cell';
import { TableHeader } from '@tiptap/extension-table-header';
import { TableRow } from '@tiptap/extension-table-row';
import TextAlign from '@tiptap/extension-text-align';
import { TextStyle } from '@tiptap/extension-text-style';
import Underline from '@tiptap/extension-underline';
import { EditorContent, useEditor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import { isAxiosError } from 'axios';
import {
  AlignCenter,
  AlignLeft,
  AlignRight,
  Bold,
  Highlighter,
  Italic,
  List,
  ListOrdered,
  Minus,
  Omega,
  Palette,
  Paperclip,
  Quote,
  Strikethrough,
  Subscript as SubscriptIcon,
  Superscript as SuperscriptIcon,
  Table as TableIcon,
  Underline as UnderlineIcon,
  X,
} from 'lucide-react';
import { DateTime } from 'luxon';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { twMerge } from 'tailwind-merge';

import Checkbox from '@/components/ui/forms/Checkbox';
import DatePickerInput from '@/components/ui/forms/DatePickerInput';
import MultiSelectDropdown from '@/components/ui/forms/MultiSelectDropdown';
import TagInput from '@/components/ui/forms/TagInput';
import TextInput from '@/components/ui/forms/TextInput';
import Modal from '@/components/ui/modals/Modal';
import { useUserContext } from '@/context/UserContext';
import { fetchDisplayGroups } from '@/services/displayGroupApi';
import {
  createNotification,
  fetchNotificationById,
  updateNotification,
  uploadNotificationAttachment,
} from '@/services/notificationApi';
import { fetchUsers } from '@/services/userApi';
import { fetchUserGroups } from '@/services/userGroupApi';
import type { Tag } from '@/types/tag';

interface AddNotificationModalProps {
  isOpen?: boolean;
  onClose: () => void;
  onSave: () => void;
  editNotificationId?: number;
}

interface NotificationDraft {
  subject: string;
  releaseDt: string;
  isInterrupt: boolean;
  recipientIds: string[];
  nonuserTags: Tag[];
  displayRecipientIds: string[];
}

const DEFAULT_DRAFT: NotificationDraft = {
  subject: '',
  releaseDt: '',
  isInterrupt: false,
  recipientIds: [],
  nonuserTags: [],
  displayRecipientIds: [],
};

type DraftErrors = Partial<Record<keyof NotificationDraft | 'body', string>>;

const FontSize = Extension.create({
  name: 'fontSize',
  addGlobalAttributes() {
    return [
      {
        types: ['textStyle'],
        attributes: {
          fontSize: {
            default: null,
            parseHTML: (el: HTMLElement) => el.style.fontSize || null,
            renderHTML: (attrs: Record<string, string | null>) =>
              attrs.fontSize ? { style: `font-size: ${attrs.fontSize}` } : {},
          },
        },
      },
    ];
  },
});

const EDITOR_EXTENSIONS = [
  StarterKit.configure({ underline: false }),
  Underline,
  TextStyle,
  Color,
  FontFamily,
  FontSize,
  Highlight.configure({ multicolor: true }),
  Subscript,
  Superscript,
  TextAlign.configure({ types: ['heading', 'paragraph'] }),
  Table.configure({ resizable: false }),
  TableRow,
  TableHeader,
  TableCell,
];

const SPECIAL_CHARS = [
  '©',
  '®',
  '™',
  '€',
  '£',
  '¥',
  '¢',
  '°',
  '±',
  '×',
  '÷',
  '≠',
  '≤',
  '≥',
  '∞',
  '½',
  '¼',
  '¾',
  '→',
  '←',
  '↑',
  '↓',
  '↔',
  '•',
  '…',
  '§',
  '¶',
  '†',
  '‡',
  'α',
  'β',
  'γ',
  'δ',
  'π',
  'Σ',
  'Ω',
  'μ',
  'ñ',
  'ü',
  'ö',
  'ä',
  'é',
  'è',
  'ê',
  'à',
  'â',
  'ç',
  'ï',
  'î',
];

function SectionHeader({
  children,
  labelExtra,
}: {
  children: React.ReactNode;
  labelExtra?: React.ReactNode;
}) {
  return (
    <div className="flex items-center gap-0.5 mb-4">
      <span className="text-sm font-semibold text-gray-700 whitespace-nowrap">{children}</span>
      {labelExtra}
    </div>
  );
}

function ToolbarSep() {
  return <span className="w-px h-4 bg-gray-200 mx-1 shrink-0" />;
}

function TiptapToolbar({ editor }: { editor: ReturnType<typeof useEditor> }) {
  const { t } = useTranslation();
  const [showSpecialChars, setShowSpecialChars] = useState(false);

  if (!editor) return null;

  const btn = (active: boolean) =>
    twMerge(
      'p-1.5 rounded transition-colors cursor-pointer shrink-0',
      active
        ? 'bg-xibo-blue-100 text-xibo-blue-600'
        : 'text-gray-500 hover:bg-gray-100 hover:text-gray-700',
    );

  const selectClass =
    'text-xs text-gray-600 bg-white border border-gray-200 rounded px-1.5 py-1 cursor-pointer focus:outline-none focus:border-xibo-blue-400 h-7 shrink-0';

  const currentHeadingLevel = editor.isActive('heading', { level: 1 })
    ? '1'
    : editor.isActive('heading', { level: 2 })
      ? '2'
      : editor.isActive('heading', { level: 3 })
        ? '3'
        : '0';

  const textColor: string =
    (editor.getAttributes('textStyle').color as string | undefined) ?? '#000000';
  const highlightColor: string =
    (editor.getAttributes('highlight').color as string | undefined) ?? '#ffff00';

  const currentFontFamily: string =
    (editor.getAttributes('textStyle').fontFamily as string | undefined) ?? '';
  const currentFontSize: string =
    (editor.getAttributes('textStyle') as Record<string, string>).fontSize ?? '';

  return (
    <div className="relative flex flex-wrap items-center gap-0.5 px-2 py-1.5 border-b border-gray-200 bg-gray-50 rounded-t-lg">
      {/* Special chars popover */}
      {showSpecialChars && (
        <div className="absolute bottom-full left-0 mb-1 z-50 flex flex-wrap max-w-50 bg-white border border-gray-200 rounded shadow p-1">
          {SPECIAL_CHARS.map((char) => (
            <button
              key={char}
              type="button"
              title={char}
              className="w-7 h-7 text-sm text-gray-700 hover:bg-gray-100 rounded cursor-pointer flex items-center justify-center"
              onClick={() => {
                editor.chain().focus().insertContent(char).run();
                setShowSpecialChars(false);
              }}
            >
              {char}
            </button>
          ))}
        </div>
      )}

      {/* Font Family */}
      <select
        value={currentFontFamily}
        onChange={(e) => {
          const val = e.target.value;
          if (val === '') {
            editor.chain().focus().unsetFontFamily().run();
          } else {
            editor.chain().focus().setFontFamily(val).run();
          }
        }}
        className={selectClass}
      >
        <option value="">{t('Default')}</option>
        <option value="Arial">Arial</option>
        <option value="Times New Roman">Times New Roman</option>
        <option value="Courier New">Courier New</option>
        <option value="Georgia">Georgia</option>
        <option value="Verdana">Verdana</option>
      </select>

      {/* Font Size */}
      <select
        value={currentFontSize}
        onChange={(e) => {
          const val = e.target.value;
          editor
            .chain()
            .focus()
            .setMark('textStyle', { fontSize: val || null })
            .run();
        }}
        className={selectClass}
      >
        <option value="">{t('Default')}</option>
        <option value="8px">8px</option>
        <option value="10px">10px</option>
        <option value="12px">12px</option>
        <option value="14px">14px</option>
        <option value="16px">16px</option>
        <option value="18px">18px</option>
        <option value="20px">20px</option>
        <option value="24px">24px</option>
        <option value="28px">28px</option>
        <option value="32px">32px</option>
        <option value="36px">36px</option>
        <option value="48px">48px</option>
        <option value="72px">72px</option>
      </select>

      <ToolbarSep />

      {/* Heading / Paragraph selector */}
      <select
        value={currentHeadingLevel}
        onChange={(e) => {
          const val = e.target.value;
          if (val === '0') {
            editor.chain().focus().setParagraph().run();
          } else {
            editor
              .chain()
              .focus()
              .toggleHeading({ level: Number(val) as 1 | 2 | 3 })
              .run();
          }
        }}
        className={twMerge(selectClass, 'min-w-25.5')}
      >
        <option value="0">{t('Paragraph')}</option>
        <option value="1">{t('Heading 1')}</option>
        <option value="2">{t('Heading 2')}</option>
        <option value="3">{t('Heading 3')}</option>
      </select>

      <ToolbarSep />

      {/* Text style */}
      <button
        type="button"
        title={t('Bold')}
        onClick={() => editor.chain().focus().toggleBold().run()}
        className={btn(editor.isActive('bold'))}
      >
        <Bold size={14} />
      </button>
      <button
        type="button"
        title={t('Italic')}
        onClick={() => editor.chain().focus().toggleItalic().run()}
        className={btn(editor.isActive('italic'))}
      >
        <Italic size={14} />
      </button>
      <button
        type="button"
        title={t('Underline')}
        onClick={() => editor.chain().focus().toggleUnderline().run()}
        className={btn(editor.isActive('underline'))}
      >
        <UnderlineIcon size={14} />
      </button>
      <button
        type="button"
        title={t('Strikethrough')}
        onClick={() => editor.chain().focus().toggleStrike().run()}
        className={btn(editor.isActive('strike'))}
      >
        <Strikethrough size={14} />
      </button>

      <ToolbarSep />

      <button
        type="button"
        title={t('Subscript')}
        onClick={() => editor.chain().focus().toggleSubscript().run()}
        className={btn(editor.isActive('subscript'))}
      >
        <SubscriptIcon size={14} />
      </button>
      <button
        type="button"
        title={t('Superscript')}
        onClick={() => editor.chain().focus().toggleSuperscript().run()}
        className={btn(editor.isActive('superscript'))}
      >
        <SuperscriptIcon size={14} />
      </button>

      <ToolbarSep />

      {/* Text color */}
      <label
        title={t('Text Color')}
        className="relative p-1.5 rounded cursor-pointer text-gray-500 hover:bg-gray-100 hover:text-gray-700 shrink-0 flex flex-col items-center gap-px"
      >
        <Palette size={14} />
        <span className="block w-3.5 h-0.5 rounded" style={{ backgroundColor: textColor }} />
        <input
          type="color"
          className="absolute inset-0 opacity-0 w-full h-full cursor-pointer"
          value={textColor}
          onChange={(e) => editor.chain().focus().setColor(e.target.value).run()}
        />
      </label>

      {/* Highlight color */}
      <label
        title={t('Highlight Color')}
        className="relative p-1.5 rounded cursor-pointer text-gray-500 hover:bg-gray-100 hover:text-gray-700 shrink-0 flex flex-col items-center gap-px"
      >
        <Highlighter size={14} />
        <span className="block w-3.5 h-0.5 rounded" style={{ backgroundColor: highlightColor }} />
        <input
          type="color"
          className="absolute inset-0 opacity-0 w-full h-full cursor-pointer"
          value={highlightColor}
          onChange={(e) => editor.chain().focus().toggleHighlight({ color: e.target.value }).run()}
        />
      </label>

      <ToolbarSep />

      {/* Alignment */}
      <button
        type="button"
        title={t('Align Left')}
        onClick={() => editor.chain().focus().setTextAlign('left').run()}
        className={btn(editor.isActive({ textAlign: 'left' }))}
      >
        <AlignLeft size={14} />
      </button>
      <button
        type="button"
        title={t('Align Center')}
        onClick={() => editor.chain().focus().setTextAlign('center').run()}
        className={btn(editor.isActive({ textAlign: 'center' }))}
      >
        <AlignCenter size={14} />
      </button>
      <button
        type="button"
        title={t('Align Right')}
        onClick={() => editor.chain().focus().setTextAlign('right').run()}
        className={btn(editor.isActive({ textAlign: 'right' }))}
      >
        <AlignRight size={14} />
      </button>

      <ToolbarSep />

      {/* Lists */}
      <button
        type="button"
        title={t('Bullet List')}
        onClick={() => editor.chain().focus().toggleBulletList().run()}
        className={btn(editor.isActive('bulletList'))}
      >
        <List size={14} />
      </button>
      <button
        type="button"
        title={t('Numbered List')}
        onClick={() => editor.chain().focus().toggleOrderedList().run()}
        className={btn(editor.isActive('orderedList'))}
      >
        <ListOrdered size={14} />
      </button>

      <ToolbarSep />

      {/* Blockquote */}
      <button
        type="button"
        title={t('Blockquote')}
        onClick={() => editor.chain().focus().toggleBlockquote().run()}
        className={btn(editor.isActive('blockquote'))}
      >
        <Quote size={14} />
      </button>

      <ToolbarSep />

      {/* Table */}
      <button
        type="button"
        title={t('Insert Table')}
        onClick={() =>
          editor.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run()
        }
        className={btn(false)}
      >
        <TableIcon size={14} />
      </button>

      {/* Horizontal Rule */}
      <button
        type="button"
        title={t('Horizontal Rule')}
        onClick={() => editor.chain().focus().setHorizontalRule().run()}
        className={btn(false)}
      >
        <Minus size={14} />
      </button>

      {/* Special Characters */}
      <button
        type="button"
        title={t('Special Characters')}
        onClick={() => setShowSpecialChars((prev) => !prev)}
        className={btn(showSpecialChars)}
      >
        <Omega size={14} />
      </button>
    </div>
  );
}

export default function AddNotificationModal({
  isOpen = true,
  onClose,
  onSave,
  editNotificationId,
}: AddNotificationModalProps) {
  const { t } = useTranslation();
  const { user } = useUserContext();
  const timeZone = user?.settings?.defaultTimezone;
  const [isSaving, setIsSaving] = useState(false);
  const [draft, setDraft] = useState<NotificationDraft>({ ...DEFAULT_DRAFT });
  const [errors, setErrors] = useState<DraftErrors>({});
  const [apiError, setApiError] = useState<string | undefined>();
  const [activeTab, setActiveTab] = useState<'general' | 'audience'>('general');
  const [attachmentFile, setAttachmentFile] = useState<File | null>(null);
  const [existingAttachmentName, setExistingAttachmentName] = useState<string | null>(null);
  const [attachmentCleared, setAttachmentCleared] = useState(false);
  const [isUploading, setIsUploading] = useState(false);
  const [isFetchingEdit, setIsFetchingEdit] = useState(false);
  const [pendingBody, setPendingBody] = useState<string | null>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);

  const editor = useEditor({
    extensions: [
      ...EDITOR_EXTENSIONS,
      Placeholder.configure({ placeholder: t('Write your notification message here…') }),
    ],
    content: '',
    editorProps: {
      attributes: {
        class: 'tiptap min-h-40 p-3 focus:outline-none',
      },
    },
  });

  const tsToIso = (ts: unknown): string => {
    const n = Number(ts);
    if (!ts || isNaN(n) || n <= 0) return '';
    return new Date(n * 1000).toISOString();
  };

  useEffect(() => {
    if (!isOpen) {
      setDraft({ ...DEFAULT_DRAFT });
      setErrors({});
      setApiError(undefined);
      setActiveTab('general');
      setAttachmentFile(null);
      setExistingAttachmentName(null);
      setAttachmentCleared(false);
      setPendingBody(null);
      editor?.commands.setContent('');
    }
  }, [isOpen, editor]);

  useEffect(() => {
    if (!isOpen || editNotificationId == null) return;
    setAttachmentFile(null);
    setExistingAttachmentName(null);
    setAttachmentCleared(false);
    if (fileInputRef.current) fileInputRef.current.value = '';
    setIsFetchingEdit(true);
    fetchNotificationById(editNotificationId)
      .then((notification) => {
        setDraft({
          ...DEFAULT_DRAFT,
          subject: notification.subject ?? '',
          releaseDt: tsToIso(notification.releaseDt),
          isInterrupt: notification.isInterrupt === 1,
          recipientIds: (notification.userGroups ?? []).map((g) => String(g.groupId)),
          displayRecipientIds: (notification.displayGroups ?? []).map((g) =>
            String(g.displayGroupId),
          ),
          nonuserTags: notification.nonusers
            ? notification.nonusers
                .split(',')
                .map((email) => email.trim())
                .filter(Boolean)
                .map((email) => ({ tag: email, value: '', tagId: 0 }))
            : [],
        });
        setPendingBody(notification.body ?? '');
        setExistingAttachmentName(notification.originalFileName ?? null);
      })
      .catch((err: unknown) => {
        if (err instanceof Error) {
          setApiError(err.message);
        } else {
          setApiError(t('Failed to load notification.'));
        }
      })
      .finally(() => setIsFetchingEdit(false));
  }, [isOpen, editNotificationId, t]);

  useEffect(() => {
    if (editor && pendingBody !== null) {
      editor.commands.setContent(pendingBody);
      setPendingBody(null);
    }
  }, [editor, pendingBody]);

  const { data: userGroupData } = useQuery({
    queryKey: ['userGroups', 'notification-form'],
    queryFn: () => fetchUserGroups({ start: 0, length: 1000 }),
    staleTime: 5 * 60 * 1000,
    enabled: activeTab === 'audience',
  });

  const { data: userData } = useQuery({
    queryKey: ['users', 'notification-form'],
    queryFn: () => fetchUsers({ start: 0, length: 1000 }),
    staleTime: 5 * 60 * 1000,
    enabled: activeTab === 'audience',
  });

  const { data: displayGroupData } = useQuery({
    queryKey: ['displayGroups', 'notification-form'],
    queryFn: () => fetchDisplayGroups({ start: 0, length: 1000, isDisplaySpecific: 0 }),
    staleTime: 5 * 60 * 1000,
    enabled: activeTab === 'audience',
  });

  const { data: displayData } = useQuery({
    queryKey: ['displays', 'notification-form'],
    queryFn: () => fetchDisplayGroups({ start: 0, length: 1000, isDisplaySpecific: 1 }),
    staleTime: 5 * 60 * 1000,
    enabled: activeTab === 'audience',
  });

  const recipientOptions = [
    ...(userGroupData?.rows ?? []).map((g) => ({ value: String(g.groupId), label: g.group })),
    ...(userData?.rows ?? [])
      .filter((u) => u.groupId != null)
      .map((u) => ({ value: String(u.groupId), label: u.userName })),
  ];

  const displayRecipientOptions = [
    ...(displayGroupData?.rows ?? []).map((g) => ({
      value: String(g.displayGroupId),
      label: g.displayGroup,
    })),
    ...(displayData?.rows ?? []).map((g) => ({
      value: String(g.displayGroupId),
      label: g.displayGroup,
    })),
  ];

  const isPastDate = (isoString: string): boolean => {
    if (!isoString) return false;
    const tz = timeZone ?? 'UTC';
    return DateTime.fromISO(isoString, { zone: tz }) < DateTime.now().setZone(tz);
  };

  const formatDateForApi = (isoString: string): string => {
    const dt = DateTime.fromISO(isoString, { zone: timeZone ?? 'UTC' });
    return dt.toFormat('yyyy-MM-dd HH:mm:ss');
  };

  const validate = (): boolean => {
    const next: DraftErrors = {};
    if (!draft.subject.trim()) next.subject = t('Subject is required.');
    if (!draft.releaseDt) {
      next.releaseDt = t('Release Date is required.');
    } else if (editNotificationId == null && isPastDate(draft.releaseDt)) {
      next.releaseDt = t('Release Date cannot be in the past.');
    }
    const bodyText = editor?.getText() ?? '';
    if (!bodyText.trim()) next.body = t('Message body is required.');
    setErrors(next);

    const hasGeneralErrors = !!(next.subject || next.releaseDt || next.body);
    if (hasGeneralErrors && activeTab === 'audience') {
      setActiveTab('general');
    }
    return Object.keys(next).length === 0;
  };

  const handleSave = async () => {
    if (isSaving || !validate()) return;

    setIsSaving(true);
    setApiError(undefined);
    try {
      let attachedFilename: string | undefined;

      if (attachmentFile) {
        setIsUploading(true);
        try {
          attachedFilename = await uploadNotificationAttachment(attachmentFile);
        } finally {
          setIsUploading(false);
        }
      }

      const payload = {
        subject: draft.subject,
        releaseDt: formatDateForApi(draft.releaseDt),
        isInterrupt: draft.isInterrupt ? 1 : 0,
        body: editor?.getHTML() ?? '',
        userGroupIds: draft.recipientIds,
        displayGroupIds: draft.displayRecipientIds,
        nonusers: draft.nonuserTags.map((tag) => tag.tag).join(',') || undefined,
        attachedFilename,
        ...(editNotificationId != null && {
          clearAttachment: attachmentCleared && !attachmentFile,
        }),
      };

      if (editNotificationId != null) {
        await updateNotification(editNotificationId, payload);
      } else {
        await createNotification(payload);
      }

      onSave();
      onClose();
    } catch (err: unknown) {
      if (isAxiosError(err) && err.response?.data?.message) {
        setApiError(err.response.data.message);
      } else if (err instanceof Error) {
        setApiError(err.message);
      } else {
        setApiError(t('An unexpected error occurred while saving the notification.'));
      }
    } finally {
      setIsSaving(false);
    }
  };

  const tabs: { key: 'general' | 'audience'; label: string }[] = [
    { key: 'general', label: t('General') },
    { key: 'audience', label: t('Audience') },
  ];

  return (
    <Modal
      title={editNotificationId != null ? t('Edit Notification') : t('Add Notification')}
      isOpen={isOpen}
      onClose={onClose}
      isPending={isSaving || isUploading || isFetchingEdit}
      size="lg"
      variant="tabbed"
      error={apiError}
      actions={[
        {
          label: t('Cancel'),
          onClick: onClose,
          variant: 'secondary',
          disabled: isSaving || isUploading || isFetchingEdit,
        },
        {
          label: isSaving || isUploading ? t('Saving…') : t('Save'),
          onClick: handleSave,
          disabled: isSaving || isUploading || isFetchingEdit,
        },
      ]}
    >
      <div className="flex flex-col h-full overflow-hidden">
        {/* Tab nav */}
        <div className="flex border-b border-gray-200 px-8 shrink-0">
          {tabs.map((tab) => (
            <button
              key={tab.key}
              type="button"
              onClick={() => setActiveTab(tab.key)}
              className={twMerge(
                'px-4 py-3 text-sm font-medium border-b-2 -mb-px transition-colors cursor-pointer',
                activeTab === tab.key
                  ? 'border-xibo-blue-600 text-xibo-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700',
              )}
            >
              {tab.label}
            </button>
          ))}
        </div>

        {/* Tab content */}
        <div className="flex-1 min-h-0 overflow-y-auto p-8">
          {/* ───────── General tab ───────── */}
          {activeTab === 'general' && (
            <div className="flex flex-col gap-6">
              {/* Details section */}
              <div>
                <SectionHeader>{t('Details')}</SectionHeader>
                <div className="flex gap-4 items-start">
                  <div className="flex-1 min-w-0">
                    <TextInput
                      name="subject"
                      label={t('Subject')}
                      labelExtra={<span className="text-red-500">*</span>}
                      helpText={t('A subject line for the notification - used as a title.')}
                      value={draft.subject}
                      onChange={(v) => setDraft((p) => ({ ...p, subject: v }))}
                      error={errors.subject}
                    />
                  </div>
                  <div className="flex-1 min-w-0">
                    <DatePickerInput
                      label={t('Release Date')}
                      labelExtra={<span className="text-red-500">*</span>}
                      helpText={t('The date when this notification will be published')}
                      value={draft.releaseDt}
                      onChange={(v) => setDraft((p) => ({ ...p, releaseDt: v }))}
                      showTimePicker
                      disablePastDates={editNotificationId == null}
                      error={errors.releaseDt}
                    />
                  </div>
                </div>

                <div className="mt-4">
                  <Checkbox
                    id="isInterrupt"
                    title={t('Interrupt?')}
                    label={t(
                      'Should the notification interrupt navigation in the Web Portal? Including Login.',
                    )}
                    checked={draft.isInterrupt}
                    onChange={(e) => setDraft((p) => ({ ...p, isInterrupt: e.target.checked }))}
                  />
                </div>
              </div>

              {/* Message section */}
              <div>
                <SectionHeader labelExtra={<span className="text-red-500">*</span>}>
                  {t('Message')}
                </SectionHeader>
                <p className="text-sm text-gray-500 mb-3">
                  {t(
                    'Add the body of your message in the box below. If you are going to target this message to a Display/DisplayGroup be aware that the formatting you apply here will be removed.',
                  )}
                </p>
                <div
                  className={twMerge(
                    'border rounded-lg overflow-hidden',
                    errors.body ? 'border-red-400' : 'border-gray-200',
                  )}
                >
                  <TiptapToolbar editor={editor} />
                  <EditorContent editor={editor} />
                </div>
                {errors.body && <p className="text-xs text-red-600 ml-2 mt-1">{errors.body}</p>}
              </div>

              {/* Attachment section */}
              <div>
                <SectionHeader>{t('Attachment')}</SectionHeader>
                {attachmentFile ? (
                  <div className="flex items-center gap-3 p-3 border border-gray-200 rounded-lg bg-gray-50">
                    <Paperclip size={16} className="text-gray-400 shrink-0" />
                    <span className="flex-1 text-sm text-gray-700 truncate">
                      {attachmentFile.name}
                    </span>
                    <button
                      type="button"
                      onClick={() => {
                        setAttachmentFile(null);
                        if (fileInputRef.current) fileInputRef.current.value = '';
                      }}
                      className="text-gray-400 hover:text-red-500 transition-colors cursor-pointer"
                    >
                      <X size={16} />
                    </button>
                  </div>
                ) : existingAttachmentName ? (
                  <div className="flex items-center gap-3 p-3 border border-gray-200 rounded-lg bg-gray-50">
                    <Paperclip size={16} className="text-gray-400 shrink-0" />
                    <span className="flex-1 text-sm text-gray-700 truncate">
                      {existingAttachmentName}
                    </span>
                    <button
                      type="button"
                      onClick={() => {
                        setExistingAttachmentName(null);
                        setAttachmentCleared(true);
                      }}
                      className="text-gray-400 hover:text-red-500 transition-colors cursor-pointer"
                    >
                      <X size={16} />
                    </button>
                  </div>
                ) : (
                  <div
                    className="flex flex-col items-center justify-center gap-2 p-6 border-2 border-dashed border-gray-200 rounded-lg cursor-pointer hover:border-xibo-blue-400 hover:bg-blue-50 transition-colors"
                    onClick={() => fileInputRef.current?.click()}
                  >
                    <Paperclip size={20} className="text-gray-400" />
                    <p className="text-sm text-gray-500">{t('Click to add an attachment')}</p>
                    <p className="text-xs text-gray-400">
                      {t('Accepted: jpg, jpeg, png, bmp, gif, zip, pdf')}
                    </p>
                  </div>
                )}
                <input
                  ref={fileInputRef}
                  type="file"
                  className="hidden"
                  accept=".jpg,.jpeg,.png,.bmp,.gif,.zip,.pdf"
                  onChange={(e) => {
                    const file = e.target.files?.[0];
                    if (file) setAttachmentFile(file);
                  }}
                />
              </div>
            </div>
          )}

          {/* ───────── Audience tab ───────── */}
          {activeTab === 'audience' && (
            <div className="flex flex-col gap-6">
              {/* Internal Recipients */}
              <div>
                <SectionHeader>{t('Internal Recipients')}</SectionHeader>
                <MultiSelectDropdown
                  label={t('Users & User Groups')}
                  value={draft.recipientIds}
                  options={recipientOptions}
                  onChange={(v) => setDraft((p) => ({ ...p, recipientIds: v }))}
                  helpText={t(
                    'Please select one or more users / groups who will receive this notification.',
                  )}
                  showTags
                  optional
                />
              </div>

              {/* External Recipients */}
              <div>
                <SectionHeader>{t('External Recipients')}</SectionHeader>
                <TagInput
                  label={t('Non users')}
                  value={draft.nonuserTags}
                  onChange={(tags) => setDraft((p) => ({ ...p, nonuserTags: tags }))}
                  placeholder={t('Add email and press Enter or comma')}
                  helpText={t('Additional emails separated by a comma.')}
                  optional
                  allowValues={false}
                />
              </div>

              {/* Target Displays */}
              <div>
                <SectionHeader>{t('Target Displays')}</SectionHeader>
                <MultiSelectDropdown
                  label={t('Displays & Display Groups')}
                  value={draft.displayRecipientIds}
                  options={displayRecipientOptions}
                  onChange={(v) => setDraft((p) => ({ ...p, displayRecipientIds: v }))}
                  helpText={t(
                    'Please select one or more displays / groups for this notification to be shown on - Layouts will need the notification widget.',
                  )}
                  showTags
                  optional
                />
              </div>
            </div>
          )}
        </div>
      </div>
    </Modal>
  );
}
