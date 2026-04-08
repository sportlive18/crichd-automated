<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRICHD-mini</title>
    <!-- Materialize CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
    <!-- Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <!-- HLS.js Library -->
    <script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>

    <style>
        body {
            display: flex;
            min-height: 100vh;
            flex-direction: column;
            background-color: #f4f4f4; /* Light grey background */
        }
        main {
            flex: 1 0 auto;
        }
        .player-wrapper {
            position: relative;
            padding-top: 56.25%;
            background-color: #000;
        }
        .player-wrapper video {
            position: absolute;
            top: 0; left: 0; bottom: 0; right: 0;
            width: 100%; height: 100%;
        }
        #streamLinkCard {
            font-family: monospace;
            font-size: 1rem;
            word-wrap: break-word;
            padding: 15px;
            background-color: #fff;
            border-left: 5px solid #2196F3; /* Blue accent border */
        }
        #channel-list.collection {
            height: 500px; /* Or a height that fits your design */
            overflow-y: auto;
            border-radius: 5px;
        }
        .collection .collection-item {
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .collection .collection-item.active {
            background-color: #90caf9; /* blue lighten-3 */
            color: #0d47a1; /* blue darken-4 */
            font-weight: bold;
        }
        .collection .collection-item:hover {
            background-color: #e3f2fd; /* blue lighten-5 */
        }
        .section {
            padding-top: 2rem;
            padding-bottom: 2rem;
        }
    </style>
</head>
<body class="grey lighten-4">

    <!-- Header -->
    <header>
        <nav class="blue darken-3">
            <div class="nav-wrapper">
                <a href="#" class="brand-logo" style="padding-left: 20px;"><i class="material-icons left">satellite</i>CRICHD-mini</a>
                <a href="#" id="menu-trigger" data-target="mobile-nav" class="sidenav-trigger"><i class="material-icons">menu</i></a>
                <ul class="right hide-on-med-and-down">
                    <li><a id="playlist-button" href="playlist.php" class="waves-effect waves-light btn blue accent-2"><i class="material-icons left">get_app</i> Playlist</a></li>
                </ul>
            </div>
        </nav>
        <ul class="sidenav" id="mobile-nav">
            <li><a href="playlist.php"><i class="material-icons">get_app</i> Download Playlist</a></li>
        </ul>
    </header>

    <!-- Tap Target Structures -->
    <div class="tap-target blue hide-on-med-and-down" data-target="playlist-button">
        <div class="tap-target-content white-text">
            <h5><strong>Download Playlist</strong></h5>
            <p><strong>Click here to download the M3U playlist and watch in your favorite player!</strong></p>
        </div>
    </div>

    <div class="tap-target blue hide-on-large-only" data-target="menu-trigger">
        <div class="tap-target-content white-text">
            <h5><strong>Discover More</strong></h5>
            <p><strong>Open the menu to download the playlist!</strong></p>
        </div>
    </div>

    <!-- Main Content -->
    <main class="section">
        <div class="container">
            <div class="row">
                <!-- Player and Stream Link -->
                <div class="col s12 l8">
                    <div class="card z-depth-2">
                        <div class="card-image player-wrapper">
                            <video id="videoPlayer" class="responsive-video" controls autoplay muted></video>
                        </div>
                    </div>
                    <div class="card" style="margin-top: 20px;">
                        <div class="card-content">
                            <span class="card-title">Stream Link</span>
                            <div id="streamLinkCard" class="z-depth-1">
                                <p id="streamLink">-</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Channel List -->
                <div class="col s12 l4">
                     <ul id="channel-list" class="collection with-header z-depth-1">
                        <li class="collection-header"><h5><i class="material-icons left">tv</i>Channels</h5></li>
                        <!-- Channels will be dynamically inserted here -->
                    </ul>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="page-footer blue darken-2" style="padding-top: 15px; padding-bottom: 10px;">
        <div class="container center-align">
            © 2023 CRICHD-mini | Developed by Siam3310
        </div>
    </footer>

    <!-- Materialize JavaScript -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>

    <!-- Application Logic -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Materialize components
            var sidenav = document.querySelectorAll('.sidenav');
            M.Sidenav.init(sidenav, {});

            // Initialize Tap Targets
            var desktopTapTargetElem = document.querySelector('[data-target="playlist-button"]');
            var desktopTapTargetInstance = M.TapTarget.init(desktopTapTargetElem, {});

            var mobileTapTargetElem = document.querySelector('[data-target="menu-trigger"]');
            var mobileTapTargetInstance = M.TapTarget.init(mobileTapTargetElem, {});

            // Show the correct tap target after 3 seconds based on screen size
            setTimeout(() => {
                if (window.innerWidth > 992) { // Corresponds to hide-on-med-and-down
                    desktopTapTargetInstance.open();
                } else {
                    mobileTapTargetInstance.open();
                }
            }, 3000);

            // Allow user to click the desktop button to see its tap target again
            document.getElementById('playlist-button').addEventListener('click', (e) => {
                e.preventDefault();
                desktopTapTargetInstance.open();
            });

            const video = document.getElementById('videoPlayer');
            const streamLinkEl = document.getElementById('streamLink');
            const channelList = document.getElementById('channel-list');

            let hls = null;
            let currentChannel = 'skysme'; // Default channel
            let channelsData = {};

            function logError(type, details) {
                console.error(`[${type}]`, details);
                M.toast({html: `Error: ${details.message || 'An unknown error occurred.'}`});
            }

            function loadStream(channelId) {
                if (!channelsData[channelId]) {
                    return logError('STREAM_ERROR', { message: `Channel '${channelId}' not found.` });
                }

                if (hls) hls.destroy();
                if (!Hls.isSupported()) {
                    M.toast({html: 'HLS is not supported in this browser.'});
                    return;
                }

                hls = new Hls({
                    lowLatencyMode: true,
                    backBufferLength: 90
                });

                hls.on(Hls.Events.ERROR, (event, data) => {
                    if (data.fatal) {
                        logError('HLS_FATAL_ERROR', data);
                        switch (data.type) {
                            case Hls.ErrorTypes.NETWORK_ERROR: hls.startLoad(); break;
                            case Hls.ErrorTypes.MEDIA_ERROR: hls.recoverMediaError(); break;
                            default: hls.destroy(); break;
                        }
                    }
                });

                hls.loadSource(`player.php?channel=${channelId}`);
                hls.attachMedia(video);
                video.play().catch(e => console.log('Autoplay was prevented.'));
            }

            function updateStreamLink(channelId) {
                streamLinkEl.textContent = 'Fetching...';
                fetch(`player.php?action=get_link&channel=${channelId}`)
                    .then(response => {
                        if (!response.ok) throw new Error(`HTTP Error: ${response.status}`);
                        return response.text();
                    })
                    .then(link => { streamLinkEl.textContent = link; })
                    .catch(error => {
                        streamLinkEl.textContent = 'Failed to fetch stream link.';
                        logError('LINK_FETCH_ERROR', error);
                    });
            }

            function renderChannelList() {
                const items = channelList.querySelectorAll('.collection-item');
                items.forEach(item => item.remove());

                for (const id in channelsData) {
                    const channel = channelsData[id];
                    const item = document.createElement('a');
                    item.href = '#!';
                    item.className = 'collection-item';
                    item.dataset.channel = id;
                    item.textContent = channel.name;
                    item.style.fontWeight = 'bold'; // Make channel names bold
                    channelList.appendChild(item);
                }
                setActiveChannel(currentChannel);
            }

            function setActiveChannel(channelId) {
                 document.querySelectorAll('#channel-list .collection-item').forEach(item => {
                    item.classList.remove('active');
                    if (item.dataset.channel === channelId) {
                        item.classList.add('active');
                    }
                });
            }

            channelList.addEventListener('click', (e) => {
                e.preventDefault();
                const item = e.target.closest('.collection-item');
                if (item && item.dataset.channel) {
                    const newChannel = item.dataset.channel;
                    if (newChannel !== currentChannel) {
                        currentChannel = newChannel;
                        loadStream(currentChannel);
                        updateStreamLink(currentChannel);
                        setActiveChannel(currentChannel);
                    }
                }
            });

            // --- Initial Load ---
            fetch('player.php?action=get_channels')
                .then(response => {
                    if (!response.ok) throw new Error(`HTTP Error: ${response.status}`);
                    return response.json();
                })
                .then(data => {
                    channelsData = data;
                    renderChannelList();
                    loadStream(currentChannel);
                    updateStreamLink(currentChannel);
                }).catch(error => {
                    M.toast({html: "Could not fetch channels. Server may be down."});
                    logError('INITIAL_FETCH_ERROR', error);
                });
        });
    </script>

</body>
</html>
