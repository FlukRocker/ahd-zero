
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
	<title>Jwplayer Script</title>
	<style>#player,#loading-uplayer{width:100%;}#player,#loading-uplayer{height:100%;}body,html{padding-left:0;}html,body{padding-bottom:0;}#player,#loading-uplayer{position:absolute;}#player,#loading-uplayer,body,html{background:#000;}body,html{padding-right:0;}html,body{padding-top:0;}html,body{margin-left:0;}html,body{margin-bottom:0;}html,body{margin-right:0;}body,html{margin-top:0;}html,body{overflow:hidden;}#loading-uplayer{z-index:11;}@-moz-keyframes rotate-loading{0%{transform:rotate(0);-ms-transform:rotate(0);-webkit-transform:rotate(0);-o-transform:rotate(0);-moz-transform:rotate(0);}100%{transform:rotate(360deg);-ms-transform:rotate(360deg);-webkit-transform:rotate(360deg);-o-transform:rotate(360deg);-moz-transform:rotate(360deg);}}@-o-keyframes rotate-loading{0%{transform:rotate(0);-ms-transform:rotate(0);-webkit-transform:rotate(0);-o-transform:rotate(0);-moz-transform:rotate(0);}100%{transform:rotate(360deg);-ms-transform:rotate(360deg);-webkit-transform:rotate(360deg);-o-transform:rotate(360deg);-moz-transform:rotate(360deg);}}@-webkit-keyframes rotate-loading{0%{transform:rotate(0);-ms-transform:rotate(0);-webkit-transform:rotate(0);-o-transform:rotate(0);-moz-transform:rotate(0);}100%{transform:rotate(360deg);-ms-transform:rotate(360deg);-webkit-transform:rotate(360deg);-o-transform:rotate(360deg);-moz-transform:rotate(360deg);}}@keyframes rotate-loading{0%{transform:rotate(0);-ms-transform:rotate(0);-webkit-transform:rotate(0);-o-transform:rotate(0);-moz-transform:rotate(0);}100%{transform:rotate(360deg);-ms-transform:rotate(360deg);-webkit-transform:rotate(360deg);-o-transform:rotate(360deg);-moz-transform:rotate(360deg);}}@-moz-keyframes loading-text-opacity{0%,100%,20%{opacity:0;}50%{opacity:1;}}@-o-keyframes loading-text-opacity{0%,100%,20%{opacity:0;}50%{opacity:1;}}@-webkit-keyframes loading-text-opacity{0%,100%,20%{opacity:0;}50%{opacity:1;}}@keyframes loading-text-opacity{0%,100%,20%{opacity:0;}50%{opacity:1;}}[class~=loading-ani],.loading-container{height:6.25pc;}[class~=loading-ani],.loading-container{position:relative;}.loading-container:hover .loading-ani{border-left-color:#e45635;}.loading-container,[class~=loading-ani],[class~=loading-container] [class~=loading-text]{width:100px;}.loading-container:hover .loading-ani,[class~=loading-ani]{border-bottom-color:transparent;}.loading-container:hover .loading-ani{border-right-color:#e45635;}[class~=loading-container]:hover [class~=loading-ani],[class~=loading-container] [class~=loading-ani]{-webkit-transition:all .5s ease-in-out;}.loading-container,[class~=loading-ani]{border-radius:100%;}[class~=loading-container] [class~=loading-ani],[class~=loading-container]:hover [class~=loading-ani]{-moz-transition:all .5s ease-in-out;}[class~=loading-container] [class~=loading-ani],[class~=loading-container]:hover [class~=loading-ani]{-ms-transition:all .5s ease-in-out;}[class~=loading-container] [class~=loading-ani],[class~=loading-container]:hover [class~=loading-ani]{-o-transition:all .5s ease-in-out;}[class~=loading-container]:hover [class~=loading-ani],[class~=loading-container] [class~=loading-ani]{transition:all .5s ease-in-out;}.loading-container:hover .loading-ani,[class~=loading-ani]{border-top-color:transparent;}[class~=loading-container] [class~=loading-text]{-moz-animation:loading-text-opacity 2s linear 0s infinite normal;}[class~=loading-container] [class~=loading-text]{-o-animation:loading-text-opacity 2s linear 0s infinite normal;}[class~=loading-container] [class~=loading-text]{-webkit-animation:loading-text-opacity 2s linear 0s infinite normal;}[class~=loading-container]{margin-left:auto;}[class~=loading-container]{margin-bottom:40vh;}[class~=loading-container]{margin-right:auto;}[class~=loading-container] [class~=loading-text]{animation:loading-text-opacity 2s linear 0s infinite normal;}[class~=loading-container] [class~=loading-text]{color:#fff;}[class~=loading-container]{margin-top:40vh;}[class~=loading-ani]{border-left-width:.125pc;}[class~=loading-container] [class~=loading-text]{font-family:"Helvetica Neue",Helvetica,Arial,sans-serif;}[class~=loading-ani]{border-bottom-width:.125pc;}[class~=loading-container] [class~=loading-text]{font-size:7.5pt;}[class~=loading-container] [class~=loading-text]{font-weight:700;}[class~=loading-container] [class~=loading-text]{margin-top:33.75pt;}[class~=loading-container] [class~=loading-text]{opacity:0;}[class~=loading-ani]{border-right-width:.125pc;}[class~=loading-ani]{border-top-width:.125pc;}[class~=loading-ani]{border-left-style:solid;}[class~=loading-ani]{border-bottom-style:solid;}[class~=loading-container] [class~=loading-text]{position:absolute;}[class~=loading-container] [class~=loading-text]{text-align:center;}[class~=loading-ani]{border-right-style:solid;}[class~=loading-container] [class~=loading-text]{text-transform:uppercase;}[class~=loading-container] [class~=loading-text]{top:0;}[class~=loading-ani]{border-top-style:solid;}[class~=loading-ani]{border-left-color:#fff;}[class~=loading-ani]{border-right-color:#fff;}[class~=loading-ani]{border-image:none;}[class~=loading-ani]{-moz-animation:rotate-loading 1.5s linear 0s infinite normal;}[class~=loading-ani]{-moz-transform-origin:50% 50%;}[class~=loading-ani]{-o-animation:rotate-loading 1.5s linear 0s infinite normal;}[class~=loading-ani]{-o-transform-origin:50% 50%;}[class~=loading-ani]{-webkit-animation:rotate-loading 1.5s linear 0s infinite normal;}[class~=loading-ani]{-webkit-transform-origin:50% 50%;}[class~=loading-ani]{animation:rotate-loading 1.5s linear 0s infinite normal;}[class~=loading-ani]{transform-origin:50% 50%;}.uplay-iframe{position:absolute;width:100%;height:100%}</style>
	<!--<script type="text/javascript" src="https://cdn.staticaly.com/gh/ufilestorage/a/master/jquery-2.2.3.min.js"></script>	-->
