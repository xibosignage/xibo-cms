<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2013 Daniel Garner
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
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");
?>
<div id="mw-content-text" lang="en" dir="ltr" class="mw-content-ltr"><p>Xibo uses database constraints to enforce it's database schema. You can think of it as a second level of checking after the web interface to ensure that the database remains corruption-free.
</p><p>What this means for you, the Xibo admin, is that when you try and restore a mysqldump'ed database the restore will fail.
</p><p>I'll use the following names for things throughout the examples. Substitute these values for your real database details:
</p>
<ul><li> MySQL Administrative User = madmin
</li><li> MySQL Xibo User = xibodbuser
</li><li> MySQL Database = xibodb
</li></ul>
<h2> <span class="mw-headline" id="Method_1"> Method 1 </span></h2>
<p>The easier of the two methods.
</p>
<ul><li> Dump the 1.0.x database to a file:
</li></ul>
<pre> mysqldump -u madmin -p xibodb &gt; xibo.sql
</pre>
<ul><li> If you now want to clone that database (for testing or similar) then first you need to create a new database to restore the dump in to:
</li></ul>
<pre>mysql -u madmin -p mysql
</pre>
<ul><li> You'll be prompted to enter your madmin password. At the mysql prompt, enter:
</li></ul>
<pre>create database xibodb2;
grant all privileges on xibodb2.* to 'xibodbuser'@'localhost';
use xibodb2;
source xibo.sql
quit;
</pre>
<p>Substitute xibodb2 for any database name of your choosing (although clearly it can't exist already!).
</p>
<ul><li> Finally alter your settings.php file in your 1.1 install to use the new database name (xibodb2 in this example).
</li></ul>
<h2> <span class="mw-headline" id="Method_2"> Method 2 </span></h2>
<p>Before you backup your database, create a file "xibo.sql" with the following contents:
</p>
<pre>SET FOREIGN_KEY_CHECKS=0;
SET UNIQUE_CHECKS=0;

</pre>
<p>It's really important that there is at least one blank line on the end of the file.
</p><p>Next you dump your Xibo database and add the output to the end of xibo.sql
</p>
<pre>mysqldump -u xibodbuser -p xibodb &gt;&gt; xibo.sql
</pre>
<p>You will be prompted to enter the password for your xibodbuser account. You could replace xibodbuser with your madmin credentials instead. xibo.sql should now contain a dump of your working Xibo database.
</p><p>If you now want to clone that database (for testing or similar) then first you need to create a new database to restore the dump in to:
</p>
<pre>mysql -u madmin -p mysql
</pre>
<p>You'll be prompted to enter your madmin password. At the mysql prompt, enter:
</p>
<pre>create database xibodb2;
grant all privileges on xibodb2.* to 'xibodbuser'@'localhost';
quit;
</pre>
<p>Substitute xibodb2 for any database name of your choosing (although clearly it can't exist already!).
</p><p>Now we can restore our database dump in to the new database:
</p>
<pre>mysql -u madmin -p xibodb2 &lt; xibo.sql
</pre>
<p>That should give you a clone of your Xibo database. You can now modify your settings.php file to use the new database name.
</p>