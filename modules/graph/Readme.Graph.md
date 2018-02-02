# Xibo Graph Module 

This is a module to display Graphs on a XIBO enabled Screen.
You can use remote JSON Data or the internally used DataSets.

## Global Configuration

After installation you can configure the default used colors. In the main Modules-Section you find the Graph module.

Besides the usual configuration settings you have the Graph-Special field `Default Colors` what is for definig the colors used for the graphs. As default the six standard-Colors are used: `#00f, #0f0, #f00, #0ff, #ff0, #f0f`

## Design Settings

When you edit a Layout and Design, you find a new Media in the Regions: `Graph`
Here you can edit the folowing settings:

### General

* **Name:** This, as usual, is the regions name. Graph also uses this to show a title above the graph.
* **Graph Type:** What type of graph you are going to show. Line, Bar, Waterfall, ...
* **Background Color:** Give a Background-Color here to be used as the Graph-Widgets Background color.

### Data

* **Data-Source:** Select here even `JSON RPC-Call` for remote Data gathering or `Internal Dataset` to use a DataSet from Xibo.
* **Optional Javascript:** If you're using the internal DataSet, you can just delete this. This Javascript Function `function prepareJsonData(json, json2)` is to prepare the remotly gathered Data to the internally used format.

RGraph uses the following Data-Format:

```javascript
// If you are using only one Datastream, just use single-dimensional arrays:
var data = { data: [val, val, val, ...],
   labels: [lbl, lbl, lbl, ...],
   legend: [lbl, lbl, lbl, ...] }

// If you are using more Datastreams you have to seperate them in the data-part:
var data = { data: [[val, val, val, ...], [val, val, val, ...]],
   labels: [lbl, lbl, lbl, ...],
   legend: [lbl, lbl, lbl, ...] }
```

### JSON

* **URI:** URI to gather JSON data from
* **Method:** How to get the Data: GET, POST
* **Post Data:** Data to send when using POST as Method
* **Second URI:** I case you need to call a second URL to get more data from. You can use here all Variables from the first JSON-RPC in the form `${[0].key}` in case you got back an array but also `${param.key}` and so on.
* **Second Method:** Also here: GET or POST
* **Second Post Data:** Data to send to the Second URI. Also here you can use the templating variables to use values from the first JSON-RPC.

### Dataset

* **DataSet:** Select the Dataset you want to show on the Graph.
* **Label Column:** Select the Column in which the labels are.

The DataSet can have multiple columns. Each Solumn then is used a seperate DataStream on the graph. So if you want to visualize two bars or lines, you need two columns.
Only numbers and Dates are supported for Data-Columns. Also no formulas.

For the label column you should use Strings.

### Effect

* **Effect:** Default effects form Xibo to change from one page to an other
* **Speed:** Transition time for the page change effect.

### Advanced

* **Show legend:** Check if a Legend shall be shown.
* **Horizontally centered:** Check if the legend should be horizontally centered
* **Horizontal:** Give here a value in Pixels to define the space between the left/right border to the legend
* **From right:** If checked, the Legend is align on the right border; If not, it's aligned left
* **Vertical:** Give here a value in Pixels to define the space between the top/bottom border to the legend
* **From bottom:** If checked, the Legend is aligned on the bottom; If not, on top
* **Javascrip:** Optional Javascript-Function to map a Label-Key to a human readable value to be displayed in the Legend.
 
