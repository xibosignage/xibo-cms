<h1>DataSets</h1>
<p>The following API calls apply to DataSets.</p>
<ul>
    <li><a href="#DataSetList">DataSetList</a></li>
    <li><a href="#DataSetAdd">DataSetAdd</a></li>
    <li><a href="#DataSetEdit">DataSetEdit</a></li>
    <li><a href="#DataSetDelete">DataSetDelete</a></li>
    <li><a href="#DataSetColumnList">DataSetColumnList</a></li>
    <li><a href="#DataSetColumnAdd">DataSetColumnAdd</a></li>
    <li><a href="#DataSetColumnEdit">DataSetColumnEdit</a></li>
    <li><a href="#DataSetColumnDelete">DataSetColumnDelete</a></li>
    <li><a href="#DataSetDataList">DataSetDataList</a></li>
    <li><a href="#DataSetDataAdd">DataSetDataAdd</a></li>
    <li><a href="#DataSetDataEdit">DataSetDataEdit</a></li>
    <li><a href="#DataSetDataDelete">DataSetDataDelete</a></li>
    <li><a href="#DataSetSecurityList">DataSetSecurityList</a></li>
    <li><a href="#DataSetSecurityAdd">DataSetSecurityAdd</a></li>
    <li><a href="#DataSetSecurityDelete">DataSetSecurityDelete</a></li>
    <li><a href="#DataSetImportCsv">DataSetImportCsv</a></li>
</ul>

<h2 class="api-method-call-doc" id="DataSetList">DataSetList</h2>
<p>Get a list of DataSets that the authenticated user has view permission to see. Each DataSet will be returned with its details and a flag to indicate the permissions the user has against it.</p>

<h3>Parameters</h3>
<p>There are no parameters</p>

<h3>Response</h3>
<pre>
{
    "dataset": [
        {
            "datasetid": "1",
            "dataset": "Test",
            "description": "",
            "ownerid": "1",
            "view": 1,
            "edit": 1,
            "del": 1,
            "modifyPermissions": 1
        },
        {
            "datasetid": "2",
            "dataset": "Test2",
            "description": "",
            "ownerid": "2",
            "view": 1,
            "edit": 1,
            "del": 1,
            "modifyPermissions": 1
        }
    ],
    "status": "ok"
}
</pre>
</p>

<h3>Errors</h3>
<p>General Errors Only.
</p>


<h2 class="api-method-call-doc" id="DataSetAdd">DataSetAdd</h2>
<h3>Parameters</h3>
<dl>
    <dt>dataSet</dt>
    <dd>The Name for this DataSet. Required.</dd>
</dl>
<dl>
    <dt>description</dt>
    <dd>A description for this DataSet.</dd>
</dl>
</p>

<h3>Response</h3>
<p><pre>
{
    "dataset": {
        "id": "3"
    },
    "status": "ok"
}
</pre>
</p>

<h3>Errors</h3>
<p>General Errors Only.
</p>


<h2 class="api-method-call-doc" id="DataSetEdit">DataSetEdit</h2>
<h3>Parameters</h3>
<dl>
    <dt>dataSetId</dt>
    <dd>The ID for this DataSet. Required.</dd>
</dl>
<dl>
    <dt>dataSet</dt>
    <dd>The Name for this DataSet. Required.</dd>
</dl>
<dl>
    <dt>description</dt>
    <dd>A description for this DataSet.</dd>
</dl>

<h3>Response</h3>
<pre>
{
    "success": {
        "id": true
    },
    "status": "ok"
}
</pre>

<h3>Errors</h3>
<p>General Errors Only.
</p>


<h2 class="api-method-call-doc" id="DataSetDelete">DataSetDelete</h2>
<h3>Parameters</h3>
<dl>
    <dt>dataSetId</dt>
    <dd>The ID for this DataSet. Required.</dd>
</dl>

<h3>Response</h3>
<pre>
{
    "success": {
        "id": true
    },
    "status": "ok"
}
</pre>