</head>
<body>
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.0/sweetalert.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.min.js" type="text/javascript"></script>
<script src="https://ssl.p.jwpcdn.com/player/v/8.16.3/jwplayer.js"></script>
<script>jwplayer.key = "ITWMv7t88JGzI0xPwW8I0+LveiXX9SWbfdmt0ArUSyc=";</script>

<div id="player"></div>
<script type="text/javascript">
var videoPlayer = jwplayer("player");
var isAdPlaying = false;
var fallbackPending = false;

videoPlayer.setup({
    preload: "auto",
    primary: "html5",
    autostart: "false",
    image: "https://cdn.shirokami.me/sharing/ahd/cover.png",
    width: "100%",
    height: "100%",
    aboutlink: "https://hosting.shirokami.me/",
    abouttext: "Player",
    sources: [{
        file: "NQN8N.NNN27", 
        label: "Original",
        type: "video/mp4"
    }],
    advertising: {
        client: "vast",
        skipoffset: "3",
        volume: "50%",
        admessage: "โฆษณา xx วินาที.",
        skipmessage: "ข้ามโฆษณา xx วินาที.",
        schedule: {
            //adbreak1: { offset: 'pre', tag: 'xml/ads_1.xml.php' },
            //adbreak2: { offset: 'pre', tag: 'xml/ads_2.xml.php' },
        }
    }
});

// --- EVENT LISTENERS ---

// Ad Events
videoPlayer.on("adPlay", function() {
    isAdPlaying = true;
    checkCount = 0; // Reset watchdog when ad starts
});
videoPlayer.on("adComplete", function() {
    isAdPlaying = false;
    if (fallbackPending) triggerFallback();
});
videoPlayer.on("adSkipped", function() {
    isAdPlaying = false;
    if (fallbackPending) triggerFallback();
});
videoPlayer.on("adError", function(e) {
    isAdPlaying = false;
    if (fallbackPending) triggerFallback();
});

// Main Content Events
videoPlayer.on("error", function(e) {
    // If ad is playing, defer fallback
    if (isAdPlaying) {
        fallbackPending = true;
    } else {
        triggerFallback();
    }
});
videoPlayer.on("setupError", function(e) {
    triggerFallback();
});


// --- FALLBACK FUNCTION ---
function triggerFallback() {
    if (isAdPlaying) {
        fallbackPending = true;
        return;
    }

    if ($("#player").find("iframe").length === 0) {
        $("#player").html("<div class=\"uplay-iframe\"> <iframe src=\"<?php
            $link = base64_decode($_GET['link'] ?? '');
            // Tell the inner player to start immediately — the viewer already
            // pressed play before the ad ran.
            if ($link !== '') {
                $link .= (strpos($link, '?') !== false ? '&' : '?') . 'autoplay=1';
            }
            echo htmlspecialchars($link, ENT_QUOTES);
        ?>\" width=\"100%\" height=\"100%\" frameborder=\"0\" allowfullscreen=\"allowfullscreen\" allow=\"autoplay; fullscreen; encrypted-media; picture-in-picture\" class=\"uplay-box\"></iframe></div>");
    }
}

// --- SAFARI WATCHDOG ---
var checkCount = 0;
var maxChecks = 12; 
var watchdogInterval = setInterval(function() {
    if (isAdPlaying) return; 

    checkCount++;
    try {
        var state = videoPlayer.getState();
        var pos = videoPlayer.getPosition();
        
        if (state === 'error') {
            clearInterval(watchdogInterval);
            triggerFallback();
        } else if ((state === 'loading' || state === 'buffering' || state === undefined)) {
            // If stuck for 8 seconds
            if (checkCount >= 8 && pos < 0.1) {
                clearInterval(watchdogInterval);
                triggerFallback();
            }
        } else if (state === 'playing' && pos > 1) {
             clearInterval(watchdogInterval);
        }
    } catch (err) {
        // ignore
    }
    
    if (checkCount >= 15) clearInterval(watchdogInterval);

}, 1000); 

</script>
<noscript><i>Javascript required</i></noscript>
</body>
</html>