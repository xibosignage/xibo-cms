
RGraph=window.RGraph||{isRGraph:true};(function(win,doc,undefined)
{RGraph.Sheets=function(key)
{var worksheet,callback,letters='ABCDEFGHIJKLMNOPQRSTUVWXYZ';if(arguments.length===3){worksheet=Number(arguments[1]);callback=arguments[2];}else{worksheet=1;callback=arguments[1];}
var url='https://spreadsheets.google.com/feeds/cells/[KEY]/[WORKSHEET]/public/full?alt=json-in-script&callback=__rgraph_JSONPCallback'.replace(/\[KEY\]/,key).replace(/\[WORKSHEET\]/,worksheet);this.load=function(url,userCallback)
{var obj=this;__rgraph_JSONPCallback=function(json)
{obj.json=json;var grid=[],row=0,col=0;for(var i=0;i<json.feed.entry.length;++i){row=json.feed.entry[i].gs$cell.row-1;col=json.feed.entry[i].gs$cell.col-1;if(!grid[row]){grid[row]=[];}
grid[row][col]=json.feed.entry[i].content.$t;}
var maxcols=0;for(var i=0;i<grid.length;++i){maxcols=grid[i]?Math.max(maxcols,grid[i].length):maxcols;}
for(var i=0;i<grid.length;++i){if(typeof grid[i]==='undefined'){grid[i]=new Array(maxcols);}
for(var j=0;j<maxcols;j++){if(typeof grid[i][j]==='undefined'){grid[i][j]='';}
if(grid[i][j].match(/^[0-9]+$/)){grid[i][j]=parseInt(grid[i][j]);}else if(grid[i][j].match(/^[0-9.]+$/)){grid[i][j]=parseFloat(grid[i][j]);}}}
obj.data=grid;userCallback(obj);};var scriptNode=document.createElement('SCRIPT');scriptNode.src=url;document.body.appendChild(scriptNode);};this.row=function(index,start)
{var opt={},row;start=start||1;if(arguments&&typeof arguments[2]==='object'&&typeof arguments[2].trim==='boolean'){opt.trim=arguments[2].trim;}else{opt.trim=true;}
row=this.data[index-1].slice(start-1);if(opt.trim){row=RGraph.SVG.arrayTrim(row);}
return row;};this.col=function(index,start)
{var opt={},col=[];start=start||1;if(arguments&&typeof arguments[2]==='object'&&typeof arguments[2].trim==='boolean'){opt.trim=arguments[2].trim;}else{opt.trim=true;}
for(var i=0;i<this.data.length;++i){col.push(this.data[i][index-1]);}
if(opt.trim){col=RGraph.SVG.arrayTrim(col);}
col=col.slice(start-1);return col;};this.getIndexOfLetters=function(l)
{var parts=l.split('');if(parts.length===1){return letters.indexOf(l)+1;}else if(parts.length===2){var idx=((letters.indexOf(parts[0])+1)*26)+(letters.indexOf(parts[1])+1);return idx;}}
this.get=function(str)
{str=str.toUpperCase();if(str.match(/^[a-z]+$/i)){if(str.length===1){var index=letters.indexOf(str)+1;return this.col(index,1,arguments[1]);}else if(str.length===2){var index=((letters.indexOf(str[0])+1)*26)+letters.indexOf(str[1])+1;return this.col(index,1,arguments[1]);}}
if(str.match(/^[0-9]+$/i)){return this.row(str,null,arguments[1]);}
if(str.match(/^([a-z]{1,2})([0-9]+)$/i)){var letter=RegExp.$1,number=RegExp.$2,col=this.get(letter,{trim:false});return col[number-1];}
if(str.match(/^([a-z]{1,2})([0-9]+):([a-z]{1,2})([0-9]+)$/i)){var letter1=RegExp.$1,number1=RegExp.$2,letter2=RegExp.$3,number2=RegExp.$4
if(letter1===letter2){var cells=[],index=this.getIndexOfLetters(letter1),col=this.col(index,null,{trim:false});for(var i=(number1-1);i<=(number2-1);++i){cells.push(col[i]);}}else if(number1===number2){var cells=[],row=this.row(number1,null,{trim:false}),index1=this.getIndexOfLetters(letter1),index2=this.getIndexOfLetters(letter2)
for(var i=(index1-1);i<=(index2-1);++i){cells.push(row[i]);}}
if(arguments[1]&&arguments[1].trim===false){}else{cells=RGraph.SVG.arrayTrim(cells);}
return cells;}};this.load(url,callback);};})(window,document);