<h3>Errors</h3>
<p>General Errors Only.
</p>


<h2 class="api-method-call-doc" id="DataSetColumnList">DataSetColumnList</h2>
<h3>Parameters</h3>
<dl>
    <dt>dataSetId</dt>
    <dd>The ID for this DataSet. Required.</dd>
</dl>

<h3>Response</h3>
<pre>
{
    "datasetcolumn": [
        {
            "datasetcolumnid": "3",
            "heading": "API Column 1",
            "listcontent": "",
            "columnorder": "1",
            "datatype": "String",
            "datasetcolumntype": "Value"
        }
    ],
    "status": "ok"
}
</pre>

<h3>Errors</h3>
<p>General Errors Only.
</p>


<h2 class="api-method-call-doc" id="DataSetColumnAdd">DataSetColumnAdd</h2>
<h3>Parameters</h3>
<dl>
    <dt>dataSetId</dt>
    <dd>The ID for this DataSet. Required.</dd>
</dl>
<dl>
    <dt>heading</dt>
    <dd>The Column Heading</dd>
</dl>
<dl>
    <dt>listContent</dt>
    <dd>A comma separated list to appear as a select list for data entry.</dd>
</dl>
<dl>
    <dt>columnOrder</dt>
    <dd>The order this column should appear</dd>
</dl>
<dl>
    <dt>dataTypeId</dt>
    <dd>The data type. See DataTypeList.</dd>
</dl>
<dl>
    <dt>datasetColumnTypeId</dt>
    <dd>The Column Type for this Column. Either <code>value</code> or <code>formula</code>. See DataSetColumnTypeList.</dd>
</dl>
<dl>
    <dt>formula</dt>
    <dd>A formula (in MySQL syntax) to apply to this column</dd>
</dl>

<h3>Response</h3>
<pre>
{
    "datasetcolumn": {
        "id": "3"
    },
    "status": "ok"
}
</pre>
<h3>Errors</h3>
<p>General Errors Only.
</p>


<h2 class="api-method-call-doc" id="DataSetColumnEdit">DataSetColumnEdit</h2>
<h3>Parameters</h3>
<dl>
    <dt>dataSetId</dt>
    <dd>The ID for this DataSet. Required.</dd>
</dl>
<dl>
    <dt>dataSetColumnId</dt>
    <dd>The ID for this DataSet Column. Required.</dd>
</dl>
<dl>
    <dt>heading</dt>
    <dd>The Column Heading</dd>
</dl>
<dl>
    <dt>listContent</dt>
    <dd>A comma separated list to appear as a select list for data entry.</dd>
</dl>
<dl>
    <dt>columnOrder</dt>
    <dd>The order this column should appear</dd>
</dl>
<dl>
    <dt>dataTypeId</dt>
    <dd>The data type. See DataTypeList.</dd>
</dl>
<dl>
    <dt>datasetColumnTypeId</dt>
    <dd>The Column Type for this Column. Either <code>value</code> or <code>formula</code>. See DataSetColumnTypeList.</dd>
</dl>
<dl>
    <dt>formula</dt>
    <dd>A formula (in MySQL syntax) to apply to this column</dd>
</dl>

<h3>Response</h3>
<pre>
{
    "success": {
        "id": true
    },
    "status": "ok"
}
</pre>
<h3>Errors</h3>
<p>General Errors Only.
</p>


<h2 class="api-method-call-doc" id="DataSetColumnDelete">DataSetColumnDelete</h2>
<h3>Parameters</h3>
<dl>
    <dt>dataSetId</dt>
    <dd>The ID for this DataSet. Required.</dd>
</dl>
<dl>
    <dt>dataSetColumnId</dt>
    <dd>The ID for this DataSet Column. Required.</dd>
</dl>
<h3>Response</h3>
<pre>
{
    "success": {
        "id": true
    },
    "status": "ok"
}
</pre>
<h3>Errors</h3>
<p>General Errors Only.
</p>


