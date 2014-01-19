
<h3> <span class="mw-headline" id="Library"> Library </span></h3>
<ul><li> LibraryMediaFileUpload
</li><li> LibraryMediaFileRevise
</li><li> LibraryMediaAdd
</li><li> LibraryMediaEdit
</li><li> LibraryMediaRetire
</li><li> LibraryMediaDownload
</li><li> LibraryMediaList
</li></ul>

<p>Transactions related to the Library
</p>
<h3> <span class="mw-headline" id="LibraryMediaFileUpload"> LibraryMediaFileUpload </span></h3>
<p>Parameters
</p>
<ul><li> FileID - Null for 1st call
</li><li> Chunk Offset
</li><li> Check Sum (MD5)
</li></ul>
<p>Response
</p>
<ul><li> FileID
</li><li> Offset (file length)
</li></ul>
<p>Errors
</p>
<ul><li> 1 - Access Denied
</li><li> 2 - Payload Checksum doesn't match provided checksum
</li><li> 3 - Unable to add File record to the Database
</li><li> 4 - Library location does not exist
</li><li> 5 - Unable to create file in the library location
</li><li> 6 - Unable to write to file in the library location
</li><li> 7 - File does not exist
</li></ul>
<h3> <span class="mw-headline" id="LibraryMediaAdd"> LibraryMediaAdd </span></h3>
<p>Parameters
</p>
<ul><li> fileId
</li><li> type (image|video|flash|ppt)
</li><li> name
</li><li> duration
</li><li> fileName (including extension)
</li></ul>
<p>Response
</p>
<ul><li> MediaID
</li></ul>
<p>Errors
</p>
<ul><li> Code 1 - Access Denied
</li><li> Code 10 - The Name cannot be longer than 100 characters
</li><li> Code 11 - You must enter a duration
</li><li> Code 12 - This user already owns media with this name
</li><li> Code 13 - Error inserting media into the database
</li><li> Code 14 - Cannot clean up after failure
</li><li> Code 15 - Cannot store file
</li><li> Code 16 - Cannot update stored file location
</li><li> Code 18 - Invalid File Extension
</li></ul>
<h3> <span class="mw-headline" id="LibraryMediaEdit"> LibraryMediaEdit </span></h3>
<p>Parameters
</p>
<ul><li> mediaId
</li><li> name
</li><li> duration
</li></ul>
<p>Response
</p>
<ul><li> success
</li></ul>
<p>Errors
</p>
<ul><li> 1 - Access Denied
</li><li> 10 - The Name cannot be longer than 100 characters
</li><li> 11 - You must enter a duration
</li><li> 12 - This user already owns media with this name
</li><li> 30 - Database failure updating media
</li></ul>
<h3> <span class="mw-headline" id="LibraryMediaFileRevise"> LibraryMediaFileRevise </span></h3>
<p>Parameters
</p>
<ul><li> mediaId
</li><li> fileId
</li><li> fileName (including extension)
</li></ul>
<p>Response
</p>
<ul><li> mediaId
</li></ul>
<p>Errors
</p>
<ul><li> 1 - Access Denied
</li><li> 13 - Error inserting media into the database
</li><li> 14 - Cannot clean up after failure
</li><li> 15 - Cannot store file
</li><li> 16 - Cannot update stored file location
</li><li> 18 - Invalid File Extension
</li><li> 31 - Unable to get information about existing media record
</li><li> 32 - Unable to update existing media record
</li></ul>
<h3> <span class="mw-headline" id="LibraryMediaRetire"> LibraryMediaRetire </span></h3>
<p>Parameters
</p>
<ul><li> mediaId
</li></ul>
<p>Response
</p>
<ul><li> success
</li></ul>
<p>Error Codes
</p>
<ul><li> 1 - Access Denied
</li><li> 19 - Error retiring media
</li></ul>
<h3> <span class="mw-headline" id="LibraryMediaDelete"> LibraryMediaDelete </span></h3>
<p>Parameters
</p>
<ul><li> mediaId
</li></ul>
<p>Response
</p>
<ul><li> Success = True
</li></ul>
<p>Error Codes
</p>
<ul><li> 1 - Access Denied
</li><li> 20 - Cannot check if media is assigned to layouts
</li><li> 21 - Media is in use
</li><li> 22 - Cannot locate stored files, unable to delete
</li><li> 23 - Database error deleting media
</li></ul>