
RGraph=window.RGraph||{isRGraph:true};RGraph.Drawing=RGraph.Drawing||{};RGraph.Drawing.XAxis=function(conf)
{if(typeof conf==='object'&&typeof conf.y==='number'&&typeof conf.id==='string'){var id=conf.id
var y=conf.y;var parseConfObjectForOptions=true;}else{var id=conf;var y=arguments[1];}
this.id=id;this.canvas=document.getElementById(this.id);this.context=this.canvas.getContext('2d');this.canvas.__object__=this;this.y=y;this.coords=[];this.coordsText=[];this.original_colors=[];this.firstDraw=true;this.type='drawing.xaxis';this.isRGraph=true;this.uid=RGraph.CreateUID();this.canvas.uid=this.canvas.uid?this.canvas.uid:RGraph.CreateUID();this.properties={'chart.gutter.left':25,'chart.gutter.right':25,'chart.labels':null,'chart.labels.position':'section','chart.colors':['black'],'chart.title.color':null,'chart.text.color':null,'chart.text.font':'Segoe UI, Arial, Verdana, sans-serif','chart.text.size':12,'chart.text.accessible':true,'chart.text.accessible.overflow':'visible','chart.text.accessible.pointerevents':true,'chart.align':'bottom','chart.numlabels':5,'chart.scale.visible':true,'chart.scale.formatter':null,'chart.scale.decimals':0,'chart.scale.point':'.','chart.scale.thousand':',','chart.scale.invert':false,'chart.scale.zerostart':true,'chart.units.pre':'','chart.units.post':'','chart.title':'','chart.numticks':null,'chart.hmargin':0,'chart.linewidth':1,'chart.noendtick.left':false,'chart.noendtick.right':false,'chart.noxaxis':false,'chart.max':null,'chart.min':0,'chart.tooltips':null,'chart.tooltips.effect':'fade','chart.tooltips.css.class':'RGraph_tooltip','chart.tooltips.event':'onclick','chart.events.click':null,'chart.events.mousemove':null,'chart.xaxispos':'bottom','chart.yaxispos':'left','chart.clearto':'rgba(0,0,0,0)'}
if(!this.canvas){alert('[DRAWING.XAXIS] No canvas support');return;}
this.$0={};if(!this.canvas.__rgraph_aa_translated__){this.context.translate(0.5,0.5);this.canvas.__rgraph_aa_translated__=true;}
var RG=RGraph,ca=this.canvas,co=ca.getContext('2d'),prop=this.properties,pa2=RG.path2,win=window,doc=document,ma=Math
if(RG.Effects&&typeof RG.Effects.decorate==='function'){RG.Effects.decorate(this);}
this.set=this.Set=function(name)
{var value=typeof arguments[1]==='undefined'?null:arguments[1];if(arguments.length===1&&typeof name==='object'){RG.parseObjectStyleConfig(this,name);return this;}
if(name.substr(0,6)!='chart.'){name='chart.'+name;}
while(name.match(/([A-Z])/)){name=name.replace(/([A-Z])/,'.'+RegExp.$1.toLowerCase());}
if(name=='chart.labels'&&!prop['chart.numxticks']){prop['chart.numxticks']=value.length;}
prop[name]=value;return this;};this.get=this.Get=function(name)
{if(name.substr(0,6)!='chart.'){name='chart.'+name;}
while(name.match(/([A-Z])/)){name=name.replace(/([A-Z])/,'.'+RegExp.$1.toLowerCase());}
return prop[name.toLowerCase()];};this.draw=this.Draw=function()
{RG.FireCustomEvent(this,'onbeforedraw');this.coordsText=[];this.gutterLeft=prop['chart.gutter.left'];this.gutterRight=prop['chart.gutter.right'];if(!prop['chart.text.color'])prop['chart.text.color']=prop['chart.colors'][0];if(!prop['chart.title.color'])prop['chart.title.color']=prop['chart.colors'][0];if(!this.colorsParsed){this.parseColors();this.colorsParsed=true;}
this.DrawXAxis();RG.InstallEventListeners(this);if(this.firstDraw){this.firstDraw=false;RG.fireCustomEvent(this,'onfirstdraw');this.firstDrawFunc();}
RG.FireCustomEvent(this,'ondraw');return this;};this.exec=function(func)
{func(this);return this;};this.getObjectByXY=function(e)
{if(this.getShape(e)){return this;}};this.getShape=function(e)
{var mouseXY=RG.getMouseXY(e);var mouseX=mouseXY[0];var mouseY=mouseXY[1];if(mouseX>=this.gutterLeft&&mouseX<=(ca.width-this.gutterRight)&&mouseY>=this.y-(prop['chart.align']=='top'?(prop['chart.text.size']*1.5)+5:0)&&mouseY<=(this.y+(prop['chart.align']=='top'?0:(prop['chart.text.size']*1.5)+5))){var x=this.gutterLeft;var y=this.y;var w=ca.width-this.gutterLeft-this.gutterRight;var h=15;return{0:this,1:x,2:y,3:w,4:h,5:0,'object':this,'x':x,'y':y,'width':w,'height':h,'index':0,'tooltip':prop['chart.tooltips']?prop['chart.tooltips'][0]:null};}
return null;};this.highlight=this.Highlight=function(shape)
{if(typeof prop['chart.highlight.style']==='function'){(prop['chart.highlight.style'])(shape);}};this.parseColors=function()
{if(this.original_colors.length===0){this.original_colors['chart.colors']=RG.array_clone(prop['chart.colors']);}
prop['chart.colors'][0]=this.parseSingleColorForGradient(prop['chart.colors'][0]);};this.reset=function()
{};this.parseSingleColorForGradient=function(color)
{if(!color){return color;}
if(typeof color==='string'&&color.match(/^gradient\((.*)\)$/i)){var parts=RegExp.$1.split(':');var grad=co.createLinearGradient(prop['chart.gutter.left'],0,ca.width-prop['chart.gutter.right'],0);var diff=1/(parts.length-1);grad.addColorStop(0,RG.trim(parts[0]));for(var j=1,len=parts.length;j<len;++j){grad.addColorStop(j*diff,RG.trim(parts[j]));}}
return grad?grad:color;};this.drawXAxis=this.DrawXAxis=function()
{var gutterLeft=prop['chart.gutter.left'],gutterRight=prop['chart.gutter.right'],x=this.gutterLeft,y=this.y,min=+prop['chart.min'],max=+prop['chart.max'],labels=prop['chart.labels'],labels_position=prop['chart.labels.position'],color=prop['chart.colors'][0],title_color=prop['chart.title.color'],label_color=prop['chart.text.color'],width=ca.width-this.gutterLeft-this.gutterRight,font=prop['chart.text.font'],size=prop['chart.text.size'],align=prop['chart.align'],numlabels=prop['chart.numlabels'],formatter=prop['chart.scale.formatter'],decimals=Number(prop['chart.scale.decimals']),invert=prop['chart.scale.invert'],scale_visible=prop['chart.scale.visible'],units_pre=prop['chart.units.pre'],units_post=prop['chart.units.post'],title=prop['chart.title']
numticks=prop['chart.numticks'],hmargin=prop['chart.hmargin'],linewidth=prop['chart.linewidth'],noleftendtick=prop['chart.noendtick.left'],norightendtick=prop['chart.noendtick.right'],noxaxis=prop['chart.noxaxis'],xaxispos=prop['chart.xaxispos'],yaxispos=prop['chart.yaxispos']
if(RG.is_null(numticks)){if(labels&&labels.length){numticks=labels.length;}else if(!labels&&max!=0){numticks=10;}else{numticks=numlabels;}}
co.lineWidth=linewidth+0.001;co.strokeStyle=color;if(!noxaxis){pa2(co,['b','m',x,ma.round(y),'l',x+width,ma.round(y),'s',co.strokeStyle]);co.beginPath();for(var i=(noleftendtick?1:0);i<=(numticks-(norightendtick?1:0));++i){co.moveTo(ma.round(x+((width/numticks)*i)),xaxispos=='center'?(align=='bottom'?y-3:y+3):y);co.lineTo(ma.round(x+((width/numticks)*i)),y+(align=='bottom'?3:-3));}
co.stroke();}
co.fillStyle=label_color;if(labels){numlabels=labels.length;var h=0;var l=0;var single_line=RG.MeasureText('Mg',false,font,size);for(var i=0,len=labels.length;i<len;++i){var dimensions=RG.MeasureText(labels[i],false,font,size);var h=ma.max(h,dimensions[1]);var l=ma.max(l,labels[i].split('\r\n').length);}
for(var i=0,len=labels.length;i<len;++i){RG.text2(this,{'font':font,'size':size,'x':labels_position=='edge'?((((width-hmargin-hmargin)/(labels.length-1))*i)+gutterLeft+hmargin):((((width-hmargin-hmargin)/labels.length)*i)+((width/labels.length)/2)+gutterLeft+hmargin),'y':align=='bottom'?y+3:y-3-h+single_line[1],'text':String(labels[i]),'valign':align=='bottom'?'top':'bottom','halign':'center','tag':'labels'});}}else if(scale_visible){if(max===null){alert('[DRAWING.XAXIS] If not specifying axis.labels you must specify axis.max!');}
if(yaxispos=='center'){width/=2;var additionalX=width;}else{var additionalX=0;}
for(var i=0;i<=numlabels;++i){if(i==0&&!prop['chart.scale.zerostart']){continue;}
var original=(((max-min)/numlabels)*i)+min;var hmargin=prop['chart.hmargin'];var text=String(typeof(formatter)=='function'?formatter(this,original):RG.numberFormat(this,original.toFixed(original===0?0:decimals),units_pre,units_post));if(invert){var x=((width-hmargin-((width-hmargin-hmargin)/numlabels)*i))+gutterLeft+additionalX;}else{var x=(((width-hmargin-hmargin)/numlabels)*i)+gutterLeft+hmargin+additionalX;}
RG.Text2(this,{'font':font,'size':size,'x':x,'y':align=='bottom'?y+3:y-3,'text':text,'valign':align=='bottom'?'top':'bottom','halign':'center','tag':'scale'});}
if(yaxispos=='center'){for(var i=0;i<numlabels;++i){var original=(((max-min)/numlabels)*(numlabels-i))+min;var hmargin=prop['chart.hmargin'];var text=String(typeof(formatter)=='function'?formatter(this,original):RG.number_format(this,original.toFixed(decimals),units_pre,units_post));if(invert){var x=((width-hmargin-((width-hmargin-hmargin)/numlabels)*i))+gutterLeft;}else{var x=(((width-hmargin-hmargin)/numlabels)*i)+gutterLeft+hmargin;}
RG.text2(this,{'font':font,'size':size,'x':x,'y':align=='bottom'?y+size+2:y-size-2,'text':'-'+text,'valign':'center','halign':'center','tag':'scale'});}}}
if(title){var dimensions=RG.MeasureText(title,false,font,size+2);co.fillStyle=title_color
RG.Text2(this,{'font':font,'size':size+2,'x':(ca.width-this.gutterLeft-this.gutterRight)/2+this.gutterLeft,'y':align=='bottom'?y+dimensions[1]+10:y-dimensions[1]-10,'text':title,'valign':'center','halign':'center','tag':'title'});}};this.on=function(type,func)
{if(type.substr(0,2)!=='on'){type='on'+type;}
if(typeof this[type]!=='function'){this[type]=func;}else{RG.addCustomEventListener(this,type,func);}
return this;};this.firstDrawFunc=function()
{};RG.att(ca);RG.Register(this);if(parseConfObjectForOptions){RG.parseObjectStyleConfig(this,conf.options);}};