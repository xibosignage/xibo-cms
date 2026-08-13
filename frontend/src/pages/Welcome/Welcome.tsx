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

import { ArrowRight, Play } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router-dom';

import { RESOURCE_CARDS, SERVICE_CARDS, type ServiceCard, type VideoLink } from './WelcomeConfig';
import VideoModal from './components/VideoModal';

import Button from '@/components/ui/Button';
import { useUserContext } from '@/context/UserContext';
import { markWelcomeSeen } from '@/services/welcomeApi';
import { UserType } from '@/types/user';
import { hasFeature } from '@/utils/permissions';

export default function Welcome() {
  const { t } = useTranslation();
  const { user } = useUserContext();
  const [videoModalData, setVideoModalData] = useState<VideoLink[] | null>(null);

  const isSuperAdmin = user?.userTypeId === UserType.SuperAdmin;

  // Mark welcome wizard as seen on first visit
  useEffect(() => {
    if (user?.newUserWizard === 0) {
      markWelcomeSeen().catch(() => {});
    }
  }, [user?.newUserWizard]);

  const hasRequiredFeatures = (featureFlags: string[]) => {
    if (!user) return false;
    return featureFlags.some((flag) => hasFeature(user, flag));
  };

  // Filter service cards by user features
  const visibleServiceCards = SERVICE_CARDS.filter((card) =>
    hasRequiredFeatures(card.featureFlags),
  );

  // Filter resource cards (some are Xibo-branded only)
  const isXiboThemed = user?.branding?.appName === 'Xibo';
  const visibleResourceCards = RESOURCE_CARDS.filter((card) => !card.xiboOnly || isXiboThemed);

  const openVideoModal = (videos: VideoLink[]) => {
    if (videos.length > 0) {
      setVideoModalData(videos);
    }
  };

  const renderServiceCardLinks = (card: ServiceCard) => {
    return card.links
      .filter((link) => !link.xiboOnly || isXiboThemed)
      .map((link) => {
        const variant = link.variant ?? 'primary';

        if (link.isExternal) {
          return (
            <a key={link.labelKey} href={link.to} target="_blank" rel="noopener noreferrer">
              <Button variant={variant} rightIcon={variant === 'link' ? ArrowRight : undefined}>
                {t(link.labelKey)}
              </Button>
            </a>
          );
        }

        return (
          <Link key={link.to} to={link.to}>
            <Button variant={variant}>{t(link.labelKey)}</Button>
          </Link>
        );
      });
  };

  return (
    <div className="relative h-full overflow-y-auto">
      {/* Hero Section */}
      <section className="flex py-20 bg-[radial-gradient(80%_60%_at_50%_-10%,rgba(99,102,241,0.18)_0%,rgba(0,0,0,0)_100%)]">
        <div className="flex flex-col items-center max-w-234.5 mx-auto">
          <h2 className="mb-1 text-3xl font-bold text-gray-900">
            {t('Welcome to the {{productName}} CMS', {
              productName: user?.branding?.productName ?? 'Xibo',
            })}
          </h2>
          <p className="text-gray-600">
            {t(
              'The Content Management System (CMS) allows users to create, manage and update content to be shown on Displays. Upload images and videos, create layout designs, schedule content and manage the display network.',
            )}
          </p>
          <div className="flex gap-2 mt-5">
            {isXiboThemed && (
              <>
                <a
                  href="https://docs.xibosignage.com"
                  target="_blank"
                  rel="noopener noreferrer"
                  className="inline-flex items-center rounded-lg bg-xibo-blue-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-xibo-blue-700"
                >
                  {t('Documentation')}
                </a>
                <a
                  href="https://xibosignage.com/training"
                  target="_blank"
                  rel="noopener noreferrer"
                  className="inline-flex items-center rounded-lg border border-gray-300 px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                  {t('Training')}
                </a>
              </>
            )}
          </div>
        </div>
      </section>
      <section className="max-w-375 mx-auto flex flex-col gap-5 px-5">
        {/* Service Cards */}
        {visibleServiceCards.map((card) => (
          <div
            key={card.serviceId}
            id={card.serviceId}
            className="flex flex-col gap-6 rounded-lg border border-gray-200 bg-white p-5 lg:flex-row"
          >
            {/* Image with video overlay */}
            <div className="relative shrink-0 w-50">
              <div className="rounded-lg h-full overflow-hidden">
                <img
                  src={card.imageUrl}
                  alt={t(card.titleKey)}
                  className="w-full h-full object-cover scale-105"
                />
              </div>
              {card.videoLinks.length > 0 && isXiboThemed && (
                <button
                  type="button"
                  onClick={() => openVideoModal(card.videoLinks)}
                  className="absolute inset-0 flex items-center justify-center rounded-lg bg-black/30 opacity-0 transition-opacity hover:opacity-100"
                >
                  <div className="flex h-14 w-14 items-center justify-center rounded-full bg-white/90 cursor-pointer">
                    <Play className="h-6 w-6 text-xibo-blue-600" />
                  </div>
                </button>
              )}
            </div>

            {/* Text content */}
            <div className="flex flex-1 flex-col justify-center">
              <h3 className="mb-2 text-md font-bold text-gray-800">{t(card.titleKey)}</h3>
              <p className="mb-4 text-sm text-gray-800">{t(card.descriptionKey)}</p>
              <div className="flex flex-wrap gap-2">{renderServiceCardLinks(card)}</div>
            </div>
          </div>
        ))}

        {/* Resource/Others Cards */}
        <div
          className={`mb-16 grid gap-6 ${visibleResourceCards.length > 1 ? 'md:grid-cols-2 lg:grid-cols-3' : ''}`}
        >
          {visibleResourceCards.map((card) => {
            const visibleLinks = card.links.filter((link) => !link.superAdminOnly || isSuperAdmin);
            const Icon = card.icon;

            return (
              <div
                key={card.titleKey}
                className="flex flex-col rounded-lg border border-gray-200 bg-white p-5"
              >
                <div className="mb-3 flex h-11.5 w-11.5 items-center justify-center rounded-lg bg-teal-100">
                  <Icon className="h-6 w-6 text-teal-800" strokeWidth={1} />
                </div>
                <h3 className="mb-2 text-md font-bold text-gray-800">{t(card.titleKey)}</h3>
                <p className="mb-4 flex-1 text-sm text-gray-800">{t(card.descriptionKey)}</p>
                <div className="flex flex-wrap gap-4">
                  {visibleLinks.map((link) => (
                    <a key={link.url} href={link.url} target="_blank" rel="noopener noreferrer">
                      <Button variant="link" className="p-0">
                        {t(link.labelKey)}
                      </Button>
                    </a>
                  ))}
                </div>
              </div>
            );
          })}
        </div>
      </section>

      {/* Video Modal */}
      {videoModalData && (
        <VideoModal videos={videoModalData} onClose={() => setVideoModalData(null)} />
      )}
    </div>
  );
}
