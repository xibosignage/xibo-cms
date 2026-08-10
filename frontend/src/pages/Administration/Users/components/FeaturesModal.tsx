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

import { isAxiosError } from 'axios';
import { ChevronDown } from 'lucide-react';
import { useEffect, useState, useTransition } from 'react';
import { useTranslation } from 'react-i18next';

import {
  getFeatureCategories,
  getFeatureDefinitions,
  getGroupDescriptions,
  getGroupLabels,
} from '../config/featureDefinitions';
import type { FeatureDefinition } from '../config/featureDefinitions';

import Modal from '@/components/ui/modals/Modal';
import { fetchUserGroupById, fetchUserGroups, updateGroupFeatures } from '@/services/userGroupApi';
import type { User } from '@/types/user';

type FeatureTab = string;

function tabClass(activeTab: FeatureTab, tab: FeatureTab): string {
  const isActive = activeTab === tab;
  return `py-2 px-3 inline-flex items-center gap-2 border-b-2 text-sm font-semibold whitespace-nowrap focus:outline-none transition-all ${
    isActive
      ? 'border-xibo-blue-600 text-xibo-blue-500'
      : 'border-gray-200 text-gray-500 hover:text-xibo-blue-600'
  }`;
}

export interface FeaturesModalProps {
  user: User;
  onClose: () => void;
  onSuccess: () => void;
}

