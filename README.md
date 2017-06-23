## Instaphp V2 ##

##Upgrade Notes ##

The latest version uses HTTPlug so that you can supply your own HTTP client layer. If you want to use Guzzle 6 or whether you have to use Guzzle 5 because other libraries require it, the HTTPlug library provides an abstraction between Instaphp and your own HTTP libary.

You will need to install one of the client libraries yourself http://docs.php-http.org/en/latest/clients.html

```
composer.phar require php-http/curl-client guzzlehttp/psr7
```

BC Breaks:

Unfortunately there are some BC breaks, specifically around the HTTP client options. `http_timeout`, `verify` and `http_connect_timeout` are now deprecated and should be set on the client yourself (see below).

The `event.before`, `event.error` and `event.after` callable functions now take the parameters Psr\Http\Message\RequestInterface, \Exception and Psr\Http\Message\ResponseInterface as arguments instead of the Guzzle equivalents. They need to return the same objects for chaining.


The example below adds a custom log function to the event.error event

```php
	$api = new Instaphp\Instaphp([
		'client_id' => 'your client id',
		'client_secret' => 'your client secret',
		'redirect_uri' => 'http://somehost.foo/callback.php',
		'scope' => 'comments+likes',
		'event.error' => function(\Exception $e) {
		    error_log($e->getMessage());
		    return $e;
		}
	]);
```

## Usage ##

Installing should be done through [composer](https://getcomposer.org/). If you want to use [Guzzle 6](http://docs.guzzlephp.org/en/stable/), use the command below

```
composer.phar require instaphp/instaphp php-http/guzzle6-adapter guzzlehttp/psr7
```

And it should start using Guzzle 6

Here's a basic example showing how to get 10 popular posts...

``` php
<?php

	$api = new Instaphp\Instaphp([
		'client_id' => 'your client id',
		'client_secret' => 'your client secret',
		'redirect_uri' => 'http://somehost.foo/callback.php',
		'scope' => 'comments+likes'
	]);

	$popular = $api->Media->Popular(['count' => 10]);

	if (empty($popular->error)) {
		foreach ($popular->data as $item) {
			printf('<img src="%s">', $item['images']['low_resolution']['url']);
		}
	}
?>
```

Important: This won't work out of the box. Instagram have strict policies about who can use their API and you need to read up about pre-populating the data using [a sandbox account](https://www.instagram.com/developer/sandbox/) before diving into your project.

### Configuration ###

Configuration is a simple `array` of key/value pairs. The absolute minimum required setting is `client_id`, but if you plan to allow users to login via OAuth, you'll need `client_secret` & `redirect_uri`. All the other settings are optional and/or have sensible defaults.

Key|Default Value|Description
:--|:-----------:|:----------------
access_token|Empty|This is the access token for an authorized user. You obtain this from API via OAuth
redirect_uri|Empty|The redirect URI you defined when setting up your Instagram client
client_ip|Empty|The IP address of the client. This is used to sign POST & DELETE requests. It's not required, but without the signing, users are more limited in how many likes/comments they can post in a given hour
scope|comments+relationships+likes|The scope of your client's capability
log_enabled|FALSE|Enable logging
log_level|DEBUG|Log level. See [Monolog Logger](https://github.com/Seldaek/monolog#log-levels)
log_path|./instaphp.log|Where the log file lives
http_useragent|Instaphp/2.0; cURL/{curl_version}; (+http://instaphp.com)|The user-agent string sent with all requests
debug|FALSE|Debug mode?
event.before|Empty|Callback called prior to sending the request to the API. Method takes a single parameter [BeforeEvent](http://docs.guzzlephp.org/en/latest/events.html#before)
event.after|Empty|Callback called after a response is received from the API. Method takes a single parameter of [CompleteEvent](http://docs.guzzlephp.org/en/latest/events.html#complete)
event.error|Empty|Callback called when an error response is received from the API. Method takes a single parameter of [ErrorEvent](http://docs.guzzlephp.org/en/latest/events.html#error).

### Configuring Guzzle ###

Your choice of HTTP client is automatically discovered using HTTPlug's [Discovery classes](http://docs.php-http.org/en/latest/discovery.html). If you need to edit the settings of your client-specific configuration you can inject the adapter into the Instaphp class

First, install the adapter

`composer require php-http/guzzle6-adapter`

Then configure the client and wrap it in the adapter

``` php
<?php

use GuzzleHttp\Client as GuzzleClient;
use Http\Adapter\Guzzle6\Client as GuzzleAdapter;

    $guzzleConfig = [
        'http_timeout' => 6,
        'http_connect_timeout' => 2,
        'verify' => false,
    ];
    $guzzleClient = new GuzzleClient($guzzleConfig);

    $adapter = new GuzzleAdapter($guzzleClient);

	$api = new Instaphp\Instaphp([
		'client_id' => 'your client id',
		'client_secret' => 'your client secret',
		'redirect_uri' => 'http://somehost.foo/callback.php',
		'scope' => 'comments+likes'
	], $adapter);

?>
```