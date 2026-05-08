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

import { Minus, Plus } from 'lucide-react';
import { useEffect, useState, useTransition } from 'react';
import { useTranslation } from 'react-i18next';

import Button from '@/components/ui/Button';
import MediaInput from '@/components/ui/forms/MediaInput';
import NumberInput from '@/components/ui/forms/NumberInput';
import Switch from '@/components/ui/forms/Switch';
import TextInput from '@/components/ui/forms/TextInput';
import Modal from '@/components/ui/modals/Modal';
import { getMenuBoardProductSchema } from '@/schema/menuBoard';
import { createMenuBoardProduct, updateMenuBoardProduct } from '@/services/menuBoardApi';
import type { MenuBoardProduct } from '@/types/menuBoardProduct';

interface AddAndEditMenuBoardProductModalProps {
  type: 'add' | 'edit';
  isOpen?: boolean;
  menuCategoryId: string | number;
  data?: MenuBoardProduct | null;
  onClose: () => void;
  onSave: () => void;
}

interface ProductOptionRow {
  id: number;
  option: string;
  value: string;
}

interface ProductDraft {
  name: string;
  price: number | null;
  description: string;
  code: string;
  displayOrder: number | null;
  availability: boolean;
  allergyInfo: string;
  calories: number | null;
  mediaId: number | null;
  productOptions: ProductOptionRow[];
}

type ProductFormErrors = {
  name?: string;
  price?: string;
  description?: string;
  code?: string;
  displayOrder?: string;
  allergyInfo?: string;
  calories?: string;
};

const DEFAULT_DRAFT: ProductDraft = {
  name: '',
  price: null,
  description: '',
  code: '',
  displayOrder: null,
  availability: true,
  allergyInfo: '',
  calories: null,
  mediaId: null,
  productOptions: [],
};

const createDraftFromData = (data?: MenuBoardProduct | null): ProductDraft => {
  if (!data) {
    return { ...DEFAULT_DRAFT };
  }
  return {
    name: data.name ?? '',
    price: data.price ?? null,
    description: data.description ?? '',
    code: data.code ?? '',
    displayOrder: data.displayOrder ?? null,
    availability: (data.availability ?? 1) === 1,
    allergyInfo: data.allergyInfo ?? '',
    calories: data.calories ?? null,
    mediaId: data.mediaId || null,
    productOptions: (data.productOptions ?? []).map((o, i) => ({
      id: i,
      option: o.option,
      value: o.value,
    })),
  };
};

type ProductTab = 'general' | 'details' | 'options';

function tabClass(activeTab: ProductTab, tab: ProductTab): string {
  const isActive = activeTab === tab;
  return `py-2 px-3 inline-flex items-center gap-2 border-b-2 text-sm font-semibold whitespace-nowrap focus:outline-none transition-all ${
    isActive ? 'border-blue-600 text-blue-500' : 'border-gray-200 text-gray-500 hover:text-blue-600'
  }`;
}

