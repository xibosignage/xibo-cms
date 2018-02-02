
RGraph=window.RGraph||{isRGraph:true};(function(win,doc,undefined)
{var RG=RGraph,ua=navigator.userAgent,ma=Math;RG.tooltips={};RG.tooltips.style={display:'inline-block',position:'absolute',padding:'6px',fontFamily:'Arial',fontSize:'10pt',fontWeight:'normal',textAlign:'center',left:0,top:0,backgroundColor:'rgb(255,255,239)',color:'black',visibility:'visible',zIndex:3,borderRadius:'5px',boxShadow:'rgba(96,96,96,0.5) 0 0 5px',opacity:0};RG.tooltip=RG.Tooltip=function(obj,text,x,y,idx,e)
{if(RG.trim(text).length===0){return;}
RG.fireCustomEvent(obj,'onbeforetooltip');if(typeof(obj.get('chart.tooltips.override'))=='function'){return obj.get('chart.tooltips.override')(obj,text,x,y,idx);}
var originalX=x;var originalY=y;text=RG.getTooltipTextFromDIV(text);var timers=RG.Registry.Get('chart.tooltip.timers');if(timers&&timers.length){for(i=0;i<timers.length;++i){clearTimeout(timers[i]);}}
RG.Registry.Set('chart.tooltip.timers',[]);if(obj.get('chart.contextmenu')){RG.hideContext();}
var tooltipObj=document.createElement('DIV');tooltipObj.className=obj.get('chart.tooltips.css.class');for(var i in RGraph.tooltips.style){if(typeof i==='string'){tooltipObj.style[i]=RGraph.tooltips.style[i];}}
tooltipObj.innerHTML=text;tooltipObj.__text__=text;tooltipObj.__canvas__=obj.canvas;tooltipObj.id='__rgraph_tooltip_'+obj.canvas.id+'_'+obj.uid+'_'+idx;tooltipObj.__event__=obj.Get('chart.tooltips.event')||'click';tooltipObj.__object__=obj;if(typeof idx==='number'){tooltipObj.__index__=idx;origIdx=idx;}
if(obj.type==='line'||obj.type==='radar'){for(var ds=0;ds<obj.data.length;++ds){if(idx>=obj.data[ds].length){idx-=obj.data[ds].length;}else{break;}}
tooltipObj.__dataset__=ds;tooltipObj.__index2__=idx;}
document.body.appendChild(tooltipObj);var width=tooltipObj.offsetWidth;var height=tooltipObj.offsetHeight;tooltipObj.style.width=width+'px';var mouseXY=RG.getMouseXY(e);var canvasXY=RG.getCanvasXY(obj.canvas);tooltipObj.style.left=e.pageX-(parseFloat(tooltipObj.style.paddingLeft)+(width/2))+'px';tooltipObj.style.top=e.pageY-height-10+'px';if(parseFloat(tooltipObj.style.left)<=5){tooltipObj.style.left='5px';}
if(parseFloat(tooltipObj.style.left)+parseFloat(tooltipObj.style.width)>window.innerWidth){tooltipObj.style.left=''
tooltipObj.style.right='5px'}
if(RG.isFixed(obj.canvas)){var scrollTop=window.scrollY||document.documentElement.scrollTop;tooltipObj.style.position='fixed';tooltipObj.style.top=e.pageY-scrollTop-height-10+'px';}
if(obj.get('chart.tooltips.effect')==='fade'){for(var i=1;i<=10;++i){(function(index)
{setTimeout(function()
{tooltipObj.style.opacity=index/10;},index*25);})(i);}}else{tooltipObj.style.opacity=1;}
tooltipObj.onmousedown=function(e){e.stopPropagation();}
tooltipObj.onmouseup=function(e){e.stopPropagation();}
tooltipObj.onclick=function(e){if(e.button==0){e.stopPropagation();}}
RG.Registry.Set('chart.tooltip',tooltipObj);RG.fireCustomEvent(obj,'ontooltip');};RG.getTooltipTextFromDIV=function(text)
{var result=/^id:(.*)/.exec(text);if(result&&result[1]&&document.getElementById(result[1])){text=document.getElementById(result[1]).innerHTML;}else if(result&&result[1]){text='';}
return text;};RG.getTooltipWidth=function(text,obj)
{var div=document.createElement('DIV');div.className=obj.Get('chart.tooltips.css.class');div.style.paddingLeft=RG.tooltips.padding;div.style.paddingRight=RG.tooltips.padding;div.style.fontFamily=RG.tooltips.font_face;div.style.fontSize=RG.tooltips.font_size;div.style.visibility='hidden';div.style.position='absolute';div.style.top='300px';div.style.left=0;div.style.display='inline';div.innerHTML=RG.getTooltipTextFromDIV(text);document.body.appendChild(div);return div.offsetWidth;};RG.hideTooltip=RG.HideTooltip=function()
{var tooltip=RG.Registry.Get('chart.tooltip');var uid=arguments[0]&&arguments[0].uid?arguments[0].uid:null;if(tooltip&&tooltip.parentNode&&(!uid||uid==tooltip.__canvas__.uid)){tooltip.parentNode.removeChild(tooltip);tooltip.style.display='none';tooltip.style.visibility='hidden';RG.Registry.Set('chart.tooltip',null);}};RG.preLoadTooltipImages=RG.PreLoadTooltipImages=function(obj)
{var tooltips=obj.Get('chart.tooltips');if(RG.hasTooltips(obj)){if(obj.type=='rscatter'){tooltips=[];for(var i=0;i<obj.data.length;++i){tooltips.push(obj.data[3]);}}
for(var i=0;i<tooltips.length;++i){var div=document.createElement('DIV');div.style.position='absolute';div.style.opacity=0;div.style.top='-100px';div.style.left='-100px';div.innerHTML=tooltips[i];document.body.appendChild(div);var img_tags=div.getElementsByTagName('IMG');for(var j=0;j<img_tags.length;++j){if(img_tags&&img_tags[i]){var img=document.createElement('IMG');img.style.position='absolute';img.style.opacity=0;img.style.top='-100px';img.style.left='-100px';img.src=img_tags[i].src
document.body.appendChild(img);setTimeout(function(){document.body.removeChild(img);},250);}}
document.body.removeChild(div);}}};RG.tooltips_mousemove=RG.Tooltips_mousemove=function(obj,e)
{var shape=obj.getShape(e);var changeCursor_tooltips=false
if(shape&&typeof(shape['index'])=='number'&&obj.Get('chart.tooltips')[shape['index']]){var text=RG.parseTooltipText(obj.Get('chart.tooltips'),shape['index']);if(text){changeCursor_tooltips=true;if(obj.Get('chart.tooltips.event')=='onmousemove'){if(!RG.Registry.Get('chart.tooltip')||RG.Registry.Get('chart.tooltip').__object__.uid!=obj.uid||RG.Registry.Get('chart.tooltip').__index__!=shape['index']){RG.hideTooltip();RG.Clear(obj.canvas);RG.redraw();RG.tooltip(obj,text,e.pageX,e.pageY,shape['index']);obj.Highlight(shape);}}}}else if(shape&&typeof(shape['index'])=='number'){var text=RG.parseTooltipText(obj.Get('chart.tooltips'),shape['index']);if(text){changeCursor_tooltips=true}}
return changeCursor_tooltips;};})(window,document);