export default function FeaturesModal({ user, onClose, onSuccess }: FeaturesModalProps) {
  const { t } = useTranslation();
  const [isPending, startTransition] = useTransition();
  const [apiError, setApiError] = useState<string | undefined>();
  const [isLoading, setIsLoading] = useState(true);

  const [enabledFeatures, setEnabledFeatures] = useState<Set<string>>(new Set());
  const [inheritedFeatures, setInheritedFeatures] = useState<Set<string>>(new Set());
  const [expandedGroups, setExpandedGroups] = useState<Set<string>>(new Set());

  const categories = getFeatureCategories(t);
  const allFeatures = getFeatureDefinitions(t);
  const groupLabels = getGroupLabels(t);
  const groupDescriptions = getGroupDescriptions(t);
  const [activeTab, setActiveTab] = useState<FeatureTab>(categories[0]?.key ?? 'content');

  // Load the user's group features + compute inherited from other groups
  useEffect(() => {
    if (!user.groupId) {
      setIsLoading(false);
      return;
    }

    setIsLoading(true);

    Promise.all([
      // Fetch the user-specific group features (directly assigned)
      fetchUserGroupById(user.groupId),
      // Fetch the groups this user actually belongs to
      fetchUserGroups({ start: 0, length: 1000, userIdMember: user.userId }),
    ])
      .then(([userGroup, memberGroups]) => {
        setEnabledFeatures(new Set(userGroup.features ?? []));

        // Inherited = features from non-user-specific groups the user belongs to
        const inherited = new Set<string>();
        for (const group of memberGroups.rows) {
          if (group.isUserSpecific !== 1 && group.features) {
            group.features.forEach((f) => inherited.add(f));
          }
        }
        setInheritedFeatures(inherited);
      })
      .catch(() => {
        setEnabledFeatures(new Set());
        setInheritedFeatures(new Set());
      })
      .finally(() => setIsLoading(false));
  }, [user.groupId, user.userId]);

  const toggleFeature = (feature: string) => {
    setEnabledFeatures((prev) => {
      const next = new Set(prev);
      if (next.has(feature)) {
        next.delete(feature);
      } else {
        next.add(feature);
      }
      return next;
    });
  };

  const toggleGroup = (features: FeatureDefinition[]) => {
    const allEnabled = features.every((f) => enabledFeatures.has(f.feature));
    setEnabledFeatures((prev) => {
      const next = new Set(prev);
      features.forEach((f) => {
        if (allEnabled) {
          next.delete(f.feature);
        } else {
          next.add(f.feature);
        }
      });
      return next;
    });
  };

  const toggleExpand = (groupKey: string) => {
    setExpandedGroups((prev) => {
      const next = new Set(prev);
      if (next.has(groupKey)) {
        next.delete(groupKey);
      } else {
        next.add(groupKey);
      }
      return next;
    });
  };

  const handleSave = () => {
    if (!user.groupId) return;

    startTransition(async () => {
      try {
        await updateGroupFeatures(user.groupId!, Array.from(enabledFeatures));
        onSuccess();
        onClose();
      } catch (err: unknown) {
        if (isAxiosError(err) && err.response?.data?.message) {
          setApiError(err.response.data.message);
        } else if (err instanceof Error) {
          setApiError(err.message);
        } else {
          setApiError(t('An unexpected error occurred.'));
        }
      }
    });
  };

  // Get features for the active tab
  const activeCategory = categories.find((c) => c.key === activeTab);
  const activeCategoryGroups = activeCategory?.groups ?? [];

  // Group features by their backend group within the active category
  const featuresByGroup: {
    groupKey: string;
    label: string;
    description?: string;
    features: FeatureDefinition[];
  }[] = [];
  for (const groupKey of activeCategoryGroups) {
    const groupFeatures = allFeatures.filter((f) => f.group === groupKey);
    if (groupFeatures.length > 0) {
      featuresByGroup.push({
        groupKey,
        label: groupLabels[groupKey] ?? groupKey,
        description: groupDescriptions[groupKey],
        features: groupFeatures,
      });
    }
  }

  return (
    <Modal
      variant="tabbed"
      isOpen
      onClose={onClose}
      title={t('Features for {{name}}', { name: user.userName })}
      size="lg"
      scrollable={false}
      isPending={isPending || isLoading}
      error={apiError}
      actions={[
        {
          label: t('Cancel'),
          onClick: onClose,
          variant: 'secondary' as const,
          disabled: isPending,
        },
        {
          label: isPending ? t('Saving...') : t('Save'),
          onClick: handleSave,
          disabled: isPending || isLoading,
        },
      ]}
    >
      <div className="flex flex-col flex-1 min-h-0 overflow-hidden px-4">
        <p className="text-sm text-gray-500 px-4 py-2">
          {t(
            'Check or un-check the options against each item to control whether access to a Feature is allowed or not.',
          )}
        </p>

        {/* Category tabs */}
        <div
          role="tablist"
          aria-label={t('Feature category tabs')}
          className="flex px-4 overflow-x-auto shrink-0"
        >
          {categories.map(({ key, label }) => (
            <button
              key={key}
              role="tab"
              type="button"
              aria-selected={activeTab === key}
              className={tabClass(activeTab, key)}
              onClick={() => setActiveTab(key)}
            >
              {label}
            </button>
          ))}
        </div>

        {/* Feature list */}
        <div role="tabpanel" className="flex-1 min-h-0 overflow-y-auto p-4">
          {isLoading ? (
            <div className="flex items-center justify-center py-8 text-gray-400 text-sm">
              {t('Loading...')}
            </div>
          ) : (
            <div className="border border-gray-200 rounded-lg overflow-hidden">
              {/* Header */}
              <div className="grid grid-cols-[1fr_100px_100px] items-center bg-gray-50 border-b border-gray-200 px-4 py-2.5">
                <span className="text-sm font-medium text-gray-600">{t('Feature')}</span>
                <span className="text-sm font-medium text-gray-600 text-center">
                  {t('Enabled?')}
                </span>
                <span className="text-sm font-medium text-gray-600 text-center">
                  {t('Inherited?')}
                </span>
              </div>

              {featuresByGroup.map(({ groupKey, label, description, features }) => {
                const isExpanded = expandedGroups.has(groupKey);
                const allEnabled = features.every((f) => enabledFeatures.has(f.feature));
                const someEnabled =
                  !allEnabled && features.some((f) => enabledFeatures.has(f.feature));
                const allInherited = features.every((f) => inheritedFeatures.has(f.feature));
                const someInherited =
                  !allInherited && features.some((f) => inheritedFeatures.has(f.feature));

                return (
                  <div key={groupKey}>
                    {/* Group header row */}
                    <div className="grid grid-cols-[1fr_100px_100px] items-center border-b border-gray-100 hover:bg-gray-50 transition-colors pr-3">
                      <button
                        type="button"
                        onClick={() => toggleExpand(groupKey)}
                        className="flex items-center gap-2 px-4 py-3 text-left"
                      >
                        <ChevronDown
                          size={14}
                          className={`text-gray-500 transition-transform duration-200 shrink-0 mt-0.5 ${
                            isExpanded ? 'rotate-180' : 'rotate-0'
                          }`}
                        />
                        <div>
                          <span className="text-sm font-bold text-gray-800">{label}</span>
                          {!isExpanded && description && (
                            <p className="text-xs text-gray-500 font-normal mt-0.5">
                              {description}
                            </p>
                          )}
                        </div>
                      </button>
                      <div className="flex justify-center">
                        <input
                          type="checkbox"
                          aria-label={t('Enable all in {{group}}', { group: label })}
                          checked={allEnabled}
                          ref={(el) => {
                            if (el) el.indeterminate = someEnabled;
                          }}
                          onChange={() => toggleGroup(features)}
                          className="h-4 w-4 border-gray-300 rounded cursor-pointer text-xibo-blue-600 focus:ring-xibo-blue-500"
                        />
                      </div>
                      <div className="flex justify-center">
                        <input
                          type="checkbox"
                          aria-label={t('{{group}} inherited', { group: label })}
                          checked={allInherited}
                          ref={(el) => {
                            if (el) el.indeterminate = someInherited;
                          }}
                          disabled
                          className="h-4 w-4 border-gray-300 rounded text-gray-500 disabled:cursor-not-allowed disabled:bg-slate-100 checked:disabled:border-gray-500 checked:disabled:bg-gray-500 indeterminate:disabled:border-gray-500 indeterminate:disabled:bg-gray-500"
                        />
                      </div>
                    </div>

                    {/* Expanded child features */}
                    {isExpanded &&
                      features.map((feat) => (
                        <div
                          key={feat.feature}
                          className="grid grid-cols-[1fr_100px_100px] items-center border-b border-gray-50 hover:bg-xibo-blue-50/30 transition-colors pr-3"
                        >
                          <div className="px-4 py-2.5 pl-10">
                            <span className="text-sm text-gray-700">{feat.title}</span>
                          </div>
                          <div className="flex justify-center">
                            <input
                              type="checkbox"
                              aria-label={t('Enable {{feature}}', { feature: feat.title })}
                              checked={enabledFeatures.has(feat.feature)}
                              onChange={() => toggleFeature(feat.feature)}
                              className="h-4 w-4 border-gray-300 rounded cursor-pointer text-xibo-blue-600 focus:ring-xibo-blue-500"
                            />
                          </div>
                          <div className="flex justify-center">
                            <input
                              type="checkbox"
                              aria-label={t('{{feature}} inherited', { feature: feat.title })}
                              checked={inheritedFeatures.has(feat.feature)}
                              disabled
                              className="h-4 w-4 border-gray-300 rounded text-gray-500 disabled:cursor-not-allowed disabled:bg-slate-100 checked:disabled:border-gray-500 checked:disabled:bg-gray-500"
                            />
                          </div>
                        </div>
                      ))}
                  </div>
                );
              })}
            </div>
          )}
        </div>
      </div>
    </Modal>
  );
}
