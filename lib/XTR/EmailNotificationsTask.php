<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
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


namespace Xibo\XTR;


use Carbon\Carbon;
use Slim\Views\Twig;
use Xibo\Entity\UserNotification;
use Xibo\Factory\UserNotificationFactory;

/**
 * Class EmailNotificationsTask
 * @package Xibo\XTR
 */
class EmailNotificationsTask implements TaskInterface
{
    use TaskTrait;

    /** @var Twig */
    private $view;

    /** @var UserNotificationFactory */
    private $userNotificationFactory;

    /** @inheritdoc */
    public function setFactories($container)
    {
        $this->view = $container->get('view');
        $this->userNotificationFactory = $container->get('userNotificationFactory');
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

        $this->log->debug('Notification Queue sending from ' . $msgFrom);

        foreach ($this->userNotificationFactory->getEmailQueue() as $notification) {
            /** @var UserNotification $notification */

            $this->log->debug('Notification found: ' . $notification->notificationId);

            // System notification for the system user
            if ($notification->isSystem == 1 && $notification->userId == 0)
                $notification->email = $this->user->email;

            if ($notification->email != '') {

                $this->log->debug('Sending Notification email to ' . $notification->email);

                // Send them an email
                $mail = new \PHPMailer\PHPMailer\PHPMailer();
                $mail->CharSet = 'UTF-8';
                $mail->Encoding = 'base64';
                $mail->From = $msgFrom;

                // Add attachment
                if ($notification->filename != null) {
                    $mail->addAttachment($this->config->getSetting('LIBRARY_LOCATION'). 'attachment/' . $notification->filename, $notification->originalFileName);
                }

                if ($msgFromName != null)
                    $mail->FromName = $msgFromName;

                $mail->Subject = $notification->subject;
                $mail->addAddress($notification->email);

                $addresses = explode(',', $notification->nonusers);
                foreach ($addresses as $address) {
                    $mail->AddAddress($address);
                }

                // Body
                $mail->isHTML(true);
                $mail->AltBody = $notification->body;
                $mail->Body = $this->generateEmailBody($notification->subject, $notification->body);

                if (!$mail->send()) {
                    $this->log->error('Unable to send email notification mail to ' . $notification->email);
                    $this->runMessage .= ' - E' . PHP_EOL;
                } else {
                    $this->runMessage .= ' - A' . PHP_EOL;
                }

                $this->log->debug('Marking notification as sent');
            } else {
                $this->log->error('Discarding NotificationId ' . $notification->notificationId . ' as no email address could be resolved.');
            }

            // Mark as sent
            $notification->setEmailed(Carbon::createFromTimestamp(time())->format('U'));
            $notification->save();
        }

        $this->runMessage .= ' - Done' . PHP_EOL;
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
        $this->view->fetch('email-template.twig', ['config' => $this->config, 'subject' => $subject, 'body' => $body]);

        $body = ob_get_contents();

        ob_end_clean();

        return $body;
    }
}