<h2 class="api-method-call-doc" id="DataSetDataList">DataSetDataList</h2>
<h3>Parameters</h3>
<dl>
    <dt>dataSetId</dt>
    <dd>The ID for this DataSet. Required.</dd>
</dl>

<h3>Response</h3>
<pre>
{
    "datasetdata": [
        {
            "datasetcolumnid": "1",
            "rownumber": "1",
            "value": "Row1-1"
        },
        {
            "datasetcolumnid": "1",
            "rownumber": "2",
            "value": "Row2-1"
        }
    ],
    "status": "ok"
}
</pre>

<h3>Errors</h3>
<p>General Errors Only.
</p>


<h2 class="api-method-call-doc" id="DataSetDataAdd">DataSetDataAdd</h2>
<h3>Parameters</h3>
<dl>
    <dt>dataSetId</dt>
    <dd>The ID for this DataSet. Required.</dd>
</dl>
<dl>
    <dt>dataSetColumnId</dt>
    <dd>The ID for this DataSet Column. Required.</dd>
</dl>
<dl>
    <dt>rowNumber</dt>
    <dd>The Row Number this data should be added with</dd>
</dl>
<dl>
    <dt>value</dt>
    <dd>The Value to Save in this Row/Column</dd>
</dl>
<h3>Response</h3>
<pre>
{
    "datasetdata": {
        "id": 1
    },
    "status": "ok"
}
</pre>
<h3>Errors</h3>
<p>General Errors Only.
</p>


<h2 class="api-method-call-doc" id="DataSetDataEdit">DataSetDataEdit</h2>
<h3>Parameters</h3>
<dl>
    <dt>dataSetId</dt>
    <dd>The ID for this DataSet. Required.</dd>
</dl>
<dl>
    <dt>dataSetColumnId</dt>
    <dd>The ID for this DataSet Column. Required.</dd>
</dl>
<dl>
    <dt>rowNumber</dt>
    <dd>The Row Number this data should be added with</dd>
</dl>
<dl>
    <dt>value</dt>
    <dd>The Value to Save in this Row/Column</dd>
</dl>
<h3>Response</h3>
<pre>
{
    "success": {
        "id": true
    },
    "status": "ok"
}
</pre>
<h3>Errors</h3>
<p>General Errors Only.
</p>

<h2 class="api-method-call-doc" id="DataSetDataDelete">DataSetDataDelete</h2>
<h3>Parameters</h3>
<dl>
    <dt>dataSetId</dt>
    <dd>The ID for this DataSet. Required.</dd>
</dl>
<dl>
    <dt>dataSetColumnId</dt>
    <dd>The ID for this DataSet Column. Required.</dd>
</dl>
<dl>
    <dt>rowNumber</dt>
    <dd>The Row Number this data should be added with</dd>
</dl>

<h3>Response</h3>
<pre>
{
    "success": {
        "id": true
    },
    "status": "ok"
}
</pre>
<h3>Errors</h3>
<p>General Errors Only.
</p>


<h2 class="api-method-call-doc" id="DataSetSecurityList">DataSetSecurityList</h2>
<h3>Parameters</h3>
<dl>
    <dt>dataSetId</dt>
    <dd>The ID for this DataSet. Required.</dd>
</dl>
<h3>Response</h3>
<pre>
{
    "datasetgroupsecurity": [
        {
            "groupid": "2",
            "group": "Everyone",
            "view": 0,
            "edit": "1",
            "del": 0,
            "isuserspecific": 0
        },
        {
            "groupid": "1",
            "group": "Users",
            "view": "1",
            "edit": 0,
            "del": 0,
            "isuserspecific": 0
        },
        {
            "groupid": "4",
            "group": "username",
            "view": 0,
            "edit": 0,
            "del": 0,
            "isuserspecific": "1"
        }
    ],
    "status": "ok"
}
</pre>
<h3>Errors</h3>
<p>General Errors Only.
</p>


<h2 class="api-method-call-doc" id="DataSetSecurityAdd">DataSetSecurityAdd</h2>
<h3>Parameters</h3>
<dl>
    <dt>dataSetId</dt>
    <dd>The ID for this DataSet. Required.</dd>
