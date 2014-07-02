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
<p>Parameters:<br/>
There are no parameters
</p>

<p>Response:<br>
A list of DataSets. E.g.<br>
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

<p>Errors:<br>
General Errors Only.
</p>


<h2>DataSetAdd</h2>
<p>Parameters:<br>
<dl>
    <dt>dataSet</dt>
    <dd>The Name for this DataSet. Required.</dd>
</dl>
<dl>
    <dt>description</dt>
    <dd>A description for this DataSet.</dd>
</dl>
</p>

<p>Response:
<pre>
{
    "dataset": {
        "id": "3"
    },
    "status": "ok"
}
</pre>
</p>

<p>Errors:<br>
General Errors Only.
</p>


<h2>DataSetEdit</h2>
<p>Parameters:</p>
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

<p>Response:</p>
<pre>
{
    "success": {
        "id": true
    },
    "status": "ok"
}
</pre>

<p>Errors:<br>
General Errors Only.
</p>


<h2>DataSetDelete</h2>
<p>Parameters:</p>
<dl>
    <dt>dataSetId</dt>
    <dd>The ID for this DataSet. Required.</dd>
</dl>

<p>Response:</p>
<pre>
{
    "success": {
        "id": true
    },
    "status": "ok"
}
</pre>

<p>Errors:<br>
General Errors Only.
</p>


<h2>DataSetColumnList</h2>
<p>Parameters:</p>

<p>Response:</p>

<p>Errors:<br>
General Errors Only.
</p>


<h2>DataSetColumnAdd</h2>
<p>Parameters:</p>
<dl>
    <dt>dataSetId</dt>
    <dd>The ID for this DataSet. Required.</dd>
</dl>
<dl>
    <dt>heading</dt>
    <dd></dd>
</dl>
<dl>
    <dt>listContent</dt>
    <dd></dd>
</dl>
<dl>
    <dt>columnOrder</dt>
    <dd></dd>
</dl>
<dl>
    <dt>dataTypeId</dt>
    <dd></dd>
</dl>
<dl>
    <dt>datasetColumnTypeId</dt>
    <dd>The Column Type for this Column. Either <code>value</code> or <code>formula</code>.</dd>
</dl>
<dl>
    <dt>formula</dt>
    <dd>A formula (in MySQL syntax) to apply to this column</dd>
</dl>

<p>Response:</p>

<p>Errors:<br>
General Errors Only.
</p>


<h2>DataSetColumnEdit</h2>
<p>Parameters:</p>

<p>Response:</p>

<p>Errors:<br>
General Errors Only.
</p>


<h2>DataSetColumnDelete</h2>
<p>Parameters:</p>

<p>Response:</p>

<p>Errors:<br>
General Errors Only.
</p>


<h2>DataSetDataList</h2>
<p>Parameters:</p>

<p>Response:</p>

<p>Errors:<br>
General Errors Only.
</p>


<h2>DataSetDataAdd</h2>
<p>Parameters:</p>

<p>Response:</p>

<p>Errors:<br>
General Errors Only.
</p>


<h2>DataSetDataEdit</h2>
<p>Parameters:</p>

<p>Response:</p>

<p>Errors:<br>
General Errors Only.
</p>


<h2>DataSetDataDelete</h2>
<p>Parameters:</p>

<p>Response:</p>

<p>Errors:<br>
General Errors Only.
</p>


<h2>DataSetSecurityList</h2>
<p>Parameters:</p>

<p>Response:</p>

<p>Errors:<br>
General Errors Only.
</p>


<h2>DataSetSecurityAdd</h2>
<p>Parameters:</p>

<p>Response:</p>

<p>Errors:<br>
General Errors Only.
</p>


<h2>DataSetSecurityDelete</h2>
<p>Parameters:</p>

<p>Response:</p>

<p>Errors:<br>
General Errors Only.
</p>


<h2>DataSetImportCsv</h2>
<p>Parameters:</p>

<p>Response:</p>

<p>Errors:<br>
General Errors Only.
</p>

