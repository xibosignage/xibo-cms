<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2013 Daniel Garner
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version. 
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */ 
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");
?>
<h1 id="Libbrowsernode_Build_Instructions">Libbrowsernode Build Instructions</h1>

<p>Build instructions on Ubuntu 12.04.4:
</p>
<ul><li> Install the following packages: git-core gyp cmake libvdpau-dev
</li><li> Download Berkelium Sources: <a rel="nofollow" class="external autonumber" href="https://github.com/sirikata/berkelium">[1]</a>
</li><li> Download Libavg 1.7.1 Sources: <a rel="nofollow" class="external autonumber" href="http://www.libavg.de">[2]</a>.
</li><li> Download libbrowsernode patches <a rel="nofollow" class="external autonumber" href="https://code.launchpad.net/~browsernode-team/+junk/berkelium-browsernode">[3]</a>
</li><li> Extract and build Berkelium following their installation guide.
</li><li> Extract libavg
</li><li> Copy the browsernode release files in to src/test/plugin folder.
</li><li> Copy the Berkelium header files in to src/test/plugin (ie src/test/plugin/berkelium)
</li><li> Build libavg as normal (following their guide).
</li><li> Run the following to build the libbrowsernode library:
</li></ul>
<pre> touch a.cpp
 g++ -c a.cpp -o a.o
 g++ a.o  -shared -llibberkelium -o libberkeliumwrapper.so
</pre>
<ul><li> Copy the resulting libberkeliumwrapper.so to /usr/lib
</li><li> Run "sudo ldconfig"
</li><li> Copy resources.pak, libffmpegsumo.so, chrome.pak and berkelium from /usr/local/bin to /usr/bin
</li></ul>

