<!DOCTYPE html>
<html lang="en">
    <head>
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title>Websockets for Serverless!</title>
        <link href="https://fonts.googleapis.com/css?family=Dosis:300&display=swap" rel="stylesheet">
        <link href="https://unpkg.com/tailwindcss@^1.0/dist/tailwind.min.css" rel="stylesheet">
    </head>
    <body class="flex h-screen">
        <div class="rounded-full mx-auto self-center relative" style="height: 400px; width: 400px; background: linear-gradient(123.19deg, #266488 3.98%, #258ecb 94.36%)">
            <h1 class="font-light absolute w-full text-center text-blue-200" style="font-family: Dosis; font-size: 45px; top: 35%">Hello there,</h1>
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
        </div>

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
                wsHost:            '<?php echo app_api_endpoint(); ?>',
                wsPath:            '/<?php echo app_stage(); ?>',
                forceTLS:          true,
                enableStats:       false,
                enabledTransports: ['ws'],
            });
        </script>
    </body>
</html>