</dl>
<dl>
    <dt>groupId</dt>
    <dd>The ID for this Group. Required.</dd>
</dl>
<dl>
    <dt>view</dt>
    <dd>View Permissions (0 = no, 1 = yes). Required.</dd>
</dl>
<dl>
    <dt>edit</dt>
    <dd>Edit Permissions (0 = no, 1 = yes). Required.</dd>
</dl>
<dl>
    <dt>delete</dt>
    <dd>Delete Permissions (0 = no, 1 = yes). Required.</dd>
</dl>
<h3>Response</h3>
<pre>
{
    "success": {
        "id": true
    },
    "status": "ok"
}
</pre>
<h3>Errors</h3>
<p>General Errors Only.
</p>


<h2 class="api-method-call-doc" id="DataSetSecurityDelete">DataSetSecurityDelete</h2>
<h3>Parameters</h3>
<dl>
    <dt>dataSetId</dt>
    <dd>The ID for this DataSet. Required.</dd>
</dl>
<dl>
    <dt>groupId</dt>
    <dd>The ID for this Group. Required.</dd>
</dl>
<h3>Response</h3>
<pre>
{
    "success": {
        "id": true
    },
    "status": "ok"
}
</pre>
<h3>Errors</h3>
<p>General Errors Only.
</p>


<h2 class="api-method-call-doc" id="DataSetImportCsv">DataSetImportCsv</h2>
<h3>Parameters</h3>
<dl>
    <dt>dataSetId</dt>
    <dd>The ID for this DataSet. Required.</dd>
</dl>
<dl>
    <dt>fileId</dt>
    <dd>The ID of the CSV file uploaded by LibraryMediaFileUpload. Required.</dd>
</dl>
<dl>
    <dt>spreadSheetMapping</dt>
    <dd>A JSON object that represents the column mapping. <code>{"zero based column number":"dataSetColumnId"}</code>. For example: <code>{"0":"1","2":"5"}</code> would be CSV column 1, dataSetColumnId 1 and CSV column 1, dataSetColumnId 5.</dd>
</dl>
<dl>
    <dt>overwrite</dt>
    <dd>Should the DataSet be cleared first. (0 = No, 1 = Yes) Required.</dd>
</dl>
<dl>
    <dt>ignoreFirstRow</dt>
    <dd>Should the first row of the CSV file be treated as a Header and ignored. (0 = No, 1 = Yes) Required.</dd>
</dl>

<h3>Response</h3>
<pre>
{
    "success": {
        "id": true
    },
    "status": "ok"
}
</pre>

<h3>Errors</h3>
<p>General Errors Only.
</p>

<h2 class="api-method-call-doc" id="DataTypeList">DataTypeList</h2>
<h3>Parameters</h3>
<p>None</p>

<h3>Response</h3>
<pre>
{
    "datatype": [
        {
            "datatypeid": "1",
            "0": "1",
            "datatype": "String",
            "1": "String"
        },
        {
            "datatypeid": "2",
            "0": "2",
            "datatype": "Number",
            "1": "Number"
        },
        {
            "datatypeid": "3",
            "0": "3",
            "datatype": "Date",
            "1": "Date"
        }
    ],
    "status": "ok"
}
</pre>

<h3>Errors</h3>
<p>General Errors Only.
</p>

<h2 class="api-method-call-doc" id="DataSetColumnTypeList">DataSetColumnTypeList</h2>
<h3>Parameters</h3>
<p>None</p>

<h3>Response</h3>
<pre>
{
    "datasetcolumntype": [
        {
            "datasetcolumntypeid": "1",
            "0": "1",
            "datasetcolumntype": "Value",
            "1": "Value"
        },
        {
            "datasetcolumntypeid": "2",
            "0": "2",
            "datasetcolumntype": "Formula",
            "1": "Formula"
        }
    ],
    "status": "ok"
}
</pre>

<h3>Errors</h3>
<p>General Errors Only.
</p>