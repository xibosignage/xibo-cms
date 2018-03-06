
RGraph=window.RGraph||{isRGraph:true};(function(win,doc,undefined)
{var RG=RGraph,ua=navigator.userAgent,ma=Math;RG.Highlight={};RG.Registry={};RG.Registry.store=[];RG.Registry.store['chart.event.handlers']=[];RG.Registry.store['__rgraph_event_listeners__']=[];RG.Background={};RG.background={};RG.objects=[];RG.Resizing={};RG.events=[];RG.cursor=[];RG.Effects=RG.Effects||{};RG.cache=[];RG.ObjectRegistry={};RG.ObjectRegistry.objects={};RG.ObjectRegistry.objects.byUID=[];RG.ObjectRegistry.objects.byCanvasID=[];RG.OR=RG.ObjectRegistry;RG.PI=ma.PI;RG.HALFPI=RG.PI/2;RG.TWOPI=RG.PI*2;RG.ISFF=ua.indexOf('Firefox')!=-1;RG.ISOPERA=ua.indexOf('Opera')!=-1;RG.ISCHROME=ua.indexOf('Chrome')!=-1;RG.ISSAFARI=ua.indexOf('Safari')!=-1&&!RG.ISCHROME;RG.ISWEBKIT=ua.indexOf('WebKit')!=-1;RG.ISIE=ua.indexOf('Trident')>0||navigator.userAgent.indexOf('MSIE')>0;RG.ISIE6=ua.indexOf('MSIE 6')>0;RG.ISIE7=ua.indexOf('MSIE 7')>0;RG.ISIE8=ua.indexOf('MSIE 8')>0;RG.ISIE9=ua.indexOf('MSIE 9')>0;RG.ISIE10=ua.indexOf('MSIE 10')>0;RG.ISOLD=RGraph.ISIE6||RGraph.ISIE7||RGraph.ISIE8;RG.ISIE11UP=ua.indexOf('MSIE')==-1&&ua.indexOf('Trident')>0;RG.ISIE10UP=RG.ISIE10||RG.ISIE11UP;RG.ISIE9UP=RG.ISIE9||RG.ISIE10UP;RG.getScale=function(max,obj)
{if(max==0){return['0.2','0.4','0.6','0.8','1.0'];}
var original_max=max;if(max<=1){if(max>0.5){return[0.2,0.4,0.6,0.8,Number(1).toFixed(1)];}else if(max>=0.1){return obj.Get('chart.scale.round')?[0.2,0.4,0.6,0.8,1]:[0.1,0.2,0.3,0.4,0.5];}else{var tmp=max;var exp=0;while(tmp<1.01){exp+=1;tmp*=10;}
var ret=['2e-'+exp,'4e-'+exp,'6e-'+exp,'8e-'+exp,'10e-'+exp];if(max<=('5e-'+exp)){ret=['1e-'+exp,'2e-'+exp,'3e-'+exp,'4e-'+exp,'5e-'+exp];}
return ret;}}
if(String(max).indexOf('.')>0){max=String(max).replace(/\.\d+$/,'');}
var interval=ma.pow(10,Number(String(Number(max)).length-1));var topValue=interval;while(topValue<max){topValue+=(interval/2);}
if(Number(original_max)>Number(topValue)){topValue+=(interval/2);}
if(max<10){topValue=(Number(original_max)<=5?5:10);}
if(obj&&typeof(obj.Get('chart.scale.round'))=='boolean'&&obj.Get('chart.scale.round')){topValue=10*interval;}
return[topValue*0.2,topValue*0.4,topValue*0.6,topValue*0.8,topValue];};RG.getScale2=function(obj,opt)
{var ca=obj.canvas,co=obj.context,prop=obj.properties,numlabels=typeof opt['ylabels.count']=='number'?opt['ylabels.count']:5,units_pre=typeof opt['units.pre']=='string'?opt['units.pre']:'',units_post=typeof opt['units.post']=='string'?opt['units.post']:'',max=Number(opt['max']),min=typeof opt['min']=='number'?opt['min']:0,strict=opt['strict'],decimals=Number(opt['scale.decimals']),point=opt['scale.point'],thousand=opt['scale.thousand'],original_max=max,round=opt['scale.round'],scale={max:1,labels:[],values:[]}
if(!max){var max=1;for(var i=0;i<numlabels;++i){var label=((((max-min)/numlabels)+min)*(i+1)).toFixed(decimals);scale.labels.push(units_pre+label+units_post);scale.values.push(parseFloat(label))}}else if(max<=1&&!strict){var arr=[1,0.5,0.10,0.05,0.010,0.005,0.0010,0.0005,0.00010,0.00005,0.000010,0.000005,0.0000010,0.0000005,0.00000010,0.00000005,0.000000010,0.000000005,0.0000000010,0.0000000005,0.00000000010,0.00000000005,0.000000000010,0.000000000005,0.0000000000010,0.0000000000005],vals=[];for(var i=0;i<arr.length;++i){if(max>arr[i]){i--;break;}}
scale.max=arr[i]
scale.labels=[];scale.values=[];for(var j=0;j<numlabels;++j){var value=((((arr[i]-min)/numlabels)*(j+1))+min).toFixed(decimals);scale.values.push(value);scale.labels.push(RG.numberFormat(obj,value,units_pre,units_post));}}else if(!strict){max=ma.ceil(max);var interval=ma.pow(10,ma.max(1,Number(String(Number(max)-Number(min)).length-1)));var topValue=interval;while(topValue<max){topValue+=(interval/2);}
if(Number(original_max)>Number(topValue)){topValue+=(interval/2);}
if(max<=10){topValue=(Number(original_max)<=5?5:10);}
if(obj&&typeof(round)=='boolean'&&round){topValue=10*interval;}
scale.max=topValue;var tmp_point=prop['chart.scale.point'];var tmp_thousand=prop['chart.scale.thousand'];obj.Set('chart.scale.thousand',thousand);obj.Set('chart.scale.point',point);for(var i=0;i<numlabels;++i){scale.labels.push(RG.number_format(obj,((((i+1)/numlabels)*(topValue-min))+min).toFixed(decimals),units_pre,units_post));scale.values.push(((((i+1)/numlabels)*(topValue-min))+min).toFixed(decimals));}
obj.Set('chart.scale.thousand',tmp_thousand);obj.Set('chart.scale.point',tmp_point);}else if(typeof(max)=='number'&&strict){for(var i=0;i<numlabels;++i){scale.labels.push(RG.numberFormat(obj,((((i+1)/numlabels)*(max-min))+min).toFixed(decimals),units_pre,units_post));scale.values.push(((((i+1)/numlabels)*(max-min))+min).toFixed(decimals));}
scale.max=max;}
scale.units_pre=units_pre;scale.units_post=units_post;scale.point=point;scale.decimals=decimals;scale.thousand=thousand;scale.numlabels=numlabels;scale.round=Boolean(round);scale.min=min;for(var i=0;i<scale.values.length;++i){scale.values[i]=parseFloat(scale.values[i]);}
return scale;};RG.arrayInvert=function(arr)
{for(var i=0,len=arr.length;i<len;++i){arr[i]=!arr[i];}
return arr;};RG.arrayTrim=function(arr)
{var out=[],content=false;for(var i=0;i<arr.length;i++){if(arr[i]){content=true;}
if(content){out.push(arr[i]);}}
out=RG.arrayReverse(out);var out2=[],content=false;for(var i=0;i<out.length;i++){if(out[i]){content=true;}
if(content){out2.push(out[i]);}}
out2=RG.arrayReverse(out2);return out2;};RG.arrayClone=RG.array_clone=function(obj)
{if(obj===null||typeof obj!=='object'){return obj;}
var temp=[];for(var i=0,len=obj.length;i<len;++i){if(typeof obj[i]==='number'){temp[i]=(function(arg){return Number(arg);})(obj[i]);}else if(typeof obj[i]==='string'){temp[i]=(function(arg){return String(arg);})(obj[i]);}else if(typeof obj[i]==='function'){temp[i]=obj[i];}else{temp[i]=RG.arrayClone(obj[i]);}}
return temp;};RG.arrayMax=RG.array_max=function(arr)
{var max=null,ma=Math
if(typeof arr==='number'){return arr;}
if(RG.isNull(arr)){return 0;}
for(var i=0,len=arr.length;i<len;++i){if(typeof arr[i]==='number'&&!isNaN(arr[i])){var val=arguments[1]?ma.abs(arr[i]):arr[i];if(typeof max==='number'){max=ma.max(max,val);}else{max=val;}}}
return max;};RG.arrayMin=function(arr)
{var max=null,min=null,ma=Math;if(typeof arr==='number'){return arr;}
if(RG.isNull(arr)){return 0;}
for(var i=0,len=arr.length;i<len;++i){if(typeof arr[i]==='number'){var val=arguments[1]?ma.abs(arr[i]):arr[i];if(typeof min==='number'){min=ma.min(min,val);}else{min=val;}}}
return min;};RG.arrayPad=RG.array_pad=function(arr,len)
{if(arr.length<len){var val=arguments[2]?arguments[2]:null;for(var i=arr.length;i<len;i+=1){arr[i]=val;}}
return arr;};RG.arraySum=RG.array_sum=function(arr)
{if(typeof arr==='number'){return arr;}
if(RG.isNull(arr)){return 0;}
var i,sum,len=arr.length;for(i=0,sum=0;i<len;sum+=(arr[i++]||0));return sum;};RG.arrayLinearize=RG.array_linearize=function()
{var arr=[],args=arguments
for(var i=0,len=args.length;i<len;++i){if(typeof args[i]==='object'&&args[i]){for(var j=0,len2=args[i].length;j<len2;++j){var sub=RG.array_linearize(args[i][j]);for(var k=0,len3=sub.length;k<len3;++k){arr.push(sub[k]);}}}else{arr.push(args[i]);}}
return arr;};RG.arrayShift=RG.array_shift=function(arr)
{var ret=[];for(var i=1,len=arr.length;i<len;++i){ret.push(arr[i]);}
return ret;};RG.arrayReverse=RG.array_reverse=function(arr)
{if(!arr){return;}
var newarr=[];for(var i=arr.length-1;i>=0;i-=1){newarr.push(arr[i]);}
return newarr;};RG.abs=function(value)
{if(typeof value==='string'){value=parseFloat(value)||0;}
if(typeof value==='number'){return ma.abs(value);}
if(typeof value==='object'){for(i in value){if(typeof i==='string'||typeof i==='number'||typeof i==='object'){value[i]=RG.abs(value[i]);}}
return value;}
return 0;};RG.clear=RG.Clear=function(ca)
{var obj=ca.__object__,co=ca.getContext('2d'),color=arguments[1]||(obj&&obj.get('clearto'))
if(!ca){return;}
RG.fireCustomEvent(obj,'onbeforeclear');if(RG.text2.domNodeCache&&RG.text2.domNodeCache[ca.id]){for(var i in RG.text2.domNodeCache[ca.id]){var el=RG.text2.domNodeCache[ca.id][i];if(el&&el.style){el.style.display='none';}}}
if(!color||(color&&color==='rgba(0,0,0,0)'||color==='transparent')){co.clearRect(-100,-100,ca.width+200,ca.height+200);co.globalCompositeOperation='source-over';}else if(color){RG.path2(co,'fs % fr -100 -100 % %',color,ca.width+200,ca.height+200);}else{RG.path2(co,'fs % fr -100 -100 % %',obj.get('clearto'),ca.width+200,ca.height+200);}
if(RG.Registry.Get('chart.background.image.'+ca.id)){var img=RG.Registry.Get('chart.background.image.'+ca.id);img.style.position='absolute';img.style.left='-10000px';img.style.top='-10000px';}
if(RG.Registry.Get('chart.tooltip')&&obj&&!obj.get('chart.tooltips.nohideonclear')){RG.HideTooltip(ca);}
ca.style.cursor='default';RG.FireCustomEvent(obj,'onclear');};RG.drawTitle=RG.DrawTitle=function(obj,text,gutterTop)
{var ca=canvas=obj.canvas,co=context=obj.context,prop=obj.properties
gutterLeft=prop['chart.gutter.left'],gutterRight=prop['chart.gutter.right'],gutterTop=gutterTop,gutterBottom=prop['chart.gutter.bottom'],size=arguments[4]?arguments[4]:12,bold=prop['chart.title.bold'],italic=prop['chart.title.italic'],centerx=(arguments[3]?arguments[3]:((ca.width-gutterLeft-gutterRight)/2)+gutterLeft),keypos=prop['chart.key.position'],vpos=prop['chart.title.vpos'],hpos=prop['chart.title.hpos'],bgcolor=prop['chart.title.background'],x=prop['chart.title.x'],y=prop['chart.title.y'],halign='center',valign='center'
if(obj.type=='bar'&&prop['chart.variant']=='3d'){keypos='gutter';}
co.beginPath();co.fillStyle=prop['chart.text.color']?prop['chart.text.color']:'black';if(keypos&&keypos!='gutter'){var valign='center';}else if(!keypos){var valign='center';}else{var valign='bottom';}
if(typeof prop['chart.title.vpos']==='number'){vpos=prop['chart.title.vpos']*gutterTop;if(prop['chart.xaxispos']==='top'){vpos=prop['chart.title.vpos']*gutterBottom+gutterTop+(ca.height-gutterTop-gutterBottom);}}else{vpos=gutterTop-size-5;if(prop['chart.xaxispos']==='top'){vpos=ca.height-gutterBottom+size+5;}}
if(typeof hpos==='number'){centerx=hpos*ca.width;}
if(typeof x==='number')centerx=x;if(typeof y==='number')vpos=y;if(typeof prop['chart.title.halign']==='string'){halign=prop['chart.title.halign'];}
if(typeof prop['chart.title.valign']==='string'){valign=prop['chart.title.valign'];}
if(typeof prop['chart.title.color']!==null){var oldColor=co.fillStyle
var newColor=prop['chart.title.color'];co.fillStyle=newColor?newColor:'black';}
var font=prop['chart.text.font'];if(typeof prop['chart.title.font']==='string'){font=prop['chart.title.font'];}
var ret=RG.text2(obj,{font:font,size:size,x:centerx,y:vpos,text:text,valign:valign,halign:halign,bounding:bgcolor!=null,'bounding.fill':bgcolor,'bold':bold,italic:italic,tag:'title',marker:false});co.fillStyle=oldColor;};RG.getMouseXY=function(e)
{if(!e.target){return;}
var el=e.target;var ca=el;var caStyle=ca.style;var offsetX=0;var offsetY=0;var x;var y;var borderLeft=parseInt(caStyle.borderLeftWidth)||0;var borderTop=parseInt(caStyle.borderTopWidth)||0;var paddingLeft=parseInt(caStyle.paddingLeft)||0
var paddingTop=parseInt(caStyle.paddingTop)||0
var additionalX=borderLeft+paddingLeft;var additionalY=borderTop+paddingTop;if(typeof e.offsetX==='number'&&typeof e.offsetY==='number'){if(!RG.ISIE&&!RG.ISOPERA){x=e.offsetX-borderLeft-paddingLeft;y=e.offsetY-borderTop-paddingTop;}else if(RG.ISIE){x=e.offsetX-paddingLeft;y=e.offsetY-paddingTop;}else{x=e.offsetX;y=e.offsetY;}}else{if(typeof el.offsetParent!=='undefined'){do{offsetX+=el.offsetLeft;offsetY+=el.offsetTop;}while((el=el.offsetParent));}
x=e.pageX-offsetX-additionalX;y=e.pageY-offsetY-additionalY;x-=(2*(parseInt(document.body.style.borderLeftWidth)||0));y-=(2*(parseInt(document.body.style.borderTopWidth)||0));}
return[x,y];};RG.getCanvasXY=function(canvas)
{var x=0;var y=0;var el=canvas;do{x+=el.offsetLeft;y+=el.offsetTop;if(el.tagName.toLowerCase()=='table'&&(RG.ISCHROME||RG.ISSAFARI)){x+=parseInt(el.border)||0;y+=parseInt(el.border)||0;}
el=el.offsetParent;}while(el&&el.tagName.toLowerCase()!='body');var paddingLeft=canvas.style.paddingLeft?parseInt(canvas.style.paddingLeft):0;var paddingTop=canvas.style.paddingTop?parseInt(canvas.style.paddingTop):0;var borderLeft=canvas.style.borderLeftWidth?parseInt(canvas.style.borderLeftWidth):0;var borderTop=canvas.style.borderTopWidth?parseInt(canvas.style.borderTopWidth):0;if(navigator.userAgent.indexOf('Firefox')>0){x+=parseInt(document.body.style.borderLeftWidth)||0;y+=parseInt(document.body.style.borderTopWidth)||0;}
return[x+paddingLeft+borderLeft,y+paddingTop+borderTop];};RG.isFixed=function(canvas)
{var obj=canvas;var i=0;while(obj&&obj.tagName.toLowerCase()!='body'&&i<99){if(obj.style.position=='fixed'){return obj;}
obj=obj.offsetParent;}
return false;};RG.register=RG.Register=function(obj)
{if(!obj.Get('chart.noregister')){RGraph.ObjectRegistry.Add(obj);obj.Set('chart.noregister',true);}};RG.redraw=RG.Redraw=function()
{var objectRegistry=RGraph.ObjectRegistry.objects.byCanvasID;var tags=document.getElementsByTagName('canvas');for(var i=0,len=tags.length;i<len;++i){if(tags[i].__object__&&tags[i].__object__.isRGraph){if(!tags[i].noclear){RGraph.clear(tags[i],arguments[0]?arguments[0]:null);}}}
for(var i=0,len=objectRegistry.length;i<len;++i){if(objectRegistry[i]){var id=objectRegistry[i][0];objectRegistry[i][1].Draw();}}};RG.redrawCanvas=RG.RedrawCanvas=function(ca)
{var objects=RG.ObjectRegistry.getObjectsByCanvasID(ca.id);if(!arguments[1]||(typeof arguments[1]==='boolean'&&!arguments[1]==false)){var color=arguments[2]||ca.__object__.get('clearto')||'transparent';RG.clear(ca,color);}
for(var i=0,len=objects.length;i<len;++i){if(objects[i]){if(objects[i]&&objects[i].isRGraph){objects[i].Draw();}}}};RG.Background.draw=RG.background.draw=RG.background.Draw=function(obj)
{var ca=obj.canvas,co=obj.context,prop=obj.properties,height=0,gutterLeft=obj.gutterLeft,gutterRight=obj.gutterRight,gutterTop=obj.gutterTop,gutterBottom=obj.gutterBottom,variant=prop['chart.variant']
co.fillStyle=prop['chart.text.color'];if(variant=='3d'){co.save();co.translate(prop['chart.variant.threed.offsetx'],-1*prop['chart.variant.threed.offsety']);}
if(typeof prop['chart.title.xaxis']==='string'&&prop['chart.title.xaxis'].length){var size=prop['chart.text.size']+2;var font=prop['chart.text.font'];var bold=prop['chart.title.xaxis.bold'];if(typeof(prop['chart.title.xaxis.size'])=='number'){size=prop['chart.title.xaxis.size'];}
if(typeof(prop['chart.title.xaxis.font'])=='string'){font=prop['chart.title.xaxis.font'];}
var hpos=((ca.width-gutterLeft-gutterRight)/2)+gutterLeft;var vpos=ca.height-gutterBottom+25;if(typeof prop['chart.title.xaxis.pos']==='number'){vpos=ca.height-(gutterBottom*prop['chart.title.xaxis.pos']);}
if(typeof prop['chart.title.xaxis.x']==='number'){hpos=prop['chart.title.xaxis.x'];}
if(typeof prop['chart.title.xaxis.y']==='number'){vpos=prop['chart.title.xaxis.y'];}
RG.text2(prop['chart.text.accessible']?obj.context:co,{font:font,size:size,x:hpos,y:vpos,text:prop['chart.title.xaxis'],halign:'center',valign:'center',bold:bold,color:prop['chart.title.xaxis.color']||'black',tag:'title xaxis'});}
if(typeof(prop['chart.title.yaxis'])=='string'&&prop['chart.title.yaxis'].length){var size=prop['chart.text.size']+2;var font=prop['chart.text.font'];var angle=270;var bold=prop['chart.title.yaxis.bold'];var color=prop['chart.title.yaxis.color'];if(typeof(prop['chart.title.yaxis.pos'])=='number'){var yaxis_title_pos=prop['chart.title.yaxis.pos']*gutterLeft;}else{var yaxis_title_pos=((gutterLeft-25)/gutterLeft)*gutterLeft;}
if(typeof prop['chart.title.yaxis.size']==='number'){size=prop['chart.title.yaxis.size'];}
if(typeof prop['chart.title.yaxis.font']==='string'){font=prop['chart.title.yaxis.font'];}
if(prop['chart.title.yaxis.align']=='right'||prop['chart.title.yaxis.position']=='right'||(obj.type==='hbar'&&prop['chart.yaxispos']==='right'&&typeof prop['chart.title.yaxis.align']==='undefined'&&typeof prop['chart.title.yaxis.position']==='undefined')){angle=90;yaxis_title_pos=prop['chart.title.yaxis.pos']?(ca.width-gutterRight)+(prop['chart.title.yaxis.pos']*gutterRight):ca.width-gutterRight+prop['chart.text.size']+5;}else{yaxis_title_pos=yaxis_title_pos;}
var y=((ca.height-gutterTop-gutterBottom)/2)+gutterTop;if(typeof prop['chart.title.yaxis.x']==='number'){yaxis_title_pos=prop['chart.title.yaxis.x'];}
if(typeof prop['chart.title.yaxis.y']==='number'){y=prop['chart.title.yaxis.y'];}
co.fillStyle=color;RG.text2(prop['chart.text.accessible']?obj.context:co,{'font':font,'size':size,'x':yaxis_title_pos,'y':y,'valign':'center','halign':'center','angle':angle,'bold':bold,'text':prop['chart.title.yaxis'],'tag':'title yaxis',accessible:false});}
var bgcolor=prop['chart.background.color'];if(bgcolor){co.fillStyle=bgcolor;co.fillRect(gutterLeft+0.5,gutterTop+0.5,ca.width-gutterLeft-gutterRight,ca.height-gutterTop-gutterBottom);}
var numbars=(prop['chart.ylabels.count']||5);var barHeight=(ca.height-gutterBottom-gutterTop)/numbars;co.beginPath();co.fillStyle=prop['chart.background.barcolor1'];co.strokeStyle=co.fillStyle;height=(ca.height-gutterBottom);for(var i=0;i<numbars;i+=2){co.rect(gutterLeft,(i*barHeight)+gutterTop,ca.width-gutterLeft-gutterRight,barHeight);}
co.fill();co.beginPath();co.fillStyle=prop['chart.background.barcolor2'];co.strokeStyle=co.fillStyle;for(var i=1;i<numbars;i+=2){co.rect(gutterLeft,(i*barHeight)+gutterTop,ca.width-gutterLeft-gutterRight,barHeight);}
co.fill();co.beginPath();var func=function(obj,cacheCanvas,cacheContext)
{if(prop['chart.background.grid']){prop['chart.background.grid.autofit.numhlines']+=0.0001;if(prop['chart.background.grid.autofit']){if(prop['chart.background.grid.autofit.align']){if(obj.type==='hbar'){obj.set('chart.background.grid.autofit.numhlines',obj.data.length);}
if(obj.type==='line'){if(typeof prop['chart.background.grid.autofit.numvlines']==='number'){}else if(prop['chart.labels']&&prop['chart.labels'].length){obj.Set('chart.background.grid.autofit.numvlines',prop['chart.labels'].length-1);}else{obj.Set('chart.background.grid.autofit.numvlines',obj.data[0].length-1);}}else if(obj.type==='waterfall'){obj.set('backgroundGridAutofitNumvlines',obj.data.length+(prop['chart.total']?1:0));}else if((obj.type==='bar'||obj.type==='scatter')&&((prop['chart.labels']&&prop['chart.labels'].length)||obj.type==='bar')){var len=(prop['chart.labels']&&prop['chart.labels'].length)||obj.data.length;obj.set({backgroundGridAutofitNumvlines:len});}else if(obj.type==='gantt'){if(typeof obj.get('chart.background.grid.autofit.numvlines')==='number'){}else{obj.set('chart.background.grid.autofit.numvlines',prop['chart.xmax']);}
obj.set('chart.background.grid.autofit.numhlines',obj.data.length);}else if(obj.type==='hbar'&&RG.isNull(prop['chart.background.grid.autofit.numhlines'])){obj.set('chart.background.grid.autofit.numhlines',obj.data.length);}}
var vsize=((cacheCanvas.width-gutterLeft-gutterRight))/prop['chart.background.grid.autofit.numvlines'];var hsize=(cacheCanvas.height-gutterTop-gutterBottom)/prop['chart.background.grid.autofit.numhlines'];obj.Set('chart.background.grid.vsize',vsize);obj.Set('chart.background.grid.hsize',hsize);}
co.beginPath();cacheContext.lineWidth=prop['chart.background.grid.width']?prop['chart.background.grid.width']:1;cacheContext.strokeStyle=prop['chart.background.grid.color'];if(prop['chart.background.grid.dashed']&&typeof cacheContext.setLineDash=='function'){cacheContext.setLineDash([3,5]);}
if(prop['chart.background.grid.dotted']&&typeof cacheContext.setLineDash=='function'){cacheContext.setLineDash([1,3]);}
co.beginPath();if(prop['chart.background.grid.hlines']){height=(cacheCanvas.height-gutterBottom)
var hsize=prop['chart.background.grid.hsize'];for(y=gutterTop;y<=height;y+=hsize){cacheContext.moveTo(gutterLeft,ma.round(y));cacheContext.lineTo(ca.width-gutterRight,ma.round(y));}}
if(prop['chart.background.grid.vlines']){var width=(cacheCanvas.width-gutterRight);var vsize=prop['chart.background.grid.vsize'];for(x=gutterLeft;ma.round(x)<=width;x+=vsize){cacheContext.moveTo(ma.round(x),gutterTop);cacheContext.lineTo(ma.round(x),ca.height-gutterBottom);}}
if(prop['chart.background.grid.border']){cacheContext.strokeStyle=prop['chart.background.grid.color'];cacheContext.strokeRect(ma.round(gutterLeft),ma.round(gutterTop),ca.width-gutterLeft-gutterRight,ca.height-gutterTop-gutterBottom);}}
cacheContext.stroke();cacheContext.beginPath();cacheContext.closePath();}
RG.cachedDraw(obj,obj.uid+'_background',func);if(variant=='3d'){co.restore();}
if(typeof co.setLineDash=='function'){co.setLineDash([1,0]);}
co.stroke();if(typeof(obj.properties['chart.title'])=='string'){var prop=obj.properties;RG.drawTitle(obj,prop['chart.title'],obj.gutterTop,null,prop['chart.title.size']?prop['chart.title.size']:prop['chart.text.size']+2,obj);}};RG.numberFormat=RG.number_format=function(obj,num)
{var ca=obj.canvas;var co=obj.context;var prop=obj.properties;var i;var prepend=arguments[2]?String(arguments[2]):'';var append=arguments[3]?String(arguments[3]):'';var output='';var decimal='';var decimal_seperator=typeof prop['chart.scale.point']=='string'?prop['chart.scale.point']:'.';var thousand_seperator=typeof prop['chart.scale.thousand']=='string'?prop['chart.scale.thousand']:',';RegExp.$1='';var i,j;if(typeof prop['chart.scale.formatter']==='function'){return prop['chart.scale.formatter'](obj,num);}
if(String(num).indexOf('e')>0){return String(prepend+String(num)+append);}
num=String(num);if(num.indexOf('.')>0){var tmp=num;num=num.replace(/\.(.*)/,'');decimal=tmp.replace(/(.*)\.(.*)/,'$2');}
var seperator=thousand_seperator;var foundPoint;for(i=(num.length-1),j=0;i>=0;j++,i--){var character=num.charAt(i);if(j%3==0&&j!=0){output+=seperator;}
output+=character;}
var rev=output;output='';for(i=(rev.length-1);i>=0;i--){output+=rev.charAt(i);}
if(output.indexOf('-'+prop['chart.scale.thousand'])==0){output='-'+output.substr(('-'+prop['chart.scale.thousand']).length);}
if(decimal.length){output=output+decimal_seperator+decimal;decimal='';RegExp.$1='';}
if(output.charAt(0)=='-'){output=output.replace(/-/,'');prepend='-'+prepend;}
return prepend+output+append;};RG.drawBars=RG.DrawBars=function(obj)
{var prop=obj.properties;var co=obj.context;var ca=obj.canvas;var hbars=prop['chart.background.hbars'];if(hbars===null){return;}
co.beginPath();for(i=0,len=hbars.length;i<len;++i){var start=hbars[i][0];var length=hbars[i][1];var color=hbars[i][2];if(RG.is_null(start))start=obj.scale2.max
if(start>obj.scale2.max)start=obj.scale2.max;if(RG.is_null(length))length=obj.scale2.max-start;if(start+length>obj.scale2.max)length=obj.scale2.max-start;if(start+length<(-1*obj.scale2.max))length=(-1*obj.scale2.max)-start;if(prop['chart.xaxispos']=='center'&&start==obj.scale2.max&&length<(obj.scale2.max* -2)){length=obj.scale2.max* -2;}
var x=prop['chart.gutter.left'];var y=obj.getYCoord(start);var w=ca.width-prop['chart.gutter.left']-prop['chart.gutter.right'];var h=obj.getYCoord(start+length)-y;if(RG.ISOPERA!=-1&&prop['chart.xaxispos']=='center'&&h<0){h*=-1;y=y-h;}
if(prop['chart.xaxispos']=='top'){y=ca.height-y;h*=-1;}
co.fillStyle=color;co.fillRect(x,y,w,h);}};RG.drawInGraphLabels=RG.DrawInGraphLabels=function(obj)
{var ca=obj.canvas,co=obj.context,prop=obj.properties,labels=prop['chart.labels.ingraph'],labels_processed=[];var fgcolor='black',bgcolor='white',direction=1;if(!labels){return;}
for(var i=0,len=labels.length;i<len;i+=1){if(typeof labels[i]==='number'){for(var j=0;j<labels[i];++j){labels_processed.push(null);}}else if(typeof labels[i]==='string'||typeof labels[i]==='object'){labels_processed.push(labels[i]);}else{labels_processed.push('');}}
RG.noShadow(obj);if(labels_processed&&labels_processed.length>0){for(var i=0,len=labels_processed.length;i<len;i+=1){if(labels_processed[i]){var coords=obj.coords[i];if(coords&&coords.length>0){var x=(obj.type=='bar'?coords[0]+(coords[2]/2):coords[0]);var y=(obj.type=='bar'?coords[1]+(coords[3]/2):coords[1]);var length=typeof labels_processed[i][4]==='number'?labels_processed[i][4]:25;co.beginPath();co.fillStyle='black';co.strokeStyle='black';if(obj.type==='bar'){if(obj.Get('chart.xaxispos')=='top'){length*=-1;}
if(prop['chart.variant']=='dot'){co.moveTo(ma.round(x),obj.coords[i][1]-5);co.lineTo(ma.round(x),obj.coords[i][1]-5-length);var text_x=ma.round(x);var text_y=obj.coords[i][1]-5-length;}else if(prop['chart.variant']=='arrow'){co.moveTo(ma.round(x),obj.coords[i][1]-5);co.lineTo(ma.round(x),obj.coords[i][1]-5-length);var text_x=ma.round(x);var text_y=obj.coords[i][1]-5-length;}else{co.arc(ma.round(x),y,2.5,0,6.28,0);co.moveTo(ma.round(x),y);co.lineTo(ma.round(x),y-length);var text_x=ma.round(x);var text_y=y-length;}
co.stroke();co.fill();}else{if(typeof labels_processed[i]=='object'&&typeof labels_processed[i][3]=='number'&&labels_processed[i][3]==-1){drawUpArrow(x,y)
var valign='top';var text_x=x;var text_y=y+5+length;}else{var text_x=x;var text_y=y-5-length;if(text_y<5&&(typeof labels_processed[i]==='string'||typeof labels_processed[i][3]==='undefined')){text_y=y+5+length;var valign='top';}
if(valign==='top'){drawUpArrow(x,y);}else{drawDownArrow(x,y);}}
co.fill();}
co.beginPath();co.fillStyle=(typeof labels_processed[i]==='object'&&typeof labels_processed[i][1]==='string')?labels_processed[i][1]:'black';RG.text2(obj,{font:prop['chart.text.font'],size:prop['chart.text.size'],x:text_x,y:text_y+(obj.properties['chart.text.accessible']?2:0),text:(typeof labels_processed[i]==='object'&&typeof labels_processed[i][0]==='string')?labels_processed[i][0]:labels_processed[i],valign:valign||'bottom',halign:'center',bounding:true,'bounding.fill':(typeof labels_processed[i]==='object'&&typeof labels_processed[i][2]==='string')?labels_processed[i][2]:'white',tag:'labels ingraph'});co.fill();}
function drawUpArrow(x,y)
{co.moveTo(ma.round(x),y+5);co.lineTo(ma.round(x),y+5+length);co.stroke();co.beginPath();co.moveTo(ma.round(x),y+5);co.lineTo(ma.round(x)-3,y+10);co.lineTo(ma.round(x)+3,y+10);co.closePath();}
function drawDownArrow(x,y)
{co.moveTo(ma.round(x),y-5);co.lineTo(ma.round(x),y-5-length);co.stroke();co.beginPath();co.moveTo(ma.round(x),y-5);co.lineTo(ma.round(x)-3,y-10);co.lineTo(ma.round(x)+3,y-10);co.closePath();}
valign=undefined;}}}};RG.fixEventObject=RG.FixEventObject=function(e)
{if(RG.ISOLD){var e=event;e.pageX=(event.clientX+doc.body.scrollLeft);e.pageY=(event.clientY+doc.body.scrollTop);e.target=event.srcElement;if(!doc.body.scrollTop&&doc.documentElement.scrollTop){e.pageX+=parseInt(doc.documentElement.scrollLeft);e.pageY+=parseInt(doc.documentElement.scrollTop);}}
if(!e.stopPropagation){e.stopPropagation=function(){window.event.cancelBubble=true;}}
return e;};RG.hideCrosshairCoords=RG.HideCrosshairCoords=function()
{var div=RG.Registry.Get('chart.coordinates.coords.div');if(div&&div.style.opacity==1&&div.__object__.Get('chart.crosshairs.coords.fadeout')){var style=RG.Registry.Get('chart.coordinates.coords.div').style;setTimeout(function(){style.opacity=0.9;},25);setTimeout(function(){style.opacity=0.8;},50);setTimeout(function(){style.opacity=0.7;},75);setTimeout(function(){style.opacity=0.6;},100);setTimeout(function(){style.opacity=0.5;},125);setTimeout(function(){style.opacity=0.4;},150);setTimeout(function(){style.opacity=0.3;},175);setTimeout(function(){style.opacity=0.2;},200);setTimeout(function(){style.opacity=0.1;},225);setTimeout(function(){style.opacity=0;},250);setTimeout(function(){style.display='none';},275);}};RG.draw3DAxes=RG.Draw3DAxes=function(obj)
{var prop=obj.properties,co=obj.context,ca=obj.canvas;var gutterLeft=obj.gutterLeft,gutterRight=obj.gutterRight,gutterTop=obj.gutterTop,gutterBottom=obj.gutterBottom,xaxispos=prop['chart.xaxispos'],graphArea=ca.height-gutterTop-gutterBottom,halfGraphArea=graphArea/2,offsetx=prop['chart.variant.threed.offsetx'],offsety=prop['chart.variant.threed.offsety'],xaxis=prop['chart.variant.threed.xaxis'],yaxis=prop['chart.variant.threed.yaxis']
if(yaxis){RG.draw3DYAxis(obj);}
if(xaxis){if(xaxispos==='center'){RG.path2(co,'b m % % l % % l % % l % % c s #aaa f #ddd',gutterLeft,gutterTop+halfGraphArea,gutterLeft+offsetx,gutterTop+halfGraphArea-offsety,ca.width-gutterRight+offsetx,gutterTop+halfGraphArea-offsety,ca.width-gutterRight,gutterTop+halfGraphArea);}else{if(obj.type==='hbar'){var xaxisYCoord=obj.canvas.height-obj.properties['chart.gutter.bottom'];}else{var xaxisYCoord=obj.getYCoord(0);}
RG.path2(co,'m % % l % % l % % l % % c s #aaa f #ddd',gutterLeft,xaxisYCoord,gutterLeft+offsetx,xaxisYCoord-offsety,ca.width-gutterRight+offsetx,xaxisYCoord-offsety,ca.width-gutterRight,xaxisYCoord);}}};RG.draw3DYAxis=function(obj)
{var prop=obj.properties,co=obj.context,ca=obj.canvas;var gutterLeft=obj.gutterLeft,gutterRight=obj.gutterRight,gutterTop=obj.gutterTop,gutterBottom=obj.gutterBottom,xaxispos=prop['chart.xaxispos'],graphArea=ca.height-gutterTop-gutterBottom,halfGraphArea=graphArea/2,offsetx=prop['chart.variant.threed.offsetx'],offsety=prop['chart.variant.threed.offsety']
if((obj.type==='hbar'||obj.type==='bar')&&prop['chart.yaxispos']==='center'){var x=((ca.width-gutterLeft-gutterRight)/2)+gutterLeft;}else if((obj.type==='hbar'||obj.type==='bar')&&prop['chart.yaxispos']==='right'){var x=ca.width-gutterRight;}else{var x=gutterLeft;}
RG.path2(co,'b m % % l % % l % % l % % s #aaa f #ddd',x,gutterTop,x+offsetx,gutterTop-offsety,x+offsetx,ca.height-gutterBottom-offsety,x,ca.height-gutterBottom);};RG.strokedCurvyRect=function(co,x,y,w,h)
{var r=arguments[5]?arguments[5]:3;var corner_tl=(arguments[6]||arguments[6]==null)?true:false;var corner_tr=(arguments[7]||arguments[7]==null)?true:false;var corner_br=(arguments[8]||arguments[8]==null)?true:false;var corner_bl=(arguments[9]||arguments[9]==null)?true:false;co.beginPath();co.moveTo(x+(corner_tl?r:0),y);co.lineTo(x+w-(corner_tr?r:0),y);if(corner_tr){co.arc(x+w-r,y+r,r,RG.PI+RG.HALFPI,RG.TWOPI,false);}
co.lineTo(x+w,y+h-(corner_br?r:0));if(corner_br){co.arc(x+w-r,y-r+h,r,RG.TWOPI,RG.HALFPI,false);}
co.lineTo(x+(corner_bl?r:0),y+h);if(corner_bl){co.arc(x+r,y-r+h,r,RG.HALFPI,RG.PI,false);}
co.lineTo(x,y+(corner_tl?r:0));if(corner_tl){co.arc(x+r,y+r,r,RG.PI,RG.PI+RG.HALFPI,false);}
co.stroke();};RG.filledCurvyRect=function(co,x,y,w,h)
{var r=arguments[5]?arguments[5]:3;var corner_tl=(arguments[6]||arguments[6]==null)?true:false;var corner_tr=(arguments[7]||arguments[7]==null)?true:false;var corner_br=(arguments[8]||arguments[8]==null)?true:false;var corner_bl=(arguments[9]||arguments[9]==null)?true:false;co.beginPath();if(corner_tl){co.moveTo(x+r,y+r);co.arc(x+r,y+r,r,RG.PI,RG.PI+RG.HALFPI,false);}else{co.fillRect(x,y,r,r);}
if(corner_tr){co.moveTo(x+w-r,y+r);co.arc(x+w-r,y+r,r,RG.PI+RG.HALFPI,0,false);}else{co.moveTo(x+w-r,y);co.fillRect(x+w-r,y,r,r);}
if(corner_br){co.moveTo(x+w-r,y+h-r);co.arc(x+w-r,y-r+h,r,0,RG.HALFPI,false);}else{co.moveTo(x+w-r,y+h-r);co.fillRect(x+w-r,y+h-r,r,r);}
if(corner_bl){co.moveTo(x+r,y+h-r);co.arc(x+r,y-r+h,r,RG.HALFPI,RG.PI,false);}else{co.moveTo(x,y+h-r);co.fillRect(x,y+h-r,r,r);}
co.fillRect(x+r,y,w-r-r,h);co.fillRect(x,y+r,r+1,h-r-r);co.fillRect(x+w-r-1,y+r,r+1,h-r-r);co.fill();};RG.hideZoomedCanvas=RG.HideZoomedCanvas=function()
{var interval=10;var frames=15;if(typeof RG.zoom_image==='object'){var obj=RG.zoom_image.obj;var prop=obj.properties;}else{return;}
if(prop['chart.zoom.fade.out']){for(var i=frames,j=1;i>=0;--i,++j){if(typeof RG.zoom_image==='object'){setTimeout("RGraph.zoom_image.style.opacity = "+String(i/10),j*interval);}}
if(typeof RG.zoom_background==='object'){setTimeout("RGraph.zoom_background.style.opacity = "+String(i/frames),j*interval);}}
if(typeof RG.zoom_image==='object'){setTimeout("RGraph.zoom_image.style.display = 'none'",prop['chart.zoom.fade.out']?(frames*interval)+10:0);}
if(typeof RG.zoom_background==='object'){setTimeout("RGraph.zoom_background.style.display = 'none'",prop['chart.zoom.fade.out']?(frames*interval)+10:0);}};RG.addCustomEventListener=RG.AddCustomEventListener=function(obj,name,func)
{if(typeof RG.events[obj.uid]==='undefined'){RG.events[obj.uid]=[];}
if(name.substr(0,2)!=='on'){name='on'+name;}
RG.events[obj.uid].push([obj,name,func]);return RG.events[obj.uid].length-1;};RG.fireCustomEvent=RG.FireCustomEvent=function(obj,name)
{if(obj&&obj.isRGraph){if(name.match(/(on)?mouseout/)&&typeof obj.properties['chart.events.mouseout']==='function'){(obj.properties['chart.events.mouseout'])(obj);}
if(obj[name]){(obj[name])(obj);}
var uid=obj.uid;if(typeof uid==='string'&&typeof RG.events==='object'&&typeof RG.events[uid]==='object'&&RG.events[uid].length>0){for(var j=0;j<RG.events[uid].length;++j){if(RG.events[uid][j]&&RG.events[uid][j][1]===name){RG.events[uid][j][2](obj);}}}}};RGraph.removeAllCustomEventListeners=RGraph.RemoveAllCustomEventListeners=function()
{var id=arguments[0];if(id&&RG.events[id]){RG.events[id]=[];}else{RG.events=[];}};RG.removeCustomEventListener=RG.RemoveCustomEventListener=function(obj,i)
{if(typeof RG.events==='object'&&typeof RG.events[obj.id]==='object'&&typeof RG.events[obj.id][i]==='object'){RG.events[obj.id][i]=null;}};RG.drawBackgroundImage=RG.DrawBackgroundImage=function(obj)
{var prop=obj.properties;var ca=obj.canvas;var co=obj.context;if(typeof prop['chart.background.image']==='string'){if(typeof ca.__rgraph_background_image__==='undefined'){var img=new Image();img.__object__=obj;img.__canvas__=ca;img.__context__=co;img.src=obj.Get('chart.background.image');ca.__rgraph_background_image__=img;}else{img=ca.__rgraph_background_image__;}
img.onload=function()
{obj.__rgraph_background_image_loaded__=true;RG.clear(ca);RG.redrawCanvas(ca);}
var gutterLeft=obj.gutterLeft;var gutterRight=obj.gutterRight;var gutterTop=obj.gutterTop;var gutterBottom=obj.gutterBottom;var stretch=prop['chart.background.image.stretch'];var align=prop['chart.background.image.align'];if(typeof align==='string'){if(align.indexOf('right')!=-1){var x=ca.width-(prop['chart.background.image.w']||img.width)-gutterRight;}else{var x=gutterLeft;}
if(align.indexOf('bottom')!=-1){var y=ca.height-(prop['chart.background.image.h']||img.height)-gutterBottom;}else{var y=gutterTop;}}else{var x=gutterLeft||25;var y=gutterTop||25;}
var x=typeof prop['chart.background.image.x']==='number'?prop['chart.background.image.x']:x;var y=typeof prop['chart.background.image.y']==='number'?prop['chart.background.image.y']:y;var w=stretch?ca.width-gutterLeft-gutterRight:img.width;var h=stretch?ca.height-gutterTop-gutterBottom:img.height;if(typeof prop['chart.background.image.w']==='number')w=prop['chart.background.image.w'];if(typeof prop['chart.background.image.h']==='number')h=prop['chart.background.image.h'];var oldAlpha=co.globalAlpha;co.globalAlpha=prop['chart.background.image.alpha'];co.drawImage(img,x,y,w,h);co.globalAlpha=oldAlpha;}};RG.hasTooltips=function(obj)
{var prop=obj.properties;if(typeof prop['chart.tooltips']=='object'&&prop['chart.tooltips']){for(var i=0,len=prop['chart.tooltips'].length;i<len;++i){if(!RG.is_null(obj.Get('chart.tooltips')[i])){return true;}}}else if(typeof prop['chart.tooltips']==='function'){return true;}
return false;};RG.createUID=RG.CreateUID=function()
{return'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g,function(c)
{var r=ma.random()*16|0,v=c=='x'?r:(r&0x3|0x8);return v.toString(16);});};RG.OR.add=RG.OR.Add=function(obj)
{var uid=obj.uid;var id=obj.canvas.id;RG.ObjectRegistry.objects.byUID.push([uid,obj]);RG.ObjectRegistry.objects.byCanvasID.push([id,obj]);};RG.OR.remove=RG.OR.Remove=function(obj)
{var id=obj.id;var uid=obj.uid;for(var i=0;i<RG.ObjectRegistry.objects.byUID.length;++i){if(RG.ObjectRegistry.objects.byUID[i]&&RG.ObjectRegistry.objects.byUID[i][1].uid==uid){RG.ObjectRegistry.objects.byUID[i]=null;}}
for(var i=0;i<RG.ObjectRegistry.objects.byCanvasID.length;++i){if(RG.ObjectRegistry.objects.byCanvasID[i]&&RG.ObjectRegistry.objects.byCanvasID[i][1]&&RG.ObjectRegistry.objects.byCanvasID[i][1].uid==uid){RG.ObjectRegistry.objects.byCanvasID[i]=null;}}};RG.OR.clear=RG.OR.Clear=function()
{if(arguments[0]){var id=(typeof arguments[0]==='object'?arguments[0].id:arguments[0]);var objects=RG.ObjectRegistry.getObjectsByCanvasID(id);for(var i=0,len=objects.length;i<len;++i){RG.ObjectRegistry.remove(objects[i]);}}else{RG.ObjectRegistry.objects={};RG.ObjectRegistry.objects.byUID=[];RG.ObjectRegistry.objects.byCanvasID=[];}};RG.OR.list=RG.OR.List=function()
{var list=[];for(var i=0,len=RG.ObjectRegistry.objects.byUID.length;i<len;++i){if(RG.ObjectRegistry.objects.byUID[i]){list.push(RG.ObjectRegistry.objects.byUID[i][1].type);}}
if(arguments[0]){return list;}else{$p(list);}};RG.OR.clearByType=RG.OR.ClearByType=function(type)
{var objects=RG.ObjectRegistry.objects.byUID;for(var i=0,len=objects.length;i<len;++i){if(objects[i]){var uid=objects[i][0];var obj=objects[i][1];if(obj&&obj.type==type){RG.ObjectRegistry.remove(obj);}}}};RG.OR.iterate=RG.OR.Iterate=function(func)
{var objects=RGraph.ObjectRegistry.objects.byUID;for(var i=0,len=objects.length;i<len;++i){if(typeof arguments[1]==='string'){var types=arguments[1].split(/,/);for(var j=0,len2=types.length;j<len2;++j){if(types[j]==objects[i][1].type){func(objects[i][1]);}}}else{func(objects[i][1]);}}};RG.OR.getObjectsByCanvasID=function(id)
{var store=RG.ObjectRegistry.objects.byCanvasID;var ret=[];for(var i=0,len=store.length;i<len;++i){if(store[i]&&store[i][0]==id){ret.push(store[i][1]);}}
return ret;};RG.OR.firstbyxy=RG.OR.getFirstObjectByXY=RG.OR.getObjectByXY=function(e)
{var canvas=e.target;var ret=null;var objects=RG.ObjectRegistry.getObjectsByCanvasID(canvas.id);for(var i=(objects.length-1);i>=0;--i){var obj=objects[i].getObjectByXY(e);if(obj){return obj;}}};RG.OR.getObjectsByXY=function(e)
{var canvas=e.target,ret=[],objects=RG.ObjectRegistry.getObjectsByCanvasID(canvas.id);for(var i=(objects.length-1);i>=0;--i){var obj=objects[i].getObjectByXY(e);if(obj){ret.push(obj);}}
return ret;};RG.OR.get=RG.OR.getObjectByUID=function(uid)
{var objects=RG.ObjectRegistry.objects.byUID;for(var i=0,len=objects.length;i<len;++i){if(objects[i]&&objects[i][1].uid==uid){return objects[i][1];}}};RG.OR.bringToFront=function(obj)
{var redraw=typeof arguments[1]==='undefined'?true:arguments[1];RG.ObjectRegistry.remove(obj);RG.ObjectRegistry.add(obj);if(redraw){RG.redrawCanvas(obj.canvas);}};RG.OR.type=RG.OR.getObjectsByType=function(type)
{var objects=RG.ObjectRegistry.objects.byUID;var ret=[];for(var i=0,len=objects.length;i<len;++i){if(objects[i]&&objects[i][1]&&objects[i][1].type&&objects[i][1].type&&objects[i][1].type==type){ret.push(objects[i][1]);}}
return ret;};RG.OR.first=RG.OR.getFirstObjectByType=function(type)
{var objects=RG.ObjectRegistry.objects.byUID;for(var i=0,len=objects.length;i<len;++i){if(objects[i]&&objects[i][1]&&objects[i][1].type==type){return objects[i][1];}}
return null;};RG.getAngleByXY=function(cx,cy,x,y)
{var angle=ma.atan((y-cy)/(x-cx));angle=ma.abs(angle)
if(x>=cx&&y>=cy){angle+=RG.TWOPI;}else if(x>=cx&&y<cy){angle=(RG.HALFPI-angle)+(RG.PI+RG.HALFPI);}else if(x<cx&&y<cy){angle+=RG.PI;}else{angle=RG.PI-angle;}
if(angle>RG.TWOPI){angle-=RG.TWOPI;}
return angle;};RG.getHypLength=function(x1,y1,x2,y2)
{var ret=ma.sqrt(((x2-x1)*(x2-x1))+((y2-y1)*(y2-y1)));return ret;};RG.getRadiusEndPoint=function(cx,cy,angle,radius)
{var x=cx+(ma.cos(angle)*radius);var y=cy+(ma.sin(angle)*radius);return[x,y];};RG.installEventListeners=RG.InstallEventListeners=function(obj)
{var prop=obj.properties;if(RG.ISOLD){return;}
if(RG.installCanvasClickListener){RG.installWindowMousedownListener(obj);RG.installWindowMouseupListener(obj);RG.installCanvasMousemoveListener(obj);RG.installCanvasMouseupListener(obj);RG.installCanvasMousedownListener(obj);RG.installCanvasClickListener(obj);}else if(RG.hasTooltips(obj)||prop['chart.adjustable']||prop['chart.annotatable']||prop['chart.contextmenu']||prop['chart.resizable']||prop['chart.key.interactive']||prop['chart.events.click']||prop['chart.events.mousemove']||typeof obj.onclick==='function'||typeof obj.onmousemove==='function'){alert('[RGRAPH] You appear to have used dynamic features but not included the file: RGraph.common.dynamic.js');}};RG.pr=function(obj)
{var indent=(arguments[2]?arguments[2]:'    ');var str='';var counter=typeof arguments[3]=='number'?arguments[3]:0;if(counter>=5){return'';}
switch(typeof obj){case'string':str+=obj+' ('+(typeof obj)+', '+obj.length+')';break;case'number':str+=obj+' ('+(typeof obj)+')';break;case'boolean':str+=obj+' ('+(typeof obj)+')';break;case'function':str+='function () {}';break;case'undefined':str+='undefined';break;case'null':str+='null';break;case'object':if(RGraph.is_null(obj)){str+=indent+'null\n';}else{str+=indent+'Object {'+'\n'
for(j in obj){str+=indent+'    '+j+' => '+RGraph.pr(obj[j],true,indent+'    ',counter+1)+'\n';}
str+=indent+'}';}
break;default:str+='Unknown type: '+typeof obj+'';break;}
if(!arguments[1]){alert(str);}
return str;};RG.dashedLine=RG.DashedLine=function(co,x1,y1,x2,y2)
{var size=5;if(typeof arguments[5]==='number'){size=arguments[5];}
var dx=x2-x1;var dy=y2-y1;var num=ma.floor(ma.sqrt((dx*dx)+(dy*dy))/size);var xLen=dx/num;var yLen=dy/num;var count=0;do{(count%2==0&&count>0)?co.lineTo(x1,y1):co.moveTo(x1,y1);x1+=xLen;y1+=yLen;}while(count++<=num);};RG.AJAX=function(url,callback)
{if(window.XMLHttpRequest){var httpRequest=new XMLHttpRequest();}else if(window.ActiveXObject){var httpRequest=new ActiveXObject("Microsoft.XMLHTTP");}
httpRequest.onreadystatechange=function()
{if(this.readyState==4&&this.status==200){this.__user_callback__=callback;this.__user_callback__(this.responseText);}}
httpRequest.open('GET',url,true);httpRequest.send();};RG.AJAX.POST=function(url,data,callback)
{var crumbs=[];if(window.XMLHttpRequest){var httpRequest=new XMLHttpRequest();}else if(window.ActiveXObject){var httpRequest=new ActiveXObject("Microsoft.XMLHTTP");}
httpRequest.onreadystatechange=function()
{if(this.readyState==4&&this.status==200){this.__user_callback__=callback;this.__user_callback__(this.responseText);}}
httpRequest.open('POST',url,true);httpRequest.setRequestHeader("Content-type","application/x-www-form-urlencoded");for(i in data){if(typeof i=='string'){crumbs.push(i+'='+encodeURIComponent(data[i]));}}
httpRequest.send(crumbs.join('&'));};RG.AJAX.getNumber=function(url,callback)
{RG.AJAX(url,function()
{var num=parseFloat(this.responseText);callback(num);});};RG.AJAX.getString=function(url,callback)
{RG.AJAX(url,function()
{var str=String(this.responseText);callback(str);});};RG.AJAX.getJSON=function(url,callback)
{RG.AJAX(url,function()
{var json=eval('('+this.responseText+')');callback(json);});};RG.AJAX.getCSV=function(url,callback)
{var seperator=arguments[2]?arguments[2]:',';RG.AJAX(url,function()
{var regexp=new RegExp(seperator);var arr=this.responseText.split(regexp);for(var i=0,len=arr.length;i<len;++i){arr[i]=parseFloat(arr[i]);}
callback(arr);});};RG.rotateCanvas=RG.RotateCanvas=function(ca,x,y,angle)
{var co=ca.getContext('2d');co.translate(x,y);co.rotate(angle);co.translate(0-x,0-y);};RG.measureText=RG.MeasureText=function(text,bold,font,size)
{if(typeof RG.measuretext_cache==='undefined'){RG.measuretext_cache=[];}
var str=text+':'+bold+':'+font+':'+size;if(typeof RG.measuretext_cache=='object'&&RG.measuretext_cache[str]){return RG.measuretext_cache[str];}
if(!RG.measuretext_cache['text-div']){var div=document.createElement('DIV');div.style.position='absolute';div.style.top='-100px';div.style.left='-100px';document.body.appendChild(div);RG.measuretext_cache['text-div']=div;}else if(RG.measuretext_cache['text-div']){var div=RG.measuretext_cache['text-div'];}
div.innerHTML=text.replace(/\r\n/g,'<br />');div.style.fontFamily=font;div.style.fontWeight=bold?'bold':'normal';div.style.fontSize=(size||12)+'pt';var size=[div.offsetWidth,div.offsetHeight];RG.measuretext_cache[str]=size;return size;};RG.text2=RG.Text2=function(obj,opt)
{function domtext()
{if(String(opt.size).toLowerCase().indexOf('italic')!==-1){opt.size=opt.size.replace(/ *italic +/,'');opt.italic=true;}
var cacheKey=ma.abs(parseInt(opt.x))+'_'+ma.abs(parseInt(opt.y))+'_'+String(opt.text).replace(/[^a-zA-Z0-9]+/g,'_')+'_'+obj.canvas.id;if(!ca.rgraph_domtext_wrapper){var wrapper=document.createElement('div');wrapper.id=ca.id+'_rgraph_domtext_wrapper';wrapper.className='rgraph_domtext_wrapper';wrapper.style.overflow=obj.properties['chart.text.accessible.overflow']!=false&&obj.properties['chart.text.accessible.overflow']!='hidden'?'visible':'hidden';wrapper.style.width=ca.offsetWidth+'px';wrapper.style.height=ca.offsetHeight+'px';wrapper.style.cssFloat=ca.style.cssFloat;wrapper.style.display=ca.style.display||'inline-block';wrapper.style.position=ca.style.position||'relative';wrapper.style.left=ca.style.left;wrapper.style.top=ca.style.top;wrapper.style.width=ca.width+'px';wrapper.style.height=ca.height+'px';ca.style.position='absolute';ca.style.left=0;ca.style.top=0;ca.style.display='inline';ca.style.cssFloat='none';if((obj.type==='bar'||obj.type==='bipolar'||obj.type==='hbar')&&obj.properties['chart.variant']==='3d'){wrapper.style.transform='skewY(5.7deg)';}
ca.parentNode.insertBefore(wrapper,ca);ca.parentNode.removeChild(ca);wrapper.appendChild(ca);ca.rgraph_domtext_wrapper=wrapper;}else{wrapper=ca.rgraph_domtext_wrapper;}
var defaults={size:12,font:'Arial',italic:'normal',bold:'normal',valign:'bottom',halign:'left',marker:true,color:co.fillStyle,bounding:{enabled:false,fill:'rgba(255,255,255,0.7)',stroke:'#666'}}
opt.text=String(opt.text).replace(/\r?\n/g,'[[RETURN]]');if(typeof RG.text2.domNodeCache==='undefined'){RG.text2.domNodeCache=new Array();}
if(typeof RG.text2.domNodeCache[obj.id]==='undefined'){RG.text2.domNodeCache[obj.id]=new Array();}
if(typeof RG.text2.domNodeDimensionCache==='undefined'){RG.text2.domNodeDimensionCache=new Array();}
if(typeof RG.text2.domNodeDimensionCache[obj.id]==='undefined'){RG.text2.domNodeDimensionCache[obj.id]=new Array();}
if(!RG.text2.domNodeCache[obj.id]||!RG.text2.domNodeCache[obj.id][cacheKey]){var span=document.createElement('span');span.style.position='absolute';span.style.display='inline';span.style.left=(opt.x*(parseInt(ca.offsetWidth)/parseInt(ca.width)))+'px';span.style.top=(opt.y*(parseInt(ca.offsetHeight)/parseInt(ca.height)))+'px';span.style.color=opt.color||defaults.color;span.style.fontFamily=opt.font||defaults.font;span.style.fontWeight=opt.bold?'bold':defaults.bold;span.style.fontStyle=opt.italic?'italic':defaults.italic;span.style.fontSize=(opt.size||defaults.size)+'pt';span.style.whiteSpace='nowrap';span.tag=opt.tag;if(typeof opt.angle==='number'&&opt.angle!==0){var coords=RG.measureText(opt.text,opt.bold,opt.font,opt.size);span.style.transformOrigin='100% 50%';span.style.transform='rotate('+opt.angle+'deg)';}
span.style.textShadow='{1}px {2}px {3}px {4}'.format(co.shadowOffsetX,co.shadowOffsetY,co.shadowBlur,co.shadowColor);if(opt.bounding){span.style.border='1px solid '+(opt['bounding.stroke']||defaults.bounding.stroke);span.style.backgroundColor=opt['bounding.fill']||defaults.bounding.fill;}
if((typeof obj.properties['chart.text.accessible.pointerevents']==='undefined'||obj.properties['chart.text.accessible.pointerevents'])&&obj.properties['chart.text.accessible.pointerevents']!=='none'){span.style.pointerEvents='auto';}else{span.style.pointerEvents='none';}
span.style.padding=opt.bounding?'2px':null;span.__text__=opt.text
span.innerHTML=opt.text.replace('&','&amp;').replace('<','&lt;').replace('>','&gt;');span.innerHTML=span.innerHTML.replace(/\[\[RETURN\]\]/g,'<br />');wrapper.appendChild(span);opt.halign=opt.halign||'left';opt.valign=opt.valign||'bottom';if(opt.halign==='right'){span.style.left=parseFloat(span.style.left)-span.offsetWidth+'px';span.style.textAlign='right';}else if(opt.halign==='center'){span.style.left=parseFloat(span.style.left)-(span.offsetWidth/2)+'px';span.style.textAlign='center';}
if(opt.valign==='top'){}else if(opt.valign==='center'){span.style.top=parseFloat(span.style.top)-(span.offsetHeight/2)+'px';}else{span.style.top=parseFloat(span.style.top)-span.offsetHeight+'px';}
var offsetWidth=parseFloat(span.offsetWidth),offsetHeight=parseFloat(span.offsetHeight),top=parseFloat(span.style.top),left=parseFloat(span.style.left);RG.text2.domNodeCache[obj.id][cacheKey]=span;RG.text2.domNodeDimensionCache[obj.id][cacheKey]={left:left,top:top,width:offsetWidth,height:offsetHeight};span.id=cacheKey;}else{span=RG.text2.domNodeCache[obj.id][cacheKey];span.style.display='inline';var offsetWidth=RG.text2.domNodeDimensionCache[obj.id][cacheKey].width,offsetHeight=RG.text2.domNodeDimensionCache[obj.id][cacheKey].height,top=RG.text2.domNodeDimensionCache[obj.id][cacheKey].top,left=RG.text2.domNodeDimensionCache[obj.id][cacheKey].left;}
if(opt.marker){RG.path2(context,'b m % % l % % m % % l % % s',opt.x-5,opt.y,opt.x+5,opt.y,opt.x,opt.y-5,opt.x,opt.y+5);}
if(obj.type==='drawing.text'){if(obj.properties['chart.events.mousemove']){span.addEventListener('mousemove',function(e){(obj.properties['chart.events.mousemove'])(e,obj);},false);}
if(obj.properties['chart.events.click']){span.addEventListener('click',function(e){(obj.properties['chart.events.click'])(e,obj);},false);}
if(obj.properties['chart.tooltips']){span.addEventListener(obj.properties['chart.tooltips.event'].indexOf('mousemove')!==-1?'mousemove':'click',function(e)
{if(!RG.Registry.get('chart.tooltip')||RG.Registry.get('chart.tooltip').__index__!==0||RG.Registry.get('chart.tooltip').__object__.uid!=obj.uid){RG.hideTooltip();RG.redraw();RG.tooltip(obj,obj.properties['chart.tooltips'][0],opt.x,opt.y,0,e);}},false);}}
var ret={};ret.x=left;ret.y=top;ret.width=offsetWidth;ret.height=offsetHeight;ret.object=obj;ret.text=opt.text;ret.tag=opt.tag;RG.text2.domNodeCache.reset=function()
{if(arguments[0]){if(typeof arguments[0]==='string'){var ca=document.getElementById(arguments[0])}else{var ca=arguments[0];}
var nodes=RG.text2.domNodeCache[ca.id];for(j in nodes){var node=RG.text2.domNodeCache[ca.id][j];if(node&&node.parentNode){node.parentNode.removeChild(node);}}
RG.text2.domNodeCache[ca.id]=[];RG.text2.domNodeDimensionCache[ca.id]=[];}else{for(i in RG.text2.domNodeCache){for(j in RG.text2.domNodeCache[i]){if(RG.text2.domNodeCache[i][j]&&RG.text2.domNodeCache[i][j].parentNode){RG.text2.domNodeCache[i][j].parentNode.removeChild(RG.text2.domNodeCache[i][j]);}}}
RG.text2.domNodeCache=[];RG.text2.domNodeDimensionCache=[];}};RG.text2.find=function(opt)
{var span,nodes=[];var id=typeof opt.id==='string'?opt.id:opt.object.id;for(i in RG.text2.domNodeCache[id]){span=RG.text2.domNodeCache[id][i];if(typeof opt.tag==='string'&&opt.tag===span.tag){nodes.push(span);continue;}
if(typeof opt.tag==='object'&&opt.tag.constructor.toString().indexOf('RegExp')){var regexp=new RegExp(opt.tag);if(regexp.test(span.tag)){nodes.push(span);continue;}}
if(typeof opt.text==='string'&&opt.text===span.__text__){nodes.push(span);continue;}
if(typeof opt.text==='object'&&opt.text.constructor.toString().indexOf('RegExp')){var regexp=new RegExp(opt.text);if(regexp.test(span.__text__)){nodes.push(span);continue;}}}
return nodes;};ret.node=span;if(obj&&obj.isRGraph&&obj.coordsText){obj.coordsText.push(ret);}
return ret;}
if(obj&&obj.isRGraph){var obj=obj;var co=obj.context;var ca=obj.canvas;}else if(typeof obj=='string'){var ca=document.getElementById(obj);var co=ca.getContext('2d');var obj=ca.__object__;}else if(typeof obj.getContext==='function'){var ca=obj;var co=ca.getContext('2d');var obj=ca.__object__;}else if(obj.toString().indexOf('CanvasRenderingContext2D')!=-1||RGraph.ISIE8&&obj.moveTo){var co=obj;var ca=obj.canvas;var obj=ca.__object__;}else if(RG.ISOLD&&obj.fillText){var co=obj;var ca=obj.canvas;var obj=ca.__object__;}
if(typeof opt.boundingFill==='string')opt['bounding.fill']=opt.boundingFill;if(typeof opt.boundingStroke==='string')opt['bounding.stroke']=opt.boundingStroke;if(obj&&obj.properties['chart.text.accessible']&&opt.accessible!==false){return domtext();}
var x=opt.x,y=opt.y,originalX=x,originalY=y,text=opt.text,text_multiline=typeof text==='string'?text.split(/\r?\n/g):'',numlines=text_multiline.length,font=opt.font?opt.font:'Arial',size=opt.size?opt.size:10,size_pixels=size*1.5,bold=opt.bold,italic=opt.italic,halign=opt.halign?opt.halign:'left',valign=opt.valign?opt.valign:'bottom',tag=typeof opt.tag=='string'&&opt.tag.length>0?opt.tag:'',marker=opt.marker,angle=opt.angle||0
var bounding=opt.bounding,bounding_stroke=opt['bounding.stroke']?opt['bounding.stroke']:'black',bounding_fill=opt['bounding.fill']?opt['bounding.fill']:'rgba(255,255,255,0.7)',bounding_shadow=opt['bounding.shadow'],bounding_shadow_color=opt['bounding.shadow.color']||'#ccc',bounding_shadow_blur=opt['bounding.shadow.blur']||3,bounding_shadow_offsetx=opt['bounding.shadow.offsetx']||3,bounding_shadow_offsety=opt['bounding.shadow.offsety']||3,bounding_linewidth=opt['bounding.linewidth']||1;var ret={};if(typeof opt.color==='string'){var orig_fillstyle=co.fillStyle;co.fillStyle=opt.color;}
if(typeof text=='number'){text=String(text);}
if(typeof text!=='string'){return;}
if(angle!=0){co.save();co.translate(x,y);co.rotate((ma.PI/180)*angle)
x=0;y=0;}
co.font=(opt.italic?'italic ':'')+(opt.bold?'bold ':'')+size+'pt '+font;var width=0;for(var i=0;i<numlines;++i){width=ma.max(width,co.measureText(text_multiline[i]).width);}
var height=size_pixels*numlines;if(opt.marker){var marker_size=10;var strokestyle=co.strokeStyle;co.beginPath();co.strokeStyle='red';co.moveTo(x,y-marker_size);co.lineTo(x,y+marker_size);co.moveTo(x-marker_size,y);co.lineTo(x+marker_size,y);co.stroke();co.strokeStyle=strokestyle;}
if(halign=='center'){co.textAlign='center';var boundingX=x-2-(width/2);}else if(halign=='right'){co.textAlign='right';var boundingX=x-2-width;}else{co.textAlign='left';var boundingX=x-2;}
if(valign=='center'){co.textBaseline='middle';y-=1;y-=((numlines-1)/2)*size_pixels;var boundingY=y-(size_pixels/2)-2;}else if(valign=='top'){co.textBaseline='top';var boundingY=y-2;}else{co.textBaseline='bottom';if(numlines>1){y-=((numlines-1)*size_pixels);}
var boundingY=y-size_pixels-2;}
var boundingW=width+4;var boundingH=height+4;if(bounding){var pre_bounding_linewidth=co.lineWidth;var pre_bounding_strokestyle=co.strokeStyle;var pre_bounding_fillstyle=co.fillStyle;var pre_bounding_shadowcolor=co.shadowColor;var pre_bounding_shadowblur=co.shadowBlur;var pre_bounding_shadowoffsetx=co.shadowOffsetX;var pre_bounding_shadowoffsety=co.shadowOffsetY;co.lineWidth=bounding_linewidth;co.strokeStyle=bounding_stroke;co.fillStyle=bounding_fill;if(bounding_shadow){co.shadowColor=bounding_shadow_color;co.shadowBlur=bounding_shadow_blur;co.shadowOffsetX=bounding_shadow_offsetx;co.shadowOffsetY=bounding_shadow_offsety;}
co.strokeRect(boundingX,boundingY,boundingW,boundingH);co.fillRect(boundingX,boundingY,boundingW,boundingH);co.lineWidth=pre_bounding_linewidth;co.strokeStyle=pre_bounding_strokestyle;co.fillStyle=pre_bounding_fillstyle;co.shadowColor=pre_bounding_shadowcolor
co.shadowBlur=pre_bounding_shadowblur
co.shadowOffsetX=pre_bounding_shadowoffsetx
co.shadowOffsetY=pre_bounding_shadowoffsety}
if(numlines>1){for(var i=0;i<numlines;++i){co.fillText(text_multiline[i],x,y+(size_pixels*i));}}else{co.fillText(text,x+0.5,y+0.5);}
if(angle!=0){if(angle==90){if(halign=='left'){if(valign=='bottom'){boundingX=originalX-2;boundingY=originalY-2;boundingW=height+4;boundingH=width+4;}
if(valign=='center'){boundingX=originalX-(height/2)-2;boundingY=originalY-2;boundingW=height+4;boundingH=width+4;}
if(valign=='top'){boundingX=originalX-height-2;boundingY=originalY-2;boundingW=height+4;boundingH=width+4;}}else if(halign=='center'){if(valign=='bottom'){boundingX=originalX-2;boundingY=originalY-(width/2)-2;boundingW=height+4;boundingH=width+4;}
if(valign=='center'){boundingX=originalX-(height/2)-2;boundingY=originalY-(width/2)-2;boundingW=height+4;boundingH=width+4;}
if(valign=='top'){boundingX=originalX-height-2;boundingY=originalY-(width/2)-2;boundingW=height+4;boundingH=width+4;}}else if(halign=='right'){if(valign=='bottom'){boundingX=originalX-2;boundingY=originalY-width-2;boundingW=height+4;boundingH=width+4;}
if(valign=='center'){boundingX=originalX-(height/2)-2;boundingY=originalY-width-2;boundingW=height+4;boundingH=width+4;}
if(valign=='top'){boundingX=originalX-height-2;boundingY=originalY-width-2;boundingW=height+4;boundingH=width+4;}}}else if(angle==180){if(halign=='left'){if(valign=='bottom'){boundingX=originalX-width-2;boundingY=originalY-2;boundingW=width+4;boundingH=height+4;}
if(valign=='center'){boundingX=originalX-width-2;boundingY=originalY-(height/2)-2;boundingW=width+4;boundingH=height+4;}
if(valign=='top'){boundingX=originalX-width-2;boundingY=originalY-height-2;boundingW=width+4;boundingH=height+4;}}else if(halign=='center'){if(valign=='bottom'){boundingX=originalX-(width/2)-2;boundingY=originalY-2;boundingW=width+4;boundingH=height+4;}
if(valign=='center'){boundingX=originalX-(width/2)-2;boundingY=originalY-(height/2)-2;boundingW=width+4;boundingH=height+4;}
if(valign=='top'){boundingX=originalX-(width/2)-2;boundingY=originalY-height-2;boundingW=width+4;boundingH=height+4;}}else if(halign=='right'){if(valign=='bottom'){boundingX=originalX-2;boundingY=originalY-2;boundingW=width+4;boundingH=height+4;}
if(valign=='center'){boundingX=originalX-2;boundingY=originalY-(height/2)-2;boundingW=width+4;boundingH=height+4;}
if(valign=='top'){boundingX=originalX-2;boundingY=originalY-height-2;boundingW=width+4;boundingH=height+4;}}}else if(angle==270){if(halign=='left'){if(valign=='bottom'){boundingX=originalX-height-2;boundingY=originalY-width-2;boundingW=height+4;boundingH=width+4;}
if(valign=='center'){boundingX=originalX-(height/2)-4;boundingY=originalY-width-2;boundingW=height+4;boundingH=width+4;}
if(valign=='top'){boundingX=originalX-2;boundingY=originalY-width-2;boundingW=height+4;boundingH=width+4;}}else if(halign=='center'){if(valign=='bottom'){boundingX=originalX-height-2;boundingY=originalY-(width/2)-2;boundingW=height+4;boundingH=width+4;}
if(valign=='center'){boundingX=originalX-(height/2)-4;boundingY=originalY-(width/2)-2;boundingW=height+4;boundingH=width+4;}
if(valign=='top'){boundingX=originalX-2;boundingY=originalY-(width/2)-2;boundingW=height+4;boundingH=width+4;}}else if(halign=='right'){if(valign=='bottom'){boundingX=originalX-height-2;boundingY=originalY-2;boundingW=height+4;boundingH=width+4;}
if(valign=='center'){boundingX=originalX-(height/2)-2;boundingY=originalY-2;boundingW=height+4;boundingH=width+4;}
if(valign=='top'){boundingX=originalX-2;boundingY=originalY-2;boundingW=height+4;boundingH=width+4;}}}
co.restore();}
co.textBaseline='alphabetic';co.textAlign='left';ret.x=boundingX;ret.y=boundingY;ret.width=boundingW;ret.height=boundingH
ret.object=obj;ret.text=text;ret.tag=tag;if(obj&&obj.isRGraph&&obj.coordsText){obj.coordsText.push(ret);}
if(typeof orig_fillstyle==='string'){co.fillStyle=orig_fillstyle;}
return ret;};RG.sequentialIndexToGrouped=function(index,data)
{var group=0;var grouped_index=0;while(--index>=0){if(RG.is_null(data[group])){group++;grouped_index=0;continue;}
if(typeof data[group]=='number'){group++
grouped_index=0;continue;}
grouped_index++;if(grouped_index>=data[group].length){group++;grouped_index=0;}}
return[group,grouped_index];};RG.Highlight.rect=RG.Highlight.Rect=function(obj,shape)
{var ca=obj.canvas;var co=obj.context;var prop=obj.properties;if(prop['chart.tooltips.highlight']){co.lineWidth=1;co.beginPath();co.strokeStyle=prop['chart.highlight.stroke'];co.fillStyle=prop['chart.highlight.fill'];co.rect(shape['x'],shape['y'],shape['width'],shape['height']);co.stroke();co.fill();}};RG.Highlight.point=RG.Highlight.Point=function(obj,shape)
{var prop=obj.properties;var ca=obj.canvas;var co=obj.context;if(prop['chart.tooltips.highlight']){co.beginPath();co.strokeStyle=prop['chart.highlight.stroke'];co.fillStyle=prop['chart.highlight.fill'];var radius=prop['chart.highlight.point.radius']||2;co.arc(shape['x'],shape['y'],radius,0,RG.TWOPI,0);co.stroke();co.fill();}};RG.parseDate=function(str)
{str=RG.trim(str);if(str==='now'){str=(new Date()).toString();}
if(str.match(/^(\d\d)(?:-|\/)(\d\d)(?:-|\/)(\d\d\d\d)(.*)$/)){str='{1}/{2}/{3}{4}'.format(RegExp.$3,RegExp.$2,RegExp.$1,RegExp.$4);}
if(str.match(/^(\d\d\d\d)(-|\/)(\d\d)(-|\/)(\d\d)( |T)(\d\d):(\d\d):(\d\d)$/)){str=RegExp.$1+'-'+RegExp.$3+'-'+RegExp.$5+'T'+RegExp.$7+':'+RegExp.$8+':'+RegExp.$9;}
if(str.match(/^\d\d\d\d-\d\d-\d\d$/)){str=str.replace(/-/g,'/');}
if(str.match(/^\d\d:\d\d:\d\d$/)){var dateObj=new Date();var date=dateObj.getDate();var month=dateObj.getMonth()+1;var year=dateObj.getFullYear();if(String(month).length===1)month='0'+month;if(String(date).length===1)date='0'+date;str=(year+'/'+month+'/'+date)+' '+str;}
return Date.parse(str);};RG.resetColorsToOriginalValues=function(obj)
{if(obj.original_colors){for(var j in obj.original_colors){if(typeof j==='string'&&j.substr(0,6)==='chart.'){obj.properties[j]=RG.arrayClone(obj.original_colors[j]);}}}
if(typeof obj.resetColorsToOriginalValues==='function'){obj.resetColorsToOriginalValues();}
obj.colorsParsed=false;};RG.linearGradient=RG.LinearGradient=function(obj,x1,y1,x2,y2,color1,color2)
{var gradient=obj.context.createLinearGradient(x1,y1,x2,y2);var numColors=arguments.length-5;for(var i=5;i<arguments.length;++i){var color=arguments[i];var stop=(i-5)/(numColors-1);gradient.addColorStop(stop,color);}
return gradient;};RG.radialGradient=RG.RadialGradient=function(obj,x1,y1,r1,x2,y2,r2,color1,color2)
{var gradient=obj.context.createRadialGradient(x1,y1,r1,x2,y2,r2);var numColors=arguments.length-7;for(var i=7;i<arguments.length;++i){var color=arguments[i];var stop=(i-7)/(numColors-1);gradient.addColorStop(stop,color);}
return gradient;};RG.addEventListener=RG.AddEventListener=function(id,e,func)
{var type=arguments[3]?arguments[3]:'unknown';RG.Registry.get('chart.event.handlers').push([id,e,func,type]);};RG.clearEventListeners=RG.ClearEventListeners=function(id)
{if(id&&id=='window'){window.removeEventListener('mousedown',window.__rgraph_mousedown_event_listener_installed__,false);window.removeEventListener('mouseup',window.__rgraph_mouseup_event_listener_installed__,false);}else{var canvas=document.getElementById(id);canvas.removeEventListener('mouseup',canvas.__rgraph_mouseup_event_listener_installed__,false);canvas.removeEventListener('mousemove',canvas.__rgraph_mousemove_event_listener_installed__,false);canvas.removeEventListener('mousedown',canvas.__rgraph_mousedown_event_listener_installed__,false);canvas.removeEventListener('click',canvas.__rgraph_click_event_listener_installed__,false);}};RG.hidePalette=RG.HidePalette=function()
{var div=RG.Registry.get('palette');if(typeof div=='object'&&div){div.style.visibility='hidden';div.style.display='none';RG.Registry.set('palette',null);}};RG.random=function(min,max)
{var dp=arguments[2]?arguments[2]:0;var r=ma.random();return Number((((max-min)*r)+min).toFixed(dp));};RG.arrayRand=RG.arrayRandom=RG.random.array=function(num,min,max)
{for(var i=0,arr=[];i<num;i+=1){arr.push(RG.random(min,max,arguments[3]));}
return arr;};RG.noShadow=RG.NoShadow=function(obj)
{var co=obj.context;co.shadowColor='rgba(0,0,0,0)';co.shadowBlur=0;co.shadowOffsetX=0;co.shadowOffsetY=0;};RG.setShadow=RG.SetShadow=function(obj,color,offsetx,offsety,blur)
{var co=obj.context;co.shadowColor=color;co.shadowOffsetX=offsetx;co.shadowOffsetY=offsety;co.shadowBlur=blur;};RG.Registry.set=RG.Registry.Set=function(name,value)
{name=name.replace(/([A-Z])/g,function(str)
{return'.'+String(RegExp.$1).toLowerCase();});if(name.substr(0,6)!=='chart.'){name='chart.'+name;}
RG.Registry.store[name]=value;return value;};RG.Registry.get=RG.Registry.Get=function(name)
{name=name.replace(/([A-Z])/g,function(str)
{return'.'+String(RegExp.$1).toLowerCase();});if(name.substr(0,6)!=='chart.'){name='chart.'+name;}
return RG.Registry.store[name];};RG.degrees2Radians=function(deg)
{return deg*(RG.PI/180);};RG.log=function(n,base)
{return ma.log(n)/(base?ma.log(base):1);};RG.isArray=RG.is_array=function(obj)
{if(obj&&obj.constructor){var pos=obj.constructor.toString().indexOf('Array');}else{return false;}
return obj!=null&&typeof pos==='number'&&pos>0&&pos<20;};RG.trim=function(str)
{return RG.ltrim(RG.rtrim(str));};RG.ltrim=function(str)
{return str.replace(/^(\s|\0)+/,'');};RG.rtrim=function(str)
{return str.replace(/(\s|\0)+$/,'');};RG.isNull=RG.is_null=function(arg)
{if(arg==null||typeof arg==='object'&&!arg){return true;}
return false;};RG.async=RG.Async=function(func)
{return setTimeout(func,arguments[1]?arguments[1]:1);};RG.reset=RG.Reset=function(ca)
{ca.width=ca.width;RG.ObjectRegistry.clear(ca);ca.__rgraph_aa_translated__=false;if(RG.text2.domNodeCache&&RG.text2.domNodeCache.reset){RG.text2.domNodeCache.reset(ca);}
if(!RG.text2.domNodeCache){RG.text2.domNodeCache=[];}
if(!RG.text2.domNodeDimensionCache){RG.text2.domNodeDimensionCache=[];}
RG.text2.domNodeCache[ca.id]=[];RG.text2.domNodeDimensionCache[ca.id]=[];};RG.att=RG.attribution=function(obj)
{var ca=obj.canvas,co=obj.context,prop=obj.properties;if(!ca||!co){return;}
var width=ca.width,height=ca.height,wrapper=document.getElementById('cvs').__object__.canvas.parentNode,text=prop['chart.attribution.text']||'Free Charts with RGraph.net',x=prop['chart.attribution.x'],y=prop['chart.attribution.y'],bold=prop['chart.attribution.bold'],italic=prop['chart.attribution.italic'],font=prop['chart.attribution.font']||'sans-serif',size=prop['chart.attribution.size']||8,underline=prop['chart.attribution.underline']?'underline':'none',color=typeof prop['chart.attribution.color']==='string'?prop['chart.attribution.color']:'',href=typeof prop['chart.attribution.href']==='string'?prop['chart.attribution.href']:'http://www.rgraph.net/canvas/index.html';if(wrapper.attribution_node){return;}
var measurements=RG.measureText(text,bold,font,size);var a=document.createElement('A');a.href=href;a.innerHTML=text;a.target='_blank';a.style.position='absolute';a.style.left=typeof x==='number'?x:wrapper.offsetWidth-measurements[0]-5+'px';a.style.top=typeof y==='number'?y:wrapper.offsetHeight-measurements[1]+'px';a.style.fontSize=size+'pt';a.style.fontStyle=typeof italic==='boolean'?(italic?'italic':''):'italic',a.style.fontWeight=bold?'bold':'',a.style.textDecoration=underline;a.style.fontFamily=font;a.style.color=color;wrapper.appendChild(a);wrapper.attribution_node=a;};RG.getCanvasTag=function(id)
{id=typeof id==='object'?id.id:id;var canvas=doc.getElementById(id);return[id,canvas];};RG.Effects.updateCanvas=RG.Effects.UpdateCanvas=function(func)
{win.requestAnimationFrame=win.requestAnimationFrame||win.webkitRequestAnimationFrame||win.msRequestAnimationFrame||win.mozRequestAnimationFrame||(function(func){setTimeout(func,16.666);});win.requestAnimationFrame(func);};RG.Effects.getEasingMultiplier=function(frames,frame)
{return ma.pow(ma.sin((frame/frames)*RG.HALFPI),3);};RG.stringsToNumbers=function(str)
{var sep=arguments[1]||',';if(typeof str==='number'){return str;}
if(typeof str==='string'){if(str.indexOf(sep)!=-1){str=str.split(sep);}else{str=parseFloat(str);}}
if(typeof str==='object'&&!RG.isNull(str)){for(var i=0,len=str.length;i<len;i+=1){str[i]=parseFloat(str[i]);}}
return str;};RG.cachedDraw=function(obj,id,func)
{if(!RG.cache[id]){RG.cache[id]={};RG.cache[id].object=obj;RG.cache[id].canvas=document.createElement('canvas');RG.cache[id].canvas.setAttribute('width',obj.canvas.width);RG.cache[id].canvas.setAttribute('height',obj.canvas.height);RG.cache[id].canvas.setAttribute('id','background_cached_canvas'+obj.canvas.id);RG.cache[id].canvas.__object__=obj;RG.cache[id].context=RG.cache[id].canvas.getContext('2d');RG.cache[id].context.translate(0.5,0.5);func(obj,RG.cache[id].canvas,RG.cache[id].context);}
obj.context.drawImage(RG.cache[id].canvas,-0.5,-0.5);};RG.parseObjectStyleConfig=function(obj,config)
{var recurse=function(obj,config,name,settings)
{var i;for(key in config){if(key.match(/^exec[0-9]*$/)){(config[key])(obj,settings);continue;}
var isObject=false;var isArray=false;var value=config[key];while(key.match(/([A-Z])/)){key=key.replace(/([A-Z])/,'.'+RegExp.$1.toLowerCase());}
if(!RG.isNull(value)&&value.constructor){isObject=value.constructor.toString().indexOf('Object')>0;isArray=value.constructor.toString().indexOf('Array')>0;}
if(isObject&&!isArray){recurse(obj,config[key],name+'.'+key,settings);}else if(key==='self'){settings[name]=value;}else{settings[name+'.'+key]=value;}}
return settings;};var settings=recurse(obj,config,'chart',{});for(key in settings){if(typeof key==='string'){obj.set(key,settings[key]);}}};RG.path2=function(co,p)
{var args=arguments;if(typeof p==='string'){p=splitstring(p);}
RG.path2.last=RG.arrayClone(p);for(var i=0,len=p.length;i<len;i+=1){switch(p[i]){case'b':co.beginPath();break;case'c':co.closePath();break;case'm':co.moveTo(parseFloat(p[i+1]),parseFloat(p[i+2]));i+=2;break;case'l':co.lineTo(parseFloat(p[i+1]),parseFloat(p[i+2]));i+=2;break;case's':if(p[i+1])co.strokeStyle=p[i+1];co.stroke();i++;break;case'f':if(p[i+1]){co.fillStyle=p[i+1];}co.fill();i++;break;case'qc':co.quadraticCurveTo(parseFloat(p[i+1]),parseFloat(p[i+2]),parseFloat(p[i+3]),parseFloat(p[i+4]));i+=4;break;case'bc':co.bezierCurveTo(parseFloat(p[i+1]),parseFloat(p[i+2]),parseFloat(p[i+3]),parseFloat(p[i+4]),parseFloat(p[i+5]),parseFloat(p[i+6]));i+=6;break;case'r':co.rect(parseFloat(p[i+1]),parseFloat(p[i+2]),parseFloat(p[i+3]),parseFloat(p[i+4]));i+=4;break;case'a':co.arc(parseFloat(p[i+1]),parseFloat(p[i+2]),parseFloat(p[i+3]),parseFloat(p[i+4]),parseFloat(p[i+5]),p[i+6]==='true'||p[i+6]===true||p[i+6]===1||p[i+6]==='1'?true:false);i+=6;break;case'at':co.arcTo(parseFloat(p[i+1]),parseFloat(p[i+2]),parseFloat(p[i+3]),parseFloat(p[i+4]),parseFloat(p[i+5]));i+=5;break;case'lw':co.lineWidth=parseFloat(p[i+1]);i++;break;case'e':co.ellipse(parseFloat(p[i+1]),parseFloat(p[i+2]),parseFloat(p[i+3]),parseFloat(p[i+4]),parseFloat(p[i+5]),parseFloat(p[i+6]),parseFloat(p[i+7]),p[i+8]==='true'?true:false);i+=8;break;case'lj':co.lineJoin=p[i+1];i++;break;case'lc':co.lineCap=p[i+1];i++;break;case'sc':co.shadowColor=p[i+1];i++;break;case'sb':co.shadowBlur=parseFloat(p[i+1]);i++;break;case'sx':co.shadowOffsetX=parseFloat(p[i+1]);i++;break;case'sy':co.shadowOffsetY=parseFloat(p[i+1]);i++;break;case'fs':co.fillStyle=p[i+1];i++;break;case'ss':co.strokeStyle=p[i+1];i++;break;case'fr':co.fillRect(parseFloat(p[i+1]),parseFloat(p[i+2]),parseFloat(p[i+3]),parseFloat(p[i+4]));i+=4;break;case'sr':co.strokeRect(parseFloat(p[i+1]),parseFloat(p[i+2]),parseFloat(p[i+3]),parseFloat(p[i+4]));i+=4;break;case'cl':co.clip();break;case'sa':co.save();break;case'rs':co.restore();break;case'tr':co.translate(parseFloat(p[i+1]),parseFloat(p[i+2]));i+=2;break;case'sl':co.scale(parseFloat(p[i+1]),parseFloat(p[i+2]));i+=2;break;case'ro':co.rotate(parseFloat(p[i+1]));i++;break;case'tf':co.transform(parseFloat(p[i+1]),parseFloat(p[i+2]),parseFloat(p[i+3]),parseFloat(p[i+4]),parseFloat(p[i+5]),parseFloat(p[i+6]));i+=6;break;case'stf':co.setTransform(parseFloat(p[i+1]),parseFloat(p[i+2]),parseFloat(p[i+3]),parseFloat(p[i+4]),parseFloat(p[i+5]),parseFloat(p[i+6]));i+=6;break;case'cr':co.clearRect(parseFloat(p[i+1]),parseFloat(p[i+2]),parseFloat(p[i+3]),parseFloat(p[i+4]));i+=4;break;case'ld':var parts=p[i+1];co.setLineDash(parts);i+=1;break;case'ldo':co.lineDashOffset=p[i+1];i++;break;case'fo':co.font=p[i+1];i++;break;case'ft':co.fillText(p[i+1],parseFloat(p[i+2]),parseFloat(p[i+3]));i+=3;break;case'st':co.strokeText(p[i+1],parseFloat(p[i+2]),parseFloat(p[i+3]));i+=3;break;case'ta':co.textAlign=p[i+1];i++;break;case'tbl':co.textBaseline=p[i+1];i++;break;case'ga':co.globalAlpha=parseFloat(p[i+1]);i++;break;case'gco':co.globalCompositeOperation=p[i+1];i++;break;case'fu':(p[i+1])(co.canvas.__object__);i++;break;case'':break;default:alert('[ERROR] Unknown option: '+p[i]);}}
function splitstring(p)
{var ret=[],buffer='',inquote=false,quote='',substitutionIndex=2;for(var i=0;i<p.length;i+=1){var chr=p[i],isWS=chr.match(/ /);if(isWS){if(!inquote){if(buffer[0]==='"'||buffer[0]==="'"){buffer=buffer.substr(1,buffer.length-2);}
if(buffer.trim()==='%'&&typeof args[substitutionIndex]!=='undefined'){buffer=args[substitutionIndex++];}
ret.push(buffer);buffer='';}else{buffer+=chr;}}else{if(chr==="'"||chr==='"'){inquote=!inquote;}
buffer+=chr;}}
if(buffer.trim()==='%'&&args[substitutionIndex]){buffer=args[substitutionIndex++];}
ret.push(buffer);return ret;}};RG.wrap=function(){};})(window,document);window.$p=function(v)
{RGraph.pr(arguments[0],arguments[1],arguments[3]);};window.$a=function(v)
{alert(v);};window.$cl=function(v)
{return console.log(v);};if(!String.prototype.format){String.prototype.format=function()
{var args=arguments;return this.replace(/{(\d+)}/g,function(str,idx)
{return typeof args[idx-1]!=='undefined'?args[idx-1]:str;});};}