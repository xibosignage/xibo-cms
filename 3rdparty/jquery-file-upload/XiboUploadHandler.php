<?php 

require_once("3rdparty/jquery-file-upload/UploadHandler.php");

class XiboUploadHandler extends UploadHandler {
	
	protected function handle_form_data($file, $index) {
        // Handle form data, e.g. $_REQUEST['description'][$index]
        
        // Link the file to the module
        $name = $_REQUEST['name'][$index];
        $duration = $_REQUEST['duration'][$index];

        $layoutid = Kit::GetParam('layoutid', _REQUEST, _INT);
        $regionid = Kit::GetParam('regionid', _REQUEST, _STRING);
        $type = Kit::GetParam('type', _REQUEST, _WORD);

        Debug::LogEntry('audit', 'Upload complete for Type: ' . $type . ' and file name: ' . $file->name . '. Name: ' . $name . '. Duration:' . $duration);

        // We want to create a module for each of the uploaded files.
        // Do not pass in the region ID so that we only assign to the library and not to the layout
        require_once("modules/$type.module.php");
        if (!$module = new $type($this->options['db'], $this->options['user'], '', $layoutid, '', '')) {
            $file->error = $module->GetErrorMessage();
        }

        // We want to add this item to our library
        if (!$storedAs = $module->AddLibraryMedia($file->name, $name, $duration, $file->name)) {
            $file->error = $module->GetErrorMessage();
        }

        // Set new file details
        $file->storedas = $storedAs;

        // Delete the file
        @unlink($this->get_upload_path($file->name));
    }
}