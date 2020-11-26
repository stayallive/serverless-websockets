<!DOCTYPE html>
<html lang="en">
    <head>
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title>Websockets for Serverless!</title>
        <link href="https://fonts.googleapis.com/css?family=Dosis:300&display=swap" rel="stylesheet">
        <link href="https://unpkg.com/tailwindcss@^1.0/dist/tailwind.min.css" rel="stylesheet">
        <style>
            /* https://jarv.is/notes/css-waving-hand-emoji/ */
            .wave > span {
                animation-name: wave-animation; /* Refers to the name of your @keyframes element below */
                animation-duration: 2s; /* Change to speed up or slow down */
                animation-iteration-count: 1;
                transform-origin: 70% 70%; /* Pivot around the bottom-left palm */
                display: inline-block;
            }

            @keyframes wave-animation {
                0% {
                    transform: rotate(0.0deg)
                }
                10% {
                    transform: rotate(14.0deg)
                }
                /* The following five values can be played with to make the waving more or less extreme */
                20% {
                    transform: rotate(-8.0deg)
                }
                30% {
                    transform: rotate(14.0deg)
                }
                40% {
                    transform: rotate(-4.0deg)
                }
                50% {
                    transform: rotate(10.0deg)
                }
                60% {
                    transform: rotate(0.0deg)
                }
                /* Reset for the last half to pause */
                100% {
                    transform: rotate(0.0deg)
                }
            }
        </style>
    </head>
    <body class="flex h-screen">
        <div class="rounded-full mx-auto self-center relative" style="height: 400px; width: 400px; background: linear-gradient(123.19deg, #266488 3.98%, #258ecb 94.36%)">
            <h1 class="font-light absolute w-full text-center text-blue-200" style="font-family: Dosis; font-size: 45px; top: 30%" id="counter">Hello there,</h1>
            <div class="w-full relative absolute" style="top: 60%; height: 50%">
                <div class="absolute inset-x-0 bg-white" style="bottom: 0; height: 55%"></div>
                <svg viewBox="0 0 1280 311" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <g clip-path="url(#clip0)">
                        <path d="M1214 177L1110.5 215.5L943.295 108.5L807.5 168.5L666 66.5L581 116L517 49.5L288.5 184L163.5 148L-34.5 264.5V311H1317V258.5L1214 177Z" fill="white"/>
                        <path d="M1214 177L1110.5 215.5L943.295 108.5L807.5 168.5L666 66.5L581 116L517 49.5L288.5 184L163.5 148L-34.5 264.5L163.5 161L275 194L230.5 281.5L311 189L517 61L628 215.5L600 132.5L666 77L943.295 295L833 184L943.295 116L1172 275L1121 227L1214 189L1298 248L1317 258.5L1214 177Z" fill="#DCEFFA"/>
                    </g>
                    <defs>
                        <clipPath id="clip0">
                            <rect width="1280" height="311" fill="white"/>
                        </clipPath>
                    </defs>
                </svg>
            </div>
            <div class="absolute w-full text-center">
                <a id="say-hi" href="#" class="shadown inline-flex items-center justify-center self-center px-5 py-3 border border-transparent text-base font-medium rounded-md text-indigo-600 bg-white hover:bg-indigo-50" style="font-size: 40px;">
                    <span>👋</span>
                </a>
            </div>
        </div>

        <script>
            function setCookie(name, value, days) {
                var expires = '';
                if (days) {
                    var date = new Date();
                    date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
                    expires = '; expires=' + date.toUTCString();
                }
                document.cookie = name + '=' + (value || '') + expires + '; path=/';
            }

            function getCookie(name) {
                var nameEQ = name + '=';
                var ca     = document.cookie.split(';');
                for (var i = 0; i < ca.length; i++) {
                    var c = ca[i];
                    while (c.charAt(0) == ' ') c = c.substring(1, c.length);
                    if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length, c.length);
                }
                return null;
            }

            function eraseCookie(name) {
                document.cookie = name + '=; Path=/; Expires=Thu, 01 Jan 1970 00:00:01 GMT;';
            }

            function uuidv4() {
                return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
                    var r = Math.random() * 16 | 0, v = c == 'x' ? r : (r & 0x3 | 0x8);
                    return v.toString(16);
                });
            }

            // Generate a random user ID for the session
            if (getCookie('user_id') === null) {
                setCookie('user_id', uuidv4(), 7);
            }
        </script>
        <script src="https://js.pusher.com/7.0.1/pusher.min.js"></script>
        <script>
            Pusher.log = (msg) => {
                console.log(msg);
            };

            // Pusher has it's own socket URL structure that doesn't play nice with API Gateway
            // so we patch the method that generates the wss URL and return our own WebSocket URL
            Pusher.Runtime.Transports.ws.hooks.urls.getInitial = () => {
                return 'wss://<?php echo app_ws_api_endpoint(); ?>/<?php echo app_stage(); ?>';
            };

            // We also need to get the socket to workaround another API Gateway limitation
            const socketRetriever = Pusher.Runtime.Transports.ws.hooks.getSocket;

            // We need to capture the actual WebSocket created by Pusher so we can send an initital message
            Pusher.Runtime.Transports.ws.hooks.getSocket = (e) => {
                // We let Pusher create the WebSocket
                const socket = socketRetriever(e);

                // We listen for when the WebSocket opens so we can push our connect message
                // We don't use `socket.onopen` since Pusher SDK immeditaly wil overwrite it
                socket.addEventListener('open', () => {
                    // Send our initial "handshake" event so we can respond with the Pusher handshake message
                    // without this handshake message from the server the SDK will timeout the connection and reconnect
                    socket.send(JSON.stringify({'event': 'internal:connect'}));
                });

                // Continue to let Pusher do it's thing
                return socket;
            };

            // The app key can be anything since it's unused (no support for multiple apps per deployment)
            const pusher = new Pusher('<?php echo getenv('APP_KEY'); ?>', {
                wsHost:            '<?php echo app_ws_api_endpoint(); ?>',
                wsPath:            '/<?php echo app_stage(); ?>',
                forceTLS:          true,
                enableStats:       false,
                authEndpoint:      '/wave/pusher/auth',
                enabledTransports: ['ws'],
            });

            const presenceChannel = pusher.subscribe('presence-internal-wave');

            function updateCounter() {
                // Extract one to not count the current browser
                let count = presenceChannel.members.count - 1;

                document.getElementById('counter').innerText = 'Say hi to ' + count + ' browsers!';
            }

            function wave() {
                let element = document.getElementById('say-hi');

                element.classList.remove('wave');
                element.offsetWidth;
                element.classList.add('wave');
            }

            presenceChannel.bind('pusher:subscription_succeeded', updateCounter);
            presenceChannel.bind('pusher:member_added', updateCounter);
            presenceChannel.bind('pusher:member_removed', updateCounter);
            presenceChannel.bind('client-wave', wave);

            document.getElementById('say-hi').addEventListener('click', (e) => {
                e.preventDefault();

                presenceChannel.trigger('client-wave');

                wave();
            });
        </script>
    </body>
</html>
