<?php

namespace Xibo\Helper;

use Exception;
use Xibo\Exception\AccessDeniedException;
use Xibo\Exception\InvalidArgumentException;

/**
 * Class DataSetUploadHandler
 * @package Xibo\Helper
 */
class DataSetUploadHandler extends BlueImpUploadHandler
{
    /**
     * @param $file
     * @param $index
     */
    protected function handle_form_data($file, $index)
    {
        $controller = $this->options['controller'];
        /* @var \Xibo\Controller\DataSet $controller */

        // Handle form data, e.g. $_REQUEST['description'][$index]
        $fileName = $file->name;

        $controller->getLog()->debug('Upload complete for ' . $fileName . '.');

        // Upload and Save
        try {

            // Authenticate
            $controller = $this->options['controller'];
            $dataSet = $controller->getDataSetFactory()->getById($this->options['dataSetId']);

            if (!$controller->getUser()->checkEditable($dataSet))
                throw new AccessDeniedException();

            // We are allowed to edit - pull all required parameters from the request object
            $overwrite = $controller->getSanitizer()->getCheckbox('overwrite');
            $ignoreFirstRow = $controller->getSanitizer()->getCheckbox('ignorefirstrow');

            $controller->getLog()->debug('Options provided - overwrite = %d, ignore first row = %d', $overwrite, $ignoreFirstRow);

            // Enumerate over the columns in the DataSet and set a row value for each
            $spreadSheetMapping = [];

            foreach ($dataSet->getColumn() as $column) {
                /* @var \Xibo\Entity\DataSetColumn $column */
                if ($column->dataSetColumnTypeId == 1) {
                    // Has this column been provided in the mappings?

                    $spreadSheetColumn = 0;
                    if (isset($_REQUEST['csvImport_' . $column->dataSetColumnId]))
                        $spreadSheetColumn = (($index === null) ? $_REQUEST['csvImport_' . $column->dataSetColumnId] : $_REQUEST['csvImport_' . $column->dataSetColumnId][$index]);
                            
                    // If it has been left blank, then skip
                    if ($spreadSheetColumn != 0)
                        $spreadSheetMapping[($spreadSheetColumn - 1)] = $column->heading;
                }
            }

            // Delete the data?
            if ($overwrite == 1)
                $dataSet->deleteData();

            // Load the file
            ini_set('auto_detect_line_endings', true);

            $firstRow = true;
            $i = 0;

            $handle = fopen($controller->getConfig()->getSetting('LIBRARY_LOCATION') . 'temp/' . $fileName, 'r');
            while (($data = fgetcsv($handle)) !== FALSE ) {
                $i++;

                $row = [];

                // The CSV file might have headings, so ignore the first row.
                if ($firstRow) {
                    $firstRow = false;

                    if ($ignoreFirstRow == 1)
                        continue;
                }

                for ($cell = 0; $cell < count($data); $cell++) {

                    // Insert the data into the correct column
                    if (isset($spreadSheetMapping[$cell])) {
                        // Sanitize the data a bit
                        $item = $data[$cell];

                        if ($item == '')
                            $item = null;

                        $row[$spreadSheetMapping[$cell]] = $item;
                    }
                }

                try {
                    $dataSet->addRow($row);
                } catch (\PDOException $PDOException) {
                    $controller->getLog()->error('Error importing row ' . $i . '. E = ' . $PDOException->getMessage());
                    $controller->getLog()->debug($PDOException->getTraceAsString());

                    throw new InvalidArgumentException(__('Unable to import row %d', $i), 'row');
                }
            }

            // Close the file
            fclose($handle);

            // Change the auto detect setting back
            ini_set('auto_detect_line_endings', false);

            // TODO: update list content definitions

            // Save the dataSet
            $dataSet->lastDataEdit = time();
            $dataSet->save(['validate' => false, 'saveColumns' => false]);

            // Tidy up the temporary file
            @unlink($controller->getConfig()->getSetting('LIBRARY_LOCATION') . 'temp/' . $fileName);

        } catch (Exception $e) {
            $file->error = $e->getMessage();
        }
    }
}