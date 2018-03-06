
RGraph=window.RGraph||{isRGraph:true};RGraph.CSV=function(url,func)
{var RG=RGraph,ua=navigator.userAgent,ma=Math;this.url=url;this.ready=func;this.data=null;this.numrows=null;this.numcols=null;this.seperator=arguments[2]||',';this.endofline=arguments[3]||/\r?\n/;this.uid=RGraph.createUID();this.splitCSV=function(str,split)
{var arr=[];var field='';var inDoubleQuotes=false;var inSingleQuotes=false;var preserve=(typeof split==='object'&&split.preserve)?true:false;if(typeof split==='object'){if(typeof split.char==='string'){split=split.char;}else{split=',';}}
for(var i=0,len=str.length;i<len;i+=1){char=str.charAt(i);if((char==='"')&&!inDoubleQuotes){inDoubleQuotes=true;continue;}else if((char==='"')&&inDoubleQuotes){inDoubleQuotes=false;continue;}
if((char==="'")&&!inSingleQuotes){inSingleQuotes=true;continue;}else if((char==="'")&&inSingleQuotes){inSingleQuotes=false;continue;}else if(char===split&&!inDoubleQuotes&&!inSingleQuotes){arr.push(field);field='';continue;}else{field=field+char;}}
arr.push(field);if(!preserve){for(i=0,len=arr.length;i<len;i+=1){arr[i]=arr[i].trim();}}
return arr;};this.fetch=function()
{var sep=this.seperator,eol=this.endofline,obj=this;if(this.url.substring(0,3)==='id:'||this.url.substring(0,4)==='str:'){if(this.url.substring(0,3)==='id:'){var data=document.getElementById(this.url.substring(3)).innerHTML.trim();}else if(this.url.substring(0,4)==='str:'){var data=this.url.substring(4).trim();}
obj.data=data.split(eol);obj.numrows=obj.data.length;for(var i=0,len=obj.data.length;i<len;i+=1){var row=obj.splitCSV(obj.data[i],{preserve:false,char:sep});if(!obj.numcols){obj.numcols=row.length;}
for(var j=0;j<row.length;j+=1){if((/^\-?[0-9.]+$/).test(row[j])){row[j]=parseFloat(row[j]);}
obj.data[i]=row;}}
obj.ready(obj);}else{RGraph.AJAX.getString(this.url,function(data)
{data=data.replace(/(\r?\n)+$/,'');obj.data=data.split(eol);obj.numrows=obj.data.length;for(var i=0,len=obj.data.length;i<len;i+=1){var row=obj.splitCSV(obj.data[i],{preserve:false,char:sep});if(!obj.numcols){obj.numcols=row.length;}
for(var j=0;j<row.length;j+=1){if((/^\-?[0-9.]+$/).test(row[j])){row[j]=parseFloat(row[j]);}
obj.data[i]=row;}}
obj.ready(obj);});}};this.getRow=function(index)
{var row=[];var start=arguments[1]||0;for(var i=start;i<this.numcols;i+=1){row.push(this.data[index][i]);}
return row;};this.getCol=this.getColumn=function(index)
{var col=[];var start=arguments[1]||0;for(var i=start;i<this.numrows;i+=1){col.push(this.data[i][index]);}
return col;};this.fetch();};