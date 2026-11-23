<?php
/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */

namespace Xibo\XTR;

use Carbon\Carbon;
use Slim\Views\Twig;
use Xibo\Entity\UserNotification;
use Xibo\Factory\NotificationFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Factory\UserNotificationFactory;
use Xibo\Service\LibraryAsset;
use Xibo\Service\LibraryStorageRegistryInterface;
use Xibo\Service\LibraryStorageServiceInterface;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class EmailNotificationsTask
 * @package Xibo\XTR
 */
class EmailNotificationsTask implements TaskInterface
{
    use TaskTrait;
    use NotifiesOncePerDayTrait;

    /** @var Twig */
    private $view;

    /** @var UserNotificationFactory */
    private $userNotificationFactory;

    /** @var UserGroupFactory */
    private $userGroupFactory;

    /** @var LibraryStorageRegistryInterface */
    private $libraryStorageRegistry;

    /** @var NotificationFactory */
    private $notificationFactory;

    /** @var array<string, string|null> temp copies of attachments by filename, null to send without it */
    private array $attachmentTempPaths = [];

    /** @var array<string, true> attachments storage failed to read, whose emails wait for the next run */
    private array $deferredAttachments = [];

    private bool $hasAttachmentStorageFailed = false;

    /** @inheritdoc */
    public function setFactories($container)
    {
        $this->view = $container->get('view');
        $this->userNotificationFactory = $container->get('userNotificationFactory');
        $this->userGroupFactory = $container->get('userGroupFactory');
        $this->libraryStorageRegistry = $container->get('libraryStorageRegistry');
        $this->notificationFactory = $container->get('notificationFactory');
        return $this;
    }

    /** @inheritdoc */
    public function run()
    {
        $this->runMessage = '# ' . __('Email Notifications') . PHP_EOL . PHP_EOL;

        $this->processQueue();
    }

    /** Process Queue of emails
     * @throws \PHPMailer\PHPMailer\Exception
     */
    private function processQueue()
    {
        // Handle queue of notifications to email.
        $this->runMessage .= '## ' . __('Email Notifications') . PHP_EOL;

        $msgFrom = $this->config->getSetting('mail_from');
        $msgFromName = $this->config->getSetting('mail_from_name');
        $processedNotifications = [];

        $this->log->debug('Notification Queue sending from ' . $msgFrom);

        $this->attachmentTempPaths = [];
        $this->deferredAttachments = [];
        $this->hasAttachmentStorageFailed = false;

        try {
            $this->sendQueue($msgFrom, $msgFromName, $processedNotifications);
        } finally {
            foreach ($this->attachmentTempPaths as $path) {
                if ($path !== null) {
                    $this->getAttachmentStorage()->releaseTemp($path);
                }
            }
        }

        if ($this->hasAttachmentStorageFailed) {
            $this->notifyOncePerDay(
                $this->notificationFactory,
                __('Notification emails sent without attachments'),
                __('Library storage could not be read, so some notification emails were sent without their attachment. See the CMS log for details.'), // phpcs:ignore
                'storage'
            );
        }

        $this->runMessage .= ' - Done' . PHP_EOL;
    }

    private function getAttachmentStorage(): LibraryStorageServiceInterface
    {
        return $this->libraryStorageRegistry->get(LibraryAsset::ATTACHMENT);
    }

    /**
     * A temp copy of the notification's attachment, fetched once for all its recipients, or null to send without it.
     */
    private function getAttachmentTempPath(UserNotification $notification): ?string
    {
        $filename = $notification->filename;
        if (empty($filename)) {
            return null;
        }

        if (!array_key_exists($filename, $this->attachmentTempPaths)) {
            try {
                $this->attachmentTempPaths[$filename] = $this->getAttachmentStorage()->downloadToTemp($filename);
            } catch (NotFoundException | InvalidArgumentException $exception) {
                // Missing or invalid names are sent without the attachment, as PHPMailer does locally.
                $this->log->error('Attachment ' . $filename . ' cannot be read for NotificationId '
                    . $notification->notificationId . ', sending without it. ' . $exception->getMessage());
                $this->attachmentTempPaths[$filename] = null;
            } catch (GeneralException $exception) {
                $this->attachmentTempPaths[$filename] = null;

                // Tried again on the next run before the email goes without it.
                $retry = $this->pool->getItem('email/attachment-retry/' . $notification->notificationId);
                if ($retry->isMiss()) {
                    $this->log->error('Library storage cannot read attachment ' . $filename . ' for NotificationId '
                        . $notification->notificationId . ', trying again next run. ' . $exception->getMessage());
                    $retry->set(true);
                    $retry->expiresAfter(86400);
                    $this->pool->save($retry);
                    $this->deferredAttachments[$filename] = true;
                } else {
                    $this->log->error('Library storage cannot read attachment ' . $filename . ' for NotificationId '
                        . $notification->notificationId . ', sending without it. ' . $exception->getMessage());
                    $this->hasAttachmentStorageFailed = true;
                }
            }
        }

        return $this->attachmentTempPaths[$filename];
    }

