<?php
header("Content-type: text/xml");
echo "<?xml version='1.0' encoding='UTF-8'?>";
?>
<VAST version="2.0" >
<Ad id="pre-roll-0">
    <InLine>
    <AdSystem>2.0</AdSystem>
    <AdTitle>Sample</AdTitle>
    <Impression></Impression>
    <Creatives>
        <Creative sequence="1" id="2" >
        <Linear skipoffset="00:00:03">
        <Duration>00:02:00</Duration>
        <AdParameters>
        </AdParameters>
		  <VideoClicks>
              <!-- Back up old link <ClickThrough>https://www.ufa1919.info/</ClickThrough>-->
              <!--<ClickThrough>https://ufa1913.com</ClickThrough> -->
              <ClickThrough>https://huisache.com/</ClickThrough>
              <ClickTracking>//www.animehdzero.com/player/img/pixel.gif</ClickTracking>
            </VideoClicks>
        <MediaFiles>
            <MediaFile delivery="progressive" bitrate="400" type="video/mp4">
                <URL>https://dev-tools.shirokami.me/video/solo16888.mp4</URL>
            </MediaFile>
        </MediaFiles>
        </Linear>
        </Creative>
    </Creatives>
    </InLine>
</Ad>
</VAST>