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

import { ChevronLeft, Info, Loader2, TriangleAlert, Upload } from 'lucide-react';
import { useState } from 'react';
import { useDropzone } from 'react-dropzone';
import { Trans, useTranslation } from 'react-i18next';

import HelpUploadCard from './HelpUploadCard';
import { ACCEPT_ATTR, useFeedbackAttachments } from './useFeedbackAttachments';

import Button from '@/components/ui/Button';
import TextInput from '@/components/ui/forms/TextInput';
import { submitFeedback } from '@/services/helpApi';

interface FeedbackFormProps {
  userName: string;
  email: string;
  accountId: string;
  onBack: () => void;
  onSuccess: () => void;
}

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

interface FieldErrors {
  userName?: string;
  email?: string;
  message?: string;
}

export default function FeedbackForm({
  userName,
  email,
  accountId,
  onBack,
  onSuccess,
}: FeedbackFormProps) {
  const { t } = useTranslation();
  const { attachments, addFiles, removeFile, isMax } = useFeedbackAttachments(t);

  const [values, setValues] = useState({ userName, email, message: '' });
  const [errors, setErrors] = useState<FieldErrors>({});
  const [formError, setFormError] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  const { getRootProps, getInputProps, open, isDragActive } = useDropzone({
    onDrop: addFiles,
    noClick: true,
    noKeyboard: true,
    accept: {
      'image/jpeg': ['.jpg', '.jpeg'],
      'image/png': ['.png'],
      'application/pdf': ['.pdf'],
      'video/quicktime': ['.mov'],
    },
  });

  const setValue = (key: keyof typeof values) => (value: string) =>
    setValues((v) => ({ ...v, [key]: value }));

  const validate = (): boolean => {
    const next: FieldErrors = {};
    if (!values.userName.trim()) {
      next.userName = t('Please provide a valid name');
    }
    if (!EMAIL_RE.test(values.email.trim())) {
      next.email = t('Please provide a valid email');
    }
    if (!values.message.trim()) {
      next.message = t('Please provide any comment or recommendation');
    }
    setErrors(next);
    return Object.keys(next).length === 0;
  };

  const handleSubmit = async () => {
    setFormError('');
    if (!validate()) {
      setFormError(t('Please fill all required fields'));
      return;
    }

    const formData = new FormData();
    formData.append('userName', values.userName.trim());
    formData.append('email', values.email.trim());
    formData.append('message', values.message.trim());
    formData.append('accountId', accountId);
    formData.append('pageUrl', window.location.pathname);

    setIsSubmitting(true);
    try {
      await submitFeedback(
        formData,
        attachments.map((a) => a.file),
      );
      onSuccess();
    } catch (err) {
      const reason = err instanceof Error ? err.message : '';
      setFormError(
        reason && reason !== 'request' ? reason : t('Something went wrong. Please try again'),
      );
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="flex flex-col pt-5 pb-8 gap-5">
      {isSubmitting && (
        <div className="absolute inset-0 z-10 flex items-center justify-center bg-white/70">
          <Loader2 className="size-10 animate-spin text-xibo-blue-600" />
        </div>
      )}

      <div className="flex flex-col gap-3 px-8 pt-3">
        <div className="flex flex-col gap-1">
          <span className="text-base font-semibold text-gray-800">
            {t('We’d love to hear your thoughts!')}
          </span>
          <p className="text-xs font-normal leading-4.5 text-gray-500">
            {t(
              'The feedback you provide, along with your name and email address are used by Xibo to improve our product and services.',
            )}
          </p>
        </div>

        <TextInput
          name="userName"
          label={t('Name')}
          value={values.userName}
          placeholder=" "
          onChange={setValue('userName')}
          error={errors.userName}
        />
        <TextInput
          name="email"
          label={t('Email')}
          value={values.email}
          placeholder=" "
          onChange={setValue('email')}
          error={errors.email}
        />
        <TextInput
          name="message"
          label={t('Comments and recommendations')}
          value={values.message}
          placeholder=" "
          onChange={setValue('message')}
          error={errors.message}
          multiline
          rows={4}
        />

        <div>
          <div className="flex items-center justify-between text-sm font-semibold text-gray-500 leading-4.5 mb-1">
            <span className="inline-flex items-center gap-1.5">{t('Attachments')}</span>
            <span className="text-xs font-normal text-gray-500">{t('Optional')}</span>
          </div>

          {!isMax && (
            <>
              <div
                {...getRootProps()}
                className={`flex items-center gap-2 h-16 bg-gray-100 p-3 rounded-lg border border-dashed border-xibo-blue-600 ${
                  isDragActive ? 'border-2 border-xibo-blue-400 bg-gray-200' : ''
                }`}
              >
                <input {...getInputProps()} accept={ACCEPT_ATTR} />
                <Upload className="size-3.5 text-xibo-blue-600" />
                <div className="text-sm text-gray-500 leading-5.25">
                  <Trans
                    i18nKey="Drag and drop files or <browse>Browse files</browse>"
                    components={{
                      browse: (
                        <button
                          type="button"
                          onClick={open}
                          className="font-medium text-xibo-blue-600 hover:text-xibo-blue-800 cursor-pointer"
                        />
                      ),
                    }}
                  />
                </div>
              </div>
              <div className="text-xs text-gray-400 mt-1">
                <div>{t('(JPG, PNG, MOV and PDF. Max size is up to 15MB)')}</div>
              </div>
            </>
          )}

          {isMax && (
            <div className="flex items-start gap-2 rounded-lg bg-blue-50 p-3 text-sm text-blue-700">
              <Info className="mt-0.5 size-4 shrink-0" />
              <div>
                <div>{t('Maximum file upload of 3 files.')}</div>
                <div>{t('Remove one to add more.')}</div>
              </div>
            </div>
          )}

          {attachments.length > 0 && (
            <div className="mt-2 flex flex-col gap-2">
              {attachments.map((attachment) => (
                <HelpUploadCard
                  key={attachment.id}
                  attachment={attachment}
                  onRemove={() => removeFile(attachment.id)}
                />
              ))}
            </div>
          )}
        </div>
      </div>

      {formError && (
        <div className="flex items-center gap-2 rounded-lg bg-red-50 p-2 px-3 mx-8 text-sm text-red-700">
          <TriangleAlert className="size-4 shrink-0" />
          <span>{formError}</span>
        </div>
      )}

      <div className="flex items-center gap-3 mx-8">
        <Button variant="secondary" aria-label={t('Back')} onClick={onBack} disabled={isSubmitting}>
          <ChevronLeft className="size-5" />
        </Button>
        <Button onClick={handleSubmit} disabled={isSubmitting} className="flex-1">
          {t('Send feedback')}
        </Button>
      </div>
    </div>
  );
}
