import { HelpCircle, Upload } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { getMediaIcon } from '../MediaConfig';

import Button from '@/components/ui/Button';
import { notify } from '@/components/ui/Notification';
import Checkbox from '@/components/ui/forms/Checkbox';
import TagInput from '@/components/ui/forms/TagInput';
import Modal from '@/components/ui/modals/Modal';
import { replaceMedia } from '@/services/mediaApi';
import type { Media, MediaType } from '@/types/media';
import type { Tag } from '@/types/tag';

interface ReplaceFileMedia {
  name: string;
  tags: Tag[];
  oldMediaId: number;
  updateInLayouts: boolean;
  deleteOldRevisions: boolean;
}

interface ReplaceFileModalProps {
  openModal: boolean;
  data: Media;
  onClose: () => void;
  onSave?: (updated: Media) => void;
}

export default function ReplaceFileModal({
  openModal,
  data,
  onClose,
  onSave,
}: ReplaceFileModalProps) {
  const { t } = useTranslation();
  const [isSaving, setIsSaving] = useState(false);
  const [isImageLoading, setIsImageLoading] = useState(true);
  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const [uploadProgress, setUploadProgress] = useState(0);
  const fileInputRef = useRef<HTMLInputElement>(null);
  const Icon = getMediaIcon(data.mediaType);

  const [draft, setDraft] = useState<ReplaceFileMedia>(() => ({
    name: data.name,
    tags: data.tags.map((t) => ({ ...t })),
    deleteOldRevisions: data.deleteOldRevisions,
    oldMediaId: data.mediaId,
    updateInLayouts: data.updateInLayouts,
  }));

  const previewUrl = selectedFile ? URL.createObjectURL(selectedFile) : data.thumbnail;

  const handleSave = async () => {
    if (isSaving || !selectedFile) return;

    try {
      setIsSaving(true);
      setUploadProgress(0);

      const res = await replaceMedia({
        file: selectedFile,
        oldMediaId: draft.oldMediaId,
        name: draft.name,
        folderId: data.folderId,
        tags: draft.tags.map((t) => `${t.tag}${t.value ? `|${t.value}` : ''}`),
        updateInLayouts: draft.updateInLayouts,
        deleteOldRevisions: draft.deleteOldRevisions,
        onProgress: (p) => setUploadProgress(p),
      });

      const uploaded = res.data.files?.[0];

      if (!uploaded || uploaded.error) {
        throw new Error(uploaded?.error || 'Replace failed');
      }

      onSave?.(uploaded as unknown as Media);
      setIsSaving(false);
      onClose();
      notify.success('Media Replaced Successfully');
    } catch (err) {
      console.error('Replace media failed:', err);
      setIsSaving(false);
      notify.error?.('Failed to replace media');
    }
  };

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    setSelectedFile(file);
    setIsImageLoading(true);
  };

  const getAcceptByMediaType = (type: MediaType) => {
    switch (type) {
      case 'image':
        return 'image/*';

      case 'video':
        return 'video/*';

      case 'audio':
        return 'audio/*';

      case 'pdf':
        return 'application/pdf,.pdf';

      case 'archive':
        return '.zip,.rar,.7z,.tar,.gz';

      case 'other':
      default:
        return '';
    }
  };

  useEffect(() => {
    setDraft({
      name: data.name,
      tags: data.tags.map((t) => ({ ...t })),
      deleteOldRevisions: data.deleteOldRevisions,
      oldMediaId: data.mediaId,
      updateInLayouts: data.updateInLayouts,
    });
  }, [data]);

  useEffect(() => {
    if (!selectedFile) return;

    const url = URL.createObjectURL(selectedFile);
    return () => URL.revokeObjectURL(url);
  }, [selectedFile]);

  return (
    <Modal
      title={t('Replace File')}
      onClose={onClose}
      isOpen={openModal}
      isPending={isSaving}
      scrollable={false}
      actions={[
        {
          label: t('Cancel'),
          onClick: onClose,
          variant: 'secondary',
          disabled: isSaving,
        },
        {
          label: isSaving ? t('Saving…') : t('Save'),
          onClick: handleSave,
          disabled: isSaving || !selectedFile,
        },
      ]}
    >
      <div className="flex flex-col h-full overflow-y-hidden overflow-x-visible gap-3 p-3 pt-0">
        <div className=" border border-gray-200 mx-4 rounded-lg">
          <div className="shrink-0 p-4 flex gap-3">
            <div className="h-37.5 aspect-7/6 relative bg-gray-400 rounded-lg">
              <div className="h-37.5 aspect-7/6 bg-gray-100 flex items-center justify-center rounded">
                {data.thumbnail ? (
                  <>
                    {isImageLoading && (
                      <div className="absolute inset-0 animate-pulse bg-gray-200" />
                    )}
                    <img
                      src={previewUrl}
                      alt={data.fileName}
                      onLoad={() => setIsImageLoading(false)}
                      onError={() => setIsImageLoading(false)}
                      className={`h-full w-full object-contain transition-opacity duration-300 ${
                        isImageLoading ? 'opacity-0' : 'opacity-100'
                      }`}
                    />
                  </>
                ) : (
                  <Icon className="w-10 h-10 text-gray-400" />
                )}
              </div>
            </div>
            <div className="flex flex-col justify-between flex-1">
              <div>
                <span className="text-sm text-gray-500 font-semibold flex items-center gap-1">
                  {t('FILE NAME')} <HelpCircle size={12} />
                </span>
                <span className="text-sm">{selectedFile?.name || t(data.fileName)}</span>
              </div>
              <div>
                <span className="text-sm text-gray-500 font-semibold flex items-center gap-1">
                  {t('FILE SIZE')} <HelpCircle size={12} />
                </span>
                <span className="text-sm">{t(data.fileSizeFormatted)}</span>
              </div>
              <div>
                <span className="text-sm text-gray-500 font-semibold flex items-center gap-1">
                  {t('RESOLUTION')} <HelpCircle size={12} />
                </span>
                <span className="text-sm">
                  {data.width} x {data.height}
                </span>
              </div>
            </div>
            <div>
              <input
                ref={fileInputRef}
                type="file"
                className="hidden"
                accept={getAcceptByMediaType(data.mediaType)}
                onChange={handleFileChange}
              />
              <Button
                leftIcon={Upload}
                variant="secondary"
                className="border-0 bg-transparent"
                onClick={() => fileInputRef.current?.click()}
              >
                {t('Select File')}
              </Button>
            </div>
          </div>
          {selectedFile && isSaving && (
            <div className="flex flex-col">
              <span className="text-xs text-xibo-black font-semibold mt-1 block px-4">
                {selectedFile.name}
              </span>
              <div className="px-4 pb-3 flex items-center gap-4">
                <div className="h-2 bg-gray-200 rounded overflow-hidden w-full">
                  <div
                    className="h-full bg-blue-500 transition-all duration-200"
                    style={{ width: `${uploadProgress}%` }}
                  />
                </div>
                <span className="text-xs text-xibo-black mt-1 block">{uploadProgress}%</span>
              </div>
            </div>
          )}
        </div>
        <div className="flex flex-col gap-3 flex-1 min-h-0 p-4 overflow-y-auto">
          {/* Name */}
          <div className="flex flex-col">
            <label htmlFor="name" className="text-xs font-semibold text-gray-500 leading-5">
              {t('Name')}
            </label>
            <input
              id="name"
              className="border-gray-200 text-sm rounded-lg"
              name="name"
              value={draft.name}
              onChange={(e) => setDraft((prev) => ({ ...prev, name: e.target.value }))}
            />
          </div>
          <TagInput
            value={draft.tags}
            helpText={t('Tags (Comma-separated: Tag or Tag|Value)')}
            onChange={(tags) => setDraft((prev) => ({ ...prev, tags }))}
          />
          {/* Retired */}
          <div className="bg-gray-50 flex flex-col ">
            <Checkbox
              id="retired"
              className="items-start p-2.5"
              title={t('Delete Old Version')}
              label={t(
                `Overwrite the existing file. If unchecked, the old version is saved in the CMS.`,
              )}
              checked={draft.deleteOldRevisions}
              classNameLabel="text-xs"
              onChange={() =>
                setDraft((prev) => ({ ...prev, deleteOldRevisions: !prev.deleteOldRevisions }))
              }
            />
            <Checkbox
              id="update"
              className="items-start p-2.5"
              title={t('Update across all Layouts')}
              label={t(`Applies the change to every layout you have permission to edit.`)}
              checked={draft.updateInLayouts}
              classNameLabel="text-xs"
              onChange={() =>
                setDraft((prev) => ({ ...prev, updateInLayouts: !prev.updateInLayouts }))
              }
            />
          </div>
        </div>
      </div>
    </Modal>
  );
}