export default function AddAndEditMenuBoardProductModal({
  type,
  isOpen = true,
  menuCategoryId,
  onClose,
  data,
  onSave,
}: AddAndEditMenuBoardProductModalProps) {
  const { t } = useTranslation();
  const [isPending, startTransition] = useTransition();
  const [apiError, setApiError] = useState<string | undefined>();
  const [formErrors, setFormErrors] = useState<ProductFormErrors>({});
  const [activeTab, setActiveTab] = useState<ProductTab>('general');

  const [draft, setDraft] = useState<ProductDraft>(() => createDraftFromData(data));

  useEffect(() => {
    if (isOpen) {
      setDraft(createDraftFromData(data));
      setApiError(undefined);
      setFormErrors({});
      setActiveTab('general');
    }
  }, [data, isOpen]);

  const updateDraft = <K extends keyof ProductDraft>(field: K, value: ProductDraft[K]) => {
    setDraft((prev) => ({ ...prev, [field]: value }));
  };

  const clearError = (field: keyof ProductFormErrors) => {
    setFormErrors((prev) => ({ ...prev, [field]: undefined }));
  };

  const tab = (name: ProductTab) => tabClass(activeTab, name);

  const addOption = () => {
    setDraft((prev) => {
      const nextId =
        prev.productOptions.length > 0 ? Math.max(...prev.productOptions.map((r) => r.id)) + 1 : 0;
      return {
        ...prev,
        productOptions: [...prev.productOptions, { id: nextId, option: '', value: '' }],
      };
    });
  };

  const removeOption = (id: number) => {
    setDraft((prev) => ({
      ...prev,
      productOptions: prev.productOptions.filter((r) => r.id !== id),
    }));
  };

  const updateOption = (id: number, field: 'option' | 'value', value: string) => {
    setDraft((prev) => ({
      ...prev,
      productOptions: prev.productOptions.map((r) => (r.id === id ? { ...r, [field]: value } : r)),
    }));
  };

  const handleSave = () => {
    const schema = getMenuBoardProductSchema(t);
    const payload = {
      ...draft,
      availability: draft.availability ? 1 : 0,
      productOptions: draft.productOptions.map(({ option, value }) => ({ option, value })),
    };
    const result = schema.safeParse(payload);

    if (!result.success) {
      const fieldErrors = result.error.flatten().fieldErrors;
      const errors = {
        name: fieldErrors.name?.[0],
        description: fieldErrors.description?.[0],
        code: fieldErrors.code?.[0],
        allergyInfo: fieldErrors.allergyInfo?.[0],
      };
      setFormErrors(errors);
      if (errors.name || errors.code) {
        setActiveTab('general');
      } else if (errors.description || errors.allergyInfo) {
        setActiveTab('details');
      }
      return;
    }

    setFormErrors({});
    setApiError(undefined);

    startTransition(async () => {
      try {
        if (type === 'edit') {
          if (!data) {
            return;
          }
          await updateMenuBoardProduct(data.menuProductId, payload);
        } else {
          await createMenuBoardProduct(menuCategoryId, payload);
        }
        onSave();
        onClose();
      } catch (err: unknown) {
        const axiosError = err as { response?: { data?: { message?: string } } };
        setApiError(axiosError.response?.data?.message ?? t('An unexpected error occurred.'));
      }
    });
  };

  return (
    <Modal
      variant="tabbed"
      title={type === 'add' ? t('Add Product') : t('Edit Product')}
      onClose={onClose}
      isOpen={isOpen}
      isPending={isPending}
      error={apiError}
      actions={[
        { label: t('Cancel'), onClick: onClose, variant: 'secondary', disabled: isPending },
        { label: isPending ? t('Saving…') : t('Save'), onClick: handleSave, disabled: isPending },
      ]}
    >
      <>
        <nav className="flex px-4 overflow-x-auto shrink-0" aria-label="Tabs">
          <button type="button" className={tab('general')} onClick={() => setActiveTab('general')}>
            {t('General')}
          </button>
          <button type="button" className={tab('details')} onClick={() => setActiveTab('details')}>
            {t('Details')}
          </button>
          <button type="button" className={tab('options')} onClick={() => setActiveTab('options')}>
            {t('Product Options')}
          </button>
        </nav>

        <div className="px-8 pb-8 pt-4 space-y-4">
          {activeTab === 'general' && (
            <div className="space-y-4">
              <TextInput
                name="name"
                label={t('Name')}
                placeholder={t('Enter Name')}
                helpText={t('The Name for this Menu Board Product.')}
                value={draft.name}
                error={formErrors.name}
                onChange={(val) => {
                  updateDraft('name', val);
                  clearError('name');
                }}
              />

              <NumberInput
                name="price"
                label={t('Price')}
                helpText={t('The Price for this Menu Board Product.')}
                value={draft.price ?? undefined}
                onChange={(val) => {
                  updateDraft('price', val !== undefined ? val : null);
                }}
              />

              <NumberInput
                name="displayOrder"
                label={t('Display Order')}
                helpText={t('Set a display order for this item to appear.')}
                value={draft.displayOrder ?? undefined}
                onChange={(val) => {
                  updateDraft('displayOrder', val !== undefined ? Math.round(val) : null);
                }}
              />

              <TextInput
                name="code"
                label={t('Code')}
                placeholder={t('Enter Code')}
                helpText={t('The Code identifier for this Menu Board Product.')}
                value={draft.code}
                error={formErrors.code}
                onChange={(val) => {
                  updateDraft('code', val);
                  clearError('code');
                }}
              />

              <MediaInput
                label={t('Media')}
                helpText={t(
                  'Optionally select an Image or Video to be associated with this Menu Board Product.',
                )}
                value={draft.mediaId ?? undefined}
                mediaType=""
                onChange={(val) => {
                  updateDraft('mediaId', val ? Number(val) : null);
                }}
              />

              <Switch
                label={t('Availability')}
                helpText={t('Should this Product appear as available?')}
                checked={draft.availability}
                onChange={(val) => updateDraft('availability', val)}
              />
            </div>
          )}

          {activeTab === 'details' && (
            <div className="space-y-4">
              <TextInput
                name="description"
                label={t('Description')}
                placeholder={t('Enter a short description')}
                helpText={t('The Description for this Menu Board Product.')}
                value={draft.description}
                error={formErrors.description}
                multiline
                onChange={(val) => {
                  updateDraft('description', val);
                  clearError('description');
                }}
              />

              <TextInput
                name="allergyInfo"
                label={t('Allergy Information')}
                placeholder={t('Enter allergy information')}
                helpText={t('The Allergy Information for this Menu Board Product.')}
                value={draft.allergyInfo}
                error={formErrors.allergyInfo}
                multiline
                onChange={(val) => {
                  updateDraft('allergyInfo', val);
                  clearError('allergyInfo');
                }}
              />

              <NumberInput
                name="calories"
                label={t('Calories')}
                helpText={t('How many calories are in this product?')}
                value={draft.calories ?? undefined}
                onChange={(val) => {
                  updateDraft('calories', val !== undefined ? Math.round(val) : null);
                }}
              />
            </div>
          )}

          {activeTab === 'options' && (
            <div className="space-y-3">
              <p className="text-sm text-gray-500">
                {t(
                  'If required please provide additional options and their prices for this Product.',
                )}
              </p>

              {draft.productOptions.map((row) => (
                <div key={row.id} className="flex gap-3 items-center">
                  <input
                    type="text"
                    value={row.option}
                    placeholder={t('Option Name')}
                    onChange={(e) => updateOption(row.id, 'option', e.target.value)}
                    className="flex-1 h-11.25 px-3 rounded-lg text-sm border border-gray-200 text-gray-800 placeholder:text-gray-500 hover:border-gray-400 focus:border-xibo-blue-600 focus:ring-xibo-blue-600/25 focus:ring-1 focus:outline-none"
                  />
                  <input
                    type="number"
                    step="0.01"
                    min={0}
                    value={row.value}
                    placeholder={t('Option Value')}
                    onChange={(e) => updateOption(row.id, 'value', e.target.value)}
                    className="flex-1 h-11.25 px-3 rounded-lg text-sm border border-gray-200 text-gray-800 placeholder:text-gray-500 hover:border-gray-400 focus:border-xibo-blue-600 focus:ring-xibo-blue-600/25 focus:ring-1 focus:outline-none"
                  />
                  <Button
                    variant="secondary"
                    className="h-8 w-8 min-w-8"
                    onClick={() => removeOption(row.id)}
                  >
                    <Minus size={14} />
                  </Button>
                </div>
              ))}

              <Button className="w-full" onClick={addOption}>
                <Plus size={14} />
              </Button>
            </div>
          )}
        </div>
      </>
    </Modal>
  );
}