    /**
     * Sends each queued notification email.
     * @param string $msgFrom
     * @param string $msgFromName
     * @param array $processedNotifications
     * @throws \PHPMailer\PHPMailer\Exception
     */
    private function sendQueue($msgFrom, $msgFromName, array &$processedNotifications): void
    {
        // Grouped by notification, so each attachment copy is released after its last recipient.
        $queue = $this->userNotificationFactory->getEmailQueue();
        usort($queue, fn ($a, $b) => $a->notificationId <=> $b->notificationId);
        $remainingUses = array_count_values(array_filter(array_map(fn ($item) => (string)$item->filename, $queue)));
        $previous = null;

        foreach ($queue as $notification) {
            $this->releaseAttachmentAfterLastUse($previous, $remainingUses);
            $previous = $notification;

            $this->log->debug('Notification found: ' . $notification->notificationId);

            if (!empty($notification->email) || $notification->isSystem == 1 || !empty($notification->nonusers)) {
                $mail = new \PHPMailer\PHPMailer\PHPMailer();

                $this->log->debug('Sending Notification email to ' . $notification->email);

                if (!empty($notification->email) && $this->checkEmailPreferences($notification)) {
                    $mail->addAddress($notification->email);
                }

                // System notifications, add mail_to to addresses if set.
                if ($notification->isSystem == 1) {
                    // We should send the system notification to:
                    //  - all assigned users
                    //  - the mail_to (if set)
                    $mailTo = $this->config->getSetting('mail_to');

                    // Make sure we've been able to resolve an address.
                    if (empty($notification->email) && empty($mailTo)) {
                        $this->log->error('Discarding NotificationId ' . $notification->notificationId
                            . ' as no email address could be resolved.');
                        continue;
                    }

                    // if mail_to is set and is different from user email, and we did not
                    // process this notification yet (the same notificationId will be returned for each assigned user)
                    // add it to addresses
                    if ($mailTo !== $notification->email
                        && !empty($mailTo)
                        && !in_array($notification->notificationId, $processedNotifications)
                    ) {
                        $this->log->debug('Sending Notification email to mailTo ' . $mailTo);
                        $mail->addAddress($mailTo);
                    }
                }

                // Email them
                $mail->CharSet = 'UTF-8';
                $mail->Encoding = 'base64';
                $mail->From = $msgFrom;

                // Add the attachment, fetched once per notification.
                $attachmentPath = $this->getAttachmentTempPath($notification);
                if (isset($this->deferredAttachments[(string)$notification->filename])) {
                    // Left queued, so the next run sends it.
                    continue;
                }
                if ($attachmentPath !== null) {
                    $mail->addAttachment($attachmentPath, $notification->originalFileName);
                }

                if (!empty($msgFromName)) {
                    $mail->FromName = $msgFromName;
                }

                $mail->Subject = $notification->subject;

                if (!empty($notification->nonusers)) {
                    foreach (explode(',', $notification->nonusers) as $address) {
                        $address = trim($address);
                        if (!empty($address)) {
                            $mail->addAddress($address);
                        }
                    }
                }

                // Body
                $mail->isHTML(true);
                $mail->AltBody = $notification->body;
                $mail->Body = $this->generateEmailBody($notification->subject, $notification->body);

                if (!$mail->send()) {
                    $this->log->error('Unable to send email notification mail to ' . $notification->email);
                    $this->runMessage .= ' - E' . PHP_EOL;
                    $this->log->error('Unable to send email notification Error: ' . $mail->ErrorInfo);
                } else {
                    $this->runMessage .= ' - A' . PHP_EOL;
                }

                $this->log->debug('Marking notification as sent');
            } else {
                $this->log->error('Discarding NotificationId ' . $notification->notificationId
                    . ' as no email address could be resolved.');
            }

            $processedNotifications[] = $notification->notificationId;
            // Mark as sent
            $notification->setEmailed(Carbon::now()->format('U'));
            $notification->save();
        }

        $this->releaseAttachmentAfterLastUse($previous, $remainingUses);
    }

    /**
     * Releases the notification's attachment copy once no later row in the queue needs it.
     * @param array<string, int> $remainingUses
     */
    private function releaseAttachmentAfterLastUse(?UserNotification $notification, array &$remainingUses): void
    {
        $filename = (string)$notification?->filename;
        if ($filename === '' || --$remainingUses[$filename] > 0) {
            return;
        }

        $path = $this->attachmentTempPaths[$filename] ?? null;
        unset($this->attachmentTempPaths[$filename]);
        if ($path !== null) {
            $this->getAttachmentStorage()->releaseTemp($path);
        }
    }

    /**
     * Generate an email body
     * @param $subject
     * @param $body
     * @return string
     */
    private function generateEmailBody($subject, $body)
    {
        // Generate Body
        // Start an object buffer
        ob_start();

        // Render the template
        echo $this->view->fetch(
            'email-template.twig',
            ['config' => $this->config, 'subject' => $subject, 'body' => $body]
        );

        $body = ob_get_contents();

        ob_end_clean();

        return $body;
    }

    /**
     * Should we send email to this user?
     * check relevant flag for the notification type on the user group.
     * @param UserNotification $notification
     * @return bool
     */
    private function checkEmailPreferences(UserNotification $notification): bool
    {
        return $this->userGroupFactory->checkNotificationEmailPreferences(
            $notification->userId,
            $notification->getTypeForGroup()
        );
    }
}
