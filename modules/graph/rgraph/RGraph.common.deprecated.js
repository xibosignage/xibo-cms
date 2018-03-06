
RGraph=window.RGraph||{isRGraph:true};(function(win,doc,undefined)
{var RG=RGraph,ua=navigator.userAgent,ma=Math;RG.text=RG.Text=function(context,font,size,x,y,text)
{var args=arguments;if((typeof(text)!='string'&&typeof(text)!='number')||text=='undefined'){return;}
if(typeof(text)=='string'&&text.match(/\r\n/)){var dimensions=RGraph.MeasureText('M',args[11],font,size);var arr=text.split('\r\n');if(args[6]&&args[6]=='center')y=(y-(dimensions[1]*((arr.length-1)/2)));for(var i=1;i<arr.length;++i){RGraph.Text(context,font,size,args[9]==-90?(x+(size*1.5)):x,y+(dimensions[1]*i),arr[i],args[6]?args[6]:null,args[7],args[8],args[9],args[10],args[11],args[12]);}
text=arr[0];}
if(document.all&&RGraph.ISOLD){y+=2;}
context.font=(args[11]?'Bold ':'')+size+'pt '+font;var i;var origX=x;var origY=y;var originalFillStyle=context.fillStyle;var originalLineWidth=context.lineWidth;if(typeof(args[6])=='undefined')args[6]='bottom';if(typeof(args[7])=='undefined')args[7]='left';if(typeof(args[8])=='undefined')args[8]=null;if(typeof(args[9])=='undefined')args[9]=0;if(navigator.userAgent.indexOf('Opera')!=-1){context.canvas.__rgraph_valign__=args[6];context.canvas.__rgraph_halign__=args[7];}
context.save();context.canvas.__rgraph_originalx__=x;context.canvas.__rgraph_originaly__=y;context.translate(x,y);x=0;y=0;if(args[9]){context.rotate(args[9]/(180/RGraph.PI));}
if(args[6]){var vAlign=args[6];if(vAlign=='center'){context.textBaseline='middle';}else if(vAlign=='top'){context.textBaseline='top';}}
if(args[7]){var hAlign=args[7];var width=context.measureText(text).width;if(hAlign){if(hAlign=='center'){context.textAlign='center';}else if(hAlign=='right'){context.textAlign='right';}}}
context.fillStyle=originalFillStyle;context.save();context.fillText(text,0,0);context.lineWidth=1;var width=context.measureText(text).width;var width_offset=(hAlign=='center'?(width/2):(hAlign=='right'?width:0));var height=size*1.5;var height_offset=(vAlign=='center'?(height/2):(vAlign=='top'?height:0));var ieOffset=RGraph.ISOLD?2:0;if(args[8]){context.strokeRect(-3-width_offset,0-3-height-ieOffset+height_offset,width+6,height+6);if(args[10]){context.fillStyle=args[10];context.fillRect(-3-width_offset,0-3-height-ieOffset+height_offset,width+6,height+6);}
context.fillStyle=originalFillStyle;context.fillText(text,0,0);}
context.restore();context.lineWidth=originalLineWidth;context.restore();};RG.getMouseXY=function(e)
{var el=(RGraph.ISOLD?event.srcElement:e.target);var x;var y;var paddingLeft=el.style.paddingLeft?parseInt(el.style.paddingLeft):0;var paddingTop=el.style.paddingTop?parseInt(el.style.paddingTop):0;var borderLeft=el.style.borderLeftWidth?parseInt(el.style.borderLeftWidth):0;var borderTop=el.style.borderTopWidth?parseInt(el.style.borderTopWidth):0;if(RGraph.ISIE8)e=event;if(typeof(e.offsetX)=='number'&&typeof(e.offsetY)=='number'){x=e.offsetX;y=e.offsetY;}else{x=0;y=0;while(el!=document.body&&el){x+=el.offsetLeft;y+=el.offsetTop;el=el.offsetParent;}
x=e.pageX-x;y=e.pageY-y;}
return[x,y];};RG.oldBrowserCompat=RG.OldBrowserCompat=function(co)
{if(!co){return;}
if(!co.measureText){co.measureText=function(text)
{var textObj=document.createElement('DIV');textObj.innerHTML=text;textObj.style.position='absolute';textObj.style.top='-100px';textObj.style.left=0;document.body.appendChild(textObj);var width={width:textObj.offsetWidth};textObj.style.display='none';return width;}}
if(!co.fillText){co.fillText=function(text,targetX,targetY)
{return false;}}
if(!co.canvas.addEventListener){window.addEventListener=function(ev,func,bubble)
{return this.attachEvent('on'+ev,func);}
co.canvas.addEventListener=function(ev,func,bubble)
{return this.attachEvent('on'+ev,func);}}};RG.each=function(arr,func)
{for(var i=0,len=arr.length;i<len;i+=1){if(typeof arguments[2]!=='undefined'){var ret=func.call(arguments[2],i,arr[i]);}else{var ret=func.call(arr,i,arr[i]);}
if(ret===false){return;}}};RG.getHeight=RG.GetHeight=function(obj)
{return obj.canvas.height;};RG.getWidth=RG.GetWidth=function(obj)
{return obj.canvas.width;};RG.timer=RG.Timer=function(label)
{if(typeof RG.TIMER_LAST_CHECKPOINT=='undefined'){RG.TIMER_LAST_CHECKPOINT=Date.now();}
var now=Date.now();console.log(label+': '+(now-RG.TIMER_LAST_CHECKPOINT).toString());RG.TIMER_LAST_CHECKPOINT=now;};RG.setConfig=RG.SetConfig=function(obj,config)
{for(i in config){if(typeof i==='string'){obj.Set(i,config[i]);}}
return obj;};})(window,document);window.$empty=function(value)
{if(!value||value.length<=0){return true;}
return false;};