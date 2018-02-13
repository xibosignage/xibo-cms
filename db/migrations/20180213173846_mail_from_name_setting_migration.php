<?php


use Phinx\Migration\AbstractMigration;

class MailFromNameSettingMigration extends AbstractMigration
{
    /** @inheritdoc */
    public function up()
    {
        // Check to see if the mail_from_name setting exists
        if (!$this->fetchRow('SELECT * FROM `setting` WHERE setting = \'mail_from_name\'')) {
            $this->execute('INSERT INTO setting (setting, value, fieldType, helptext, options, cat, userChange, title, validation, ordering, `default`, userSee, type) VALUES (\'mail_from_name\', \'\', \'text\', \'Mail will be sent under this name\', NULL, \'maintenance\', 1, \'Sending email name\', \'\', 45, \'\', 1, \'string\');');
        }
    }
}
