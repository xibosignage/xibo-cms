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
</li><li> Download Berkelium Sources:
</li></ul>
<pre> git clone git://github.com/sirikata/berkelium
cd berkelium
git submodule update --init --recursive
./util/build-chromium.sh --deps --force
</pre>
<ul><li> The Chromium sources will download. Once the download completes, and the building begins, manually patch build/chromium/src/net/base/x509_certificate_nss.c as follows:
</li></ul>
<pre>
--- x509_certificate_nss.cc	2014-02-08 22:57:17.379760998 +0000
+++ x509_certificate_nss.cc	2014-02-09 09:21:17.633417195 +0000
@@ -188,7 +188,12 @@
   }
 }
 
-typedef char* (*CERTGetNameFunc)(CERTName* name);
+
+#if NSS_VMINOR >= 15
+  typedef char* (*CERTGetNameFunc)(CERTName const* name);
+#else
+  typedef char* (*CERTGetNameFunc)(CERTName* name);
+#endif
</pre>
</pre>
<ul><li> Allow the build to complete normally. This will take several hours, and requires around 4GB of RAM at times.
</li><li> Once the Chromium build completes, build Berkelium as follows:
</li></ul>
<pre>
cmake . -DCMAKE_BUILD_TYPE=Release
make
sudo make install
</pre>
<ul><li> It may well be that Berkelium fails to build at the linking stage. If so, edit the file CMakeFiles/libberkelium.dir/link.txt and remove all instances of the string -ljpeg. Be sure to only select -ljpeg and nothing else. Save the file and run make again to complete the build.
</li><li> Run the following to build the libbrowsernode library:
</li></ul>
<pre> touch a.cpp
 g++ -c a.cpp -o a.o
 g++ a.o  -shared -llibberkelium -o libberkeliumwrapper.so
</pre>
<ul><li> Copy the resulting libberkeliumwrapper.so to /usr/lib
</li></ul>
<pre>
sudo ldconfig
</pre>
<ul><li> Copy resources.pak, libffmpegsumo.so, chrome.pak and berkelium from /usr/local/bin to /usr/bin
</li><li> Download Libavg 1.7.1 Sources:
</li></ul>
<pre>
sudo apt-get install subversion automake autoconf libtool libxml2-dev \
libpango1.0-dev librsvg2-dev libgdk-pixbuf2.0-dev libavcodec-dev libavformat-dev \
libswscale-dev python-dev libboost-python-dev libboost-thread-dev g++ libSDL-dev \
libxxf86vm-dev libdc1394-22-dev linux-libc-dev libvdpau-dev

svn co -r 9546 https://www.libavg.de/svn/trunk/libavg

cd libavg
</pre>
<ul><li> Download libbrowsernode patches <a rel="nofollow" class="external autonumber" href="https://code.launchpad.net/browsernode">[3]</a>
</li><li> Copy the browsernode release files in to src/test/plugin folder.
</li><li> Copy the Berkelium header files in to src/test/plugin (ie src/test/plugin/berkelium)
</li><li> Copy /usr/local/lib/liblibberkelium.so in to src/test/plugin
</li><li> Build libavg as normal:
</li></ul>
<pre>
./bootstrap
./configure --enable-dc1394 --enable-v4l2
make
sudo make install
</pre>

