<h1>DataSets</h1>
<p>The following API calls apply to DataSets.</p>
<ul>
    <li>DataSetList</li>
    <li>DataSetAdd</li>
    <li>DataSetEdit</li>
    <li>DataSetDelete</li>
    <li>DataSetColumnList</li>
    <li>DataSetColumnAdd</li>
    <li>DataSetColumnEdit</li>
    <li>DataSetColumnDelete</li>
    <li>DataSetDataList</li>
    <li>DataSetDataAdd</li>
    <li>DataSetDataEdit</li>
    <li>DataSetDataDelete</li>
    <li>DataSetSecurityList</li>
    <li>DataSetSecurityAdd</li>
    <li>DataSetSecurityDelete</li>
    <li>DataSetImportCsv</li>
</ul>

<h2>DataSetList</h2>
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


<h2>DataSetAdd</h2>
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


<h2>DataSetEdit</h2>
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


<h2>DataSetDelete</h2>
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


<h2>DataSetColumnList</h2>
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


<h2>DataSetColumnAdd</h2>
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


<h2>DataSetColumnEdit</h2>
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


<h2>DataSetColumnDelete</h2>
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


<h2>DataSetDataList</h2>
<h3>Parameters</h3>

<h3>Response</h3>

<h3>Errors</h3>
<p>General Errors Only.
</p>


<h2>DataSetDataAdd</h2>
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


<h2>DataSetDataEdit</h2>
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

<h2>DataSetDataDelete</h2>
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


<h2>DataSetSecurityList</h2>
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


<h2>DataSetSecurityAdd</h2>
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


<h2>DataSetSecurityDelete</h2>
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


<h2>DataSetImportCsv</h2>
<h3>Parameters</h3>

<h3>Response</h3>

<h3>Errors</h3>
<p>General Errors Only.
</p>

<h2>DataTypeList</h2>
<h3>Parameters</h3>
<p>None</p>

<h3>Response</h3>

<h3>Errors</h3>
<p>General Errors Only.
</p>

<h2>DataSetColumnTypeList</h2>
<h3>Parameters</h3>
<p>None</p>

<h3>Response</h3>

<h3>Errors</h3>
<p>General Errors Only.
</p>