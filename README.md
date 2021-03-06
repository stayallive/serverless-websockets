<h3 align="center">
    <b>⚠️ Please do not use this in production yet, use it at your own risk! ⚠️</b>
</h3>

---

# Serverless WebSockets

Brings the power of WebSockets to serverless. Tries to achieve drop-in Pusher replacement.

This project is heavily inspired by [Laravel WebSockets](https://github.com/beyondcode/laravel-websockets).

## Parameters

The `serverless.yml` references AWS Systems Manager Parameter Store parameters to configure the application.

Set your values using the AWS CLI (or create them manually through the AWS Console):

```bash
# Set this to the region you are deploying to
REGION="eu-central-1"

# You will need to set the parameter for each stage
STAGE="dev"

# Configuration that you need to share with your Pusher SDKs
aws ssm put-parameter --region $REGION --name "/serverless-websockets/$STAGE/app-id" --type String --value 'MY_APP_ID'
aws ssm put-parameter --region $REGION --name "/serverless-websockets/$STAGE/app-key" --type String --value 'MY_APP_KEY'
aws ssm put-parameter --region $REGION --name "/serverless-websockets/$STAGE/app-secret" --type String --value 'MY_APP_SECRET'

### The parameters below are optional:

# Set this to true to enable the webhook worker which sends the webhooks, set to false to disable the worker
aws ssm put-parameter --region $REGION --name "/serverless-websockets/$STAGE/webhook-enabled" --type String --value 'false'
# You cannot set empty values so just not create the parameters if you don't want webhooks
aws ssm put-parameter --region $REGION --name "/serverless-websockets/$STAGE/webhook-target" --type String --value ''
# Add the events you want to receive: channel_occupied,channel_vacated,member_added,member_removed,client_event
aws ssm put-parameter --region $REGION --name "/serverless-websockets/$STAGE/webhook-events" --type String --value ''

# Set to true if you want to see how many clients are connected and how long they are connected on average
aws ssm put-parameter --region $REGION --name "/serverless-websockets/$STAGE/write-cloudwatch-metrics" --type String --value 'false'

# Set to true if you want to allow clients to send event in authenticated channels
aws ssm put-parameter --region $REGION --name "/serverless-websockets/$STAGE/client-events-enabled" --type String --value 'false'

# There is a little example application on /wave you can use to see if everything is working as expected
aws ssm put-parameter --region $REGION --name "/serverless-websockets/$STAGE/wave-example-enabled" --type String --value 'false'
```

Do not forget to re-deploy after updating the parameters.

## CLI

There is a Lambda function called CLI created for each deployment, this Lambda serves a few purposes.

You can call any CLI action by executing:

```bash
# Don't forget to include --stage=<stage> if you want to execute it on a specific stage
serverless invoke -f cli -d '{"action": "<ACTION NAME>"}'
```

### One-off commands

#### disconnect-all-connections

Executing this action will disconnect all currently connected clients. It will not prevent them from immediately reconnecting.

### Scheduled commands

#### write-cloudwatch-metrics

This action is scheduled to run every minute and is disabled by default. It writes the current amount of connectiond and the average time the currently connected clients are connected.

#### cleanup-stale-connections

This action is scheduled to run every 4 hours and is enabled by default. It finds and disconnects client who are connected for over 24 hours.

## Limitations

This project uses the AWS API Gateway to provide it's WebSocket connection. The limitation with using the API Gateway is that you cannot specify a WebSocket endpoint yourself, it's defined by AWS and defaults to `wss://<gateway-endpoint>/<stage-name>`. This has 2 drawbacks:

- Only one application per deployment, since there is no other way to specify the application ID in the Pusher SDK except in the WebSocket url which we cannot modify
- We need to "message" the Pusher JS SDK (and other SDK that handle the WebSocket connection) a bit to use the correct WebSocket endpoint

Another limitation is that we cannot respond to the initial connection from the API Gateway so we need the client to send an event so we can respond with the Pusher protocol handshake.

It's likely client SDKs (handling the WebSocket connection) needs changes to use the correct `wss` url.

### Changes required for the Pusher JS SDK

This is code is tested with version `7.0.1` of the Pusher JS SDK, keep in mind that upgrading to newer Pusher JS SDK version might break this workaround.

```js
// Pusher has it's own socket URL structure that doesn't play nice with API Gateway
// so we patch the method that generates the wss URL and return our own WebSocket URL
Pusher.Runtime.Transports.ws.hooks.urls.getInitial = () => {
    return 'wss://YOUR_API_GATEWAY_ENDPOINT/YOUR_API_GATEWAY_STAGE_NAME';
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
const pusher = new Pusher('APP_KEY', {
    forceTLS:          true,
    enableStats:       false, // disable this since we do not accept the stats being sent
    enabledTransports: ['ws'],
});
```

## Security

If you discover any security related issues, please email alex@bouma.dev instead of using the issue tracker.

## Credits

- [Alex Bouma](https://github.com/stayallive)
- [All Contributors](../../contributors)
- [All Contributors of Laravel WebSockets](https://github.com/beyondcode/laravel-websockets)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
