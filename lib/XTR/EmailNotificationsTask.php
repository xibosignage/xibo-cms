<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (EmailNotificationsTask.php)
 */


namespace Xibo\XTR;


use Xibo\Entity\UserNotification;
use Xibo\Exception\ConfigurationException;

/**
 * Class EmailNotificationsTask
 * @package Xibo\XTR
 */
class EmailNotificationsTask implements TaskInterface
{
    use TaskTrait;

    /** @inheritdoc */
    public function run()
    {
        $this->runMessage = '# ' . __('Email Notifications') . PHP_EOL . PHP_EOL;

        $this->processQueue();
    }

    /** Process Queue of emails */
    private function processQueue()
    {
        // Handle queue of notifications to email.
        $this->runMessage .= '## ' . __('Email Notifications') . PHP_EOL;

        $msgFrom = $this->config->GetSetting('mail_from');
        $msgFromName = $this->config->GetSetting('mail_from_name');

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

                if ($msgFromName != null)
                    $mail->FromName = $msgFromName;

                $mail->Subject = $notification->subject;
                $mail->addAddress($notification->email);

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
            $notification->setEmailed($this->date->getLocalDate(null, 'U'));
            $notification->save();
        }

        $this->runMessage .= ' - Done' . PHP_EOL;
    }

    /**
     * Generate an email body
     * @param $subject
     * @param $body
     * @return string
     * @throws ConfigurationException
     */
    private function generateEmailBody($subject, $body)
    {
        // Generate Body
        // Start an object buffer
        ob_start();

        // Render the template
        $this->app->render('email-template.twig', ['config' => $this->config, 'subject' => $subject, 'body' => $body]);

        $body = ob_get_contents();

        ob_end_clean();

        return $body;
    }
}