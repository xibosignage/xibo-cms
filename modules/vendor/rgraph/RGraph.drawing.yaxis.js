
RGraph=window.RGraph||{isRGraph:true};RGraph.Drawing=RGraph.Drawing||{};RGraph.Drawing.YAxis=function(conf)
{if(typeof conf==='object'&&typeof conf.x==='number'&&typeof conf.id==='string'){var id=conf.id
var x=conf.x;var parseConfObjectForOptions=true;}else{var id=conf;var x=arguments[1];}
this.id=id;this.canvas=document.getElementById(this.id);this.context=this.canvas.getContext("2d");this.canvas.__object__=this;this.x=x;this.coords=[];this.coordsText=[];this.original_colors=[];this.maxLabelLength=0;this.firstDraw=true;this.type='drawing.yaxis';this.isRGraph=true;this.uid=RGraph.CreateUID();this.canvas.uid=this.canvas.uid?this.canvas.uid:RGraph.CreateUID();this.properties={'chart.gutter.top':25,'chart.gutter.bottom':30,'chart.min':0,'chart.max':null,'chart.colors':['black'],'chart.title':'','chart.title.color':null,'chart.numticks':5,'chart.numlabels':5,'chart.labels.specific':null,'chart.text.font':'Segoe UI, Arial, Verdana, sans-serif','chart.text.size':12,'chart.text.color':null,'chart.text.accessible':true,'chart.text.accessible.overflow':'visible','chart.text.accessible.pointerevents':true,'chart.align':'left','hart.scale.formatter':null,'chart.scale.point':'.','chart.scale.decimals':0,'chart.scale.decimals':0,'chart.scale.point':'.','chart.scale.invert':false,'chart.scale.zerostart':true,'chart.scale.visible':true,'chart.units.pre':'','chart.units.post':'','chart.linewidth':1,'chart.noendtick.top':false,'chart.noendtick.bottom':false,'chart.noyaxis':false,'chart.tooltips':null,'chart.tooltips.effect':'fade','chart.tooltips.css.class':'RGraph_tooltip','chart.tooltips.event':'onclick','chart.xaxispos':'bottom','chart.events.click':null,'chart.events.mousemove':null,'chart.clearto':'rgba(0,0,0,0)'}
if(!this.canvas){alert('[DRAWING.YAXIS] No canvas support');return;}
this.$0={};if(!this.canvas.__rgraph_aa_translated__){this.context.translate(0.5,0.5);this.canvas.__rgraph_aa_translated__=true;}
var RG=RGraph,ca=this.canvas,co=ca.getContext('2d'),prop=this.properties,pa2=RG.path2,win=window,doc=document,ma=Math
if(RG.Effects&&typeof RG.Effects.decorate==='function'){RG.Effects.decorate(this);}
this.set=this.Set=function(name)
{var value=typeof arguments[1]==='undefined'?null:arguments[1];if(arguments.length===1&&typeof name==='object'){RG.parseObjectStyleConfig(this,name);return this;}
if(name.substr(0,6)!='chart.'){name='chart.'+name;}
while(name.match(/([A-Z])/)){name=name.replace(/([A-Z])/,'.'+RegExp.$1.toLowerCase());}
prop[name]=value;return this;};this.get=this.Get=function(name)
{if(name.substr(0,6)!='chart.'){name='chart.'+name;}
while(name.match(/([A-Z])/)){name=name.replace(/([A-Z])/,'.'+RegExp.$1.toLowerCase());}
return prop[name.toLowerCase()];};this.draw=this.Draw=function()
{RG.fireCustomEvent(this,'onbeforedraw');this.gutterTop=prop['chart.gutter.top'];this.gutterBottom=prop['chart.gutter.bottom'];this.coordsText=[];if(!prop['chart.text.color'])prop['chart.text.color']=prop['chart.colors'][0];if(!prop['chart.title.color'])prop['chart.title.color']=prop['chart.colors'][0];if(!this.colorsParsed){this.parseColors();this.colorsParsed=true;}
this.drawYAxis();RG.InstallEventListeners(this);if(this.firstDraw){this.firstDraw=false;RG.fireCustomEvent(this,'onfirstdraw');this.firstDrawFunc();}
RG.FireCustomEvent(this,'ondraw');return this;};this.exec=function(func)
{func(this);return this;};this.getObjectByXY=function(e)
{if(this.getShape(e)){return this;}};this.getShape=function(e)
{var mouseXY=RG.getMouseXY(e);var mouseX=mouseXY[0];var mouseY=mouseXY[1];if(mouseX>=this.x-(prop['chart.align']=='right'?0:this.getWidth())&&mouseX<=this.x+(prop['chart.align']=='right'?this.getWidth():0)&&mouseY>=this.gutterTop&&mouseY<=(ca.height-this.gutterBottom)){var x=this.x;var y=this.gutterTop;var w=15;;var h=ca.height-this.gutterTop-this.gutterBottom;return{0:this,1:x,2:y,3:w,4:h,5:0,'object':this,'x':x,'y':y,'width':w,'height':h,'index':0,'tooltip':prop['chart.tooltips']?prop['chart.tooltips'][0]:null};}
return null;};this.highlight=this.Highlight=function(shape)
{if(typeof prop['chart.highlight.style']==='function'){(prop['chart.highlight.style'])(shape);}};this.parseColors=function()
{if(this.original_colors.length===0){this.original_colors['chart.colors']=RG.array_clone(prop['chart.colors']);}
prop['chart.colors'][0]=this.parseSingleColorForGradient(prop['chart.colors'][0]);};this.reset=function()
{};this.parseSingleColorForGradient=function(color)
{if(!color){return color;}
if(typeof color==='string'&&color.match(/^gradient\((.*)\)$/i)){var parts=RegExp.$1.split(':');var grad=co.createLinearGradient(0,prop['chart.gutter.top'],0,ca.height-this.gutterBottom);var diff=1/(parts.length-1);grad.addColorStop(0,RG.trim(parts[0]));for(var j=1;j<parts.length;++j){grad.addColorStop(j*diff,RG.trim(parts[j]));}}
return grad?grad:color;};this.drawYAxis=this.DrawYAxis=function()
{for(i in prop){if(typeof i=='string'){var key=i.replace(/^chart\./,'axis.');prop[key]=prop[i];}}
var x=this.x,y=this.gutterTop,height=ca.height-this.gutterBottom-this.gutterTop,min=+prop['chart.min']?+prop['chart.min']:0,max=+prop['chart.max'],title=prop['chart.title']?prop['chart.title']:'',color=prop['chart.colors']?prop['chart.colors'][0]:'black',title_color=prop['chart.title.color']?prop['chart.title.color']:color,label_color=prop['chart.text.color']?prop['chart.text.color']:color,numticks=typeof(prop['chart.numticks'])=='number'?prop['chart.numticks']:10,labels_specific=prop['chart.labels.specific'],numlabels=prop['chart.numlabels']?prop['chart.numlabels']:5,font=prop['chart.text.font']?prop['chart.text.font']:'Arial',size=prop['chart.text.size']?prop['chart.text.size']:10
align=typeof(prop['chart.align'])=='string'?prop['chart.align']:'left',formatter=prop['chart.scale.formatter'],decimals=prop['chart.scale.decimals'],invert=prop['chart.scale.invert'],scale_visible=prop['chart.scale.visible'],units_pre=prop['chart.units.pre'],units_post=prop['chart.units.post'],linewidth=prop['chart.linewidth']?prop['chart.linewidth']:1,notopendtick=prop['chart.noendtick.top'],nobottomendtick=prop['chart.noendtick.bottom'],noyaxis=prop['chart.noyaxis'],xaxispos=prop['chart.xaxispos']
co.lineWidth=linewidth+0.001;co.strokeStyle=color;if(!noyaxis){pa2(co,['b','m',Math.round(x),y,'l',Math.round(x),y+height,'s',color]);if(numticks){var gap=(xaxispos=='center'?height/2:height)/numticks;var halfheight=height/2;co.beginPath();for(var i=(notopendtick?1:0);i<=(numticks-(nobottomendtick||xaxispos=='center'?1:0));++i){pa2(co,['m',align=='right'?x+3:x-3,Math.round(y+(gap*i)),'l',x,Math.round(y+(gap*i))]);}
if(xaxispos=='center'){for(var i=1;i<=numticks;++i){pa2(co,['m',align=='right'?x+3:x-3,Math.round(y+halfheight+(gap*i)),'l',x,Math.round(y+halfheight+(gap*i))]);}}
co.stroke();}}
co.fillStyle=label_color;var text_len=0;if(scale_visible){if(labels_specific&&labels_specific.length){var text_len=0;for(var i=0,len=labels_specific.length;i<len;i+=1){text_len=ma.max(text_len,co.measureText(labels_specific[i]).width);}
for(var i=0,len=labels_specific.length;i<len;++i){var gap=(len-1)>0?(height/(len-1)):0;if(xaxispos=='center'){gap/=2;}
RG.text2(this,{'font':font,'size':size,'x':x-(align=='right'?-5:5),'y':(i*gap)+this.gutterTop,'text':labels_specific[i],'valign':'center','halign':align=='right'?'left':'right','tag':'scale'});this.maxLabelLength=ma.max(this.maxLabelLength,co.measureText(labels_specific[i]).width);}
if(xaxispos=='center'){for(var i=(labels_specific.length-2);i>=0;--i){RG.text2(this,{'font':font,'size':size,'x':x-(align=='right'?-5:5),'y':ca.height-this.gutterBottom-(i*gap),'text':labels_specific[i],'valign':'center','halign':align=='right'?'left':'right','tag':'scale'});}}}else{for(var i=0;i<=numlabels;++i){var original=((max-min)*((numlabels-i)/numlabels))+min;if(original==0&&prop['chart.scale.zerostart']==false){continue;}
var text=RG.numberFormat(this,original.toFixed(original===0?0:decimals),units_pre,units_post);var text=String(typeof(formatter)=='function'?formatter(this,original):text);var text_len=ma.max(text_len,co.measureText(text).width);this.maxLabelLength=text_len;if(invert){var y=height-((height/numlabels)*i);}else{var y=(height/numlabels)*i;}
if(prop['chart.xaxispos']=='center'){y=y/2;}
text=text.replace(/^-,([0-9])/,'-$1');RG.text2(this,{'font':font,'size':size,'x':x-(align=='right'?-5:5),'y':y+this.gutterTop,'text':text,'valign':'center','halign':align=='right'?'left':'right','tag':'scale'});if(prop['chart.xaxispos']=='center'&&i<numlabels){RG.Text2(this,{'font':font,'size':size,'x':x-(align=='right'?-5:5),'y':ca.height-this.gutterBottom-y,'text':'-'+text,'valign':'center','halign':align=='right'?'left':'right','tag':'scale'});}}}}
if(title){co.beginPath();co.fillStyle=title_color;if(labels_specific){var width=0;for(var i=0,len=labels_specific.length;i<len;i+=1){width=Math.max(width,co.measureText(labels_specific[i]).width);}}else{var width=co.measureText(prop['chart.units.pre']+prop['chart.max'].toFixed(prop['chart.scale.decimals'])+prop['chart.units.post']).width;}
RG.text2(this,{font:font,size:size+2,x:align=='right'?x+width+8:x-width-8,y:height/2+this.gutterTop,text:title,valign:'bottom',halign:'center',angle:align=='right'?90:-90,accessible:false});co.stroke();}};this.getWidth=function()
{var width=this.maxLabelLength;if(prop['chart.title']&&prop['chart.title'].length){width+=(prop['chart.text.size']*1.5);}
this.width=width;return width;};this.on=function(type,func)
{if(type.substr(0,2)!=='on'){type='on'+type;}
if(typeof this[type]!=='function'){this[type]=func;}else{RG.addCustomEventListener(this,type,func);}
return this;};this.firstDrawFunc=function()
{};RG.att(ca);RG.Register(this);if(parseConfObjectForOptions){RG.parseObjectStyleConfig(this,conf.options);}};