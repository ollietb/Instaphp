<?php

/*
 * The MIT License (MIT)
 * Copyright © 2013 Randy Sesser <randy@instaphp.com>
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the “Software”), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * THE SOFTWARE IS PROVIDED “AS IS”, WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 * @author Randy Sesser <randy@instaphp.com>
 * @filesource
 */

namespace Instaphp\Instagram;

use Http\Client\Common\Plugin\ContentLengthPlugin;
use Http\Client\Common\Plugin\HeaderSetPlugin;
use Http\Client\Common\Plugin\LoggerPlugin;
use Http\Client\Common\PluginClient;
use Http\Client\Exception\HttpException;
use Http\Client\HttpClient;
use Http\Discovery\HttpClientDiscovery;
use Http\Discovery\MessageFactoryDiscovery;
use Http\Message\MessageFactory;
use Instaphp\Exceptions\APIAgeGatedError;
use Instaphp\Exceptions\APIInvalidParametersError;
use Instaphp\Exceptions\APINotAllowedError;
use Instaphp\Exceptions\APINotFoundError;
use Instaphp\Exceptions\Exception as InstaphpException;
use Instaphp\Exceptions\HttpException as InstaphpHttpException;
use Instaphp\Exceptions\OAuthAccessTokenException;
use Instaphp\Exceptions\OAuthParameterException;
use Instaphp\Exceptions\OAuthRateLimitException;
use Instaphp\Http\Plugin\InstaphpPlugin;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface;

/**
 * The base Instagram API object.
 *
 * All APIs inherit from this base class. It provides helper methods for making
 * HTTP requests and handling errors returned from API.
 *
 * @author Randy Sesser <randy@instaphp.com>
 * @license http://instaphp.mit-license.org MIT License
 *
 * @version 2.0-dev
 */
class Instagram
{
    const BASE_URL = 'https://api.instagram.com';

    /** @var array The configuration for Instaphp */
    protected $config = [];

    /** @var string The client_id for requests */
    protected $client_id = '';

    /** @var string The client_secret for requesting access_tokens */
    protected $client_secret = '';

    /** @var string The access_token for authenticated requests */
    protected $access_token = '';

    /** @var string The IP address of the client * */
    protected $client_ip = '';

    /** @var array The currently authenticated user */
    protected $user = [];

    /** @var bool Are we in debug mode */
    protected $debug = false;

    /** @var HttpClient The Http client for making requests to the API */
    protected $http = null;

    /** @var \Monolog\Logger The Monolog log object */
    protected $log = null;

    /** @var \Http\Message\MessageFactory */
    protected $message_factory;

    /**
     * Instagram constructor.
     *
     * @param array               $config
     * @param HttpClient|null     $client
     * @param MessageFactory|null $messageFactory
     * @param array               $plugins        An array of plugins @see http://docs.php-http.org/en/latest/plugins/index.html
     */
    public function __construct(array $config, HttpClient $client = null, MessageFactory $messageFactory = null, $plugins = [])
    {
        $this->config        = $config;
        $this->client_id     = $this->config['client_id'];
        $this->client_secret = $this->config['client_secret'];
        $this->access_token  = $this->config['access_token'];
        $this->client_ip     = $this->config['client_ip'];

        $this->createClient($client, $messageFactory, $plugins);
    }

    /**
     * @param \Http\Client\HttpClient $client
     */
    public function setHttpClient(HttpClient $client)
    {
        $this->http = $client;
    }

    /**
     * @return \Http\Client\Common\PluginClient|\Http\Client\HttpClient
     */
    public function getHttpClient()
    {
        return $this->http;
    }

    /**
     * Set the access_token for all future requests made with the current instance.
     *
     * @param string $access_token A valid access_token
     */
    public function setAccessToken($access_token)
    {
        $this->access_token = $access_token;
    }

    /**
     * Get the access_token currently in use.
     *
     * @return string
     */
    public function getAccessToken()
    {
        return $this->access_token;
    }

    /**
     * @return array
     */
    public function getCurrentUser()
    {
        return $this->user;
    }

    /**
     * Checks the existance of an access_token and assumes the user is logged in
     * and has authorized this site.
     *
     * @return bool
     */
    public function isAuthorized()
    {
        return !empty($this->access_token);
    }

    /**
     * Makes a GET request to the API.
     *
     * @param string $path    The path of the request
     * @param array  $params  Parameters to pass to the API
     * @param array  $headers Additional headers to pass in the HTTP call
     *
     * @throws InstaphpException
     *
     * @return \Instaphp\Instagram\Response
     */
    protected function get($path, array $params = [], array $headers = [])
    {
        $query = $this->prepare($params);

        $queryString = http_build_query($query);

        $uri = $this->buildPath($path).'?'.$queryString;

        try {
            $response = $this->http->sendRequest(
              $this->message_factory->createRequest('GET', $uri, $headers)
            );
        } catch (HttpException $e) {
            throw new InstaphpException($e->getMessage(), $e->getCode(), $e);
        } catch (\Exception $e) {
            // Wrap exception to conform to the Interface
            throw new InstaphpException($e->getMessage(), $e->getCode(), $e);
        }

        return $this->parseResponse($response, $uri);
    }

    /**
     * Makes a POST request to the API.
     *
     * @param string $path The path of the request
     * @param array $params Parameters to pass to the API
     * @param array $headers Additional headers to pass in the HTTP call
     *
     * @param bool $addVersion
     * @return \Instaphp\Instagram\Response
     * @throws \Instaphp\Exceptions\Exception
     */
    protected function post($path, array $params = [], array $headers = [], $addVersion = false)
    {
        $query = $this->prepare($params);

        $uri = $this->buildPath($path, $addVersion);

        try {
            $response = $this->http->sendRequest(
              $this->message_factory->createRequest('POST', $uri, $headers, http_build_query($query))
            );
        } catch (HttpException $e) {
            throw new InstaphpException($e->getMessage(), $e->getCode(), $e);
        } catch (\Exception $e) {
            // Wrap exception to conform to the Interface
            throw new InstaphpException($e->getMessage(), $e->getCode(), $e);
        }

        return $this->parseResponse($response, $uri);
    }

    /**
     * Makes a DELETE request to the API.
     *
     * @param string $path    The path of the request
     * @param array  $params  Parameters to pass to the API
     * @param array  $headers Additional headers to pass in the HTTP call
     *
     * @throws InstaphpException
     *
     * @return \Instaphp\Instagram\Response
     */
    protected function delete($path, array $params = [], array $headers = [])
    {
        $query = $this->prepare($params);

        $uri = $this->buildPath($path);

        try {
            $response = $this->http->sendRequest(
              $this->message_factory->createRequest('DELETE', $uri, $headers, http_build_query($query))
            );
        } catch (HttpException $e) {
            throw new InstaphpException($e->getMessage(), $e->getCode(), $e);
        } catch (\Exception $e) {
            // Wrap exception to conform to the Interface
            throw new InstaphpException($e->getMessage(), $e->getCode(), $e);
        }

        return $this->parseResponse($response, $uri);
    }

    /**
     * Adds the api_version to the beginning of the path.
     *
     * @param string $path
     * @param bool   $add_version
     *
     * @return string Returns the corrected path
     */
    protected function buildPath($path, $add_version = true)
    {
        $path = sprintf('/%s/', $path);
        $path = preg_replace('/[\/]{2,}/', '/', $path);

        if ($add_version && !preg_match('/^\/v1/', $path)) {
            $path = '/v1'.$path;
        }

        // Some endpoints don't respond with a trailing slash
        $path = rtrim($path, '/');

        return self::BASE_URL.$path;
    }

    /**
     * Works like sprintf, but urlencodes it's arguments.
     *
     * @param string   $path Path (in sprintf format)
     * @param mixed... $args Arguments to be urlencoded and passed to sprintf
     *
     * @return string
     */
    protected function formatPath($path)
    {
        $args = func_get_args();
        $path = array_shift($args);
        $args = array_map('urlencode', $args);

        return vsprintf($path, $args);
    }

    /**
     * Parses both the {@link \Instaphp\Utils\Http\Response HTTP Response} and
     * the {@link \Instaphp\Instagram\Response Instagram Response} and scans them
     * for errors and throws the apropriate exception. If there's no errors,
     * this method returns the Instagram Response object.
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     * @param null|mixed                          $uri
     *
     * @throws \Instaphp\Exceptions\APIAgeGatedError
     * @throws \Instaphp\Exceptions\APIInvalidParametersError
     * @throws \Instaphp\Exceptions\APINotAllowedError
     * @throws \Instaphp\Exceptions\APINotFoundError
     * @throws InstaphpException
     * @throws \Instaphp\Exceptions\HttpException
     * @throws \Instaphp\Exceptions\InvalidResponseFormatException
     * @throws \Instaphp\Exceptions\OAuthAccessTokenException
     * @throws \Instaphp\Exceptions\OAuthParameterException
     * @throws \Instaphp\Exceptions\OAuthRateLimitException
     *
     * @return \Instaphp\Instagram\Response
     */
    protected function parseResponse(ResponseInterface $response, $uri = null)
    {
        if ($response == null) {
            throw new InstaphpException('Response object is NULL');
        }
        $igresponse = new \Instaphp\Instagram\Response($response, $uri);

        //-- First check if there's an API error from the Instagram response
        if (isset($igresponse->meta['error_type'])) {
            switch ($igresponse->meta['error_type']) {
                case 'OAuthParameterException':
                    throw new OAuthParameterException($igresponse->meta['error_message'], $igresponse->meta['code'], $igresponse);
                    break;
                case 'OAuthRateLimitException':
                    throw new OAuthRateLimitException($igresponse->meta['error_message'], $igresponse->meta['code'], $igresponse);
                    break;
                case 'OAuthAccessTokenException':
                    throw new OAuthAccessTokenException($igresponse->meta['error_message'], $igresponse->meta['code'], $igresponse);
                    break;
                case 'APINotFoundError':
                    throw new APINotFoundError($igresponse->meta['error_message'], $igresponse->meta['code'], $igresponse);
                    break;
                case 'APINotAllowedError':
                    throw new APINotAllowedError($igresponse->meta['error_message'], $igresponse->meta['code'], $igresponse);
                    break;
                case 'APIInvalidParametersError':
                    throw new APIInvalidParametersError($igresponse->meta['error_message'], $igresponse->meta['code'], $igresponse);
                    break;
                case 'APIAgeGatedError':
                    throw new APIAgeGatedError($igresponse->meta['error_message'], $igresponse->meta['code'], $igresponse);
                    break;
                default:
                    break;
            }
        }
        //-- Next, look at the HTTP status code for 500 errors when Instagram is
        //-- either down or just broken (like it seems to be a lot lately)
        switch ($response->getStatusCode()) {
            case 500:
            case 502:
            case 503:
            case 400: //-- 400 error slipped through?
                throw new InstaphpHttpException($response->getReasonPhrase(), $response->getStatusCode(), $igresponse);
                break;
            case 429:
                throw new OAuthRateLimitException($igresponse->meta['error_message'], 429, $igresponse);
                break;
            default: //-- no error then?
                break;
        }

        return $igresponse;
    }

    /**
     * Simply prepares the parameters being passed. Automatically set the client_id
     * unless there is an access_token, in which case it is added instead.
     *
     * @param array $params The list of parameters to prepare for a request
     * @param bool  $encode Whether the params should be urlencoded
     *
     * @return array The prepared parameters
     */
    private function prepare(array $params)
    {
        $params['client_id'] = $this->client_id;
        if (!empty($this->access_token)) {
            unset($params['client_id']);
            $params['access_token'] = $this->access_token;
        }

        return $params;
    }

    private function createClient($client, $messageFactory, $plugins = []) {

        if (null === $client) {
            $http = HttpClientDiscovery::find();
        } else {
            $http = $client;
        }

        if (null === $messageFactory) {
            $this->message_factory = MessageFactoryDiscovery::find();
        } else {
            $this->message_factory = $messageFactory;
        }

        // This plugin sets the headers
        $headerSetPlugin = new HeaderSetPlugin([
          'User-Agent'            => $this->config['http_useragent'],
          'X-Insta-Forwarded-For' => implode('|', [$this->config['client_ip'], hash_hmac('SHA256', $this->config['client_ip'], $this->config['client_secret'])]),
        ]);

        // This plugin ensures the content length is correct
        $contentLengthPlugin = new ContentLengthPlugin();

        $plugins += [$headerSetPlugin, $contentLengthPlugin];

        if ($this->config['log_enabled']) {
            $this->log = new Logger('instaphp');
            $this->log->pushHandler(new StreamHandler($this->config['log_path'], $this->config['log_level']));

            // This plugin pushes logs to the log_path
            $loggerPlugin = new LoggerPlugin($this->log);

            $plugins[] = $loggerPlugin;
        }

        // This plugin uses the callables from config to hook into events
        $instaphpPlugin = new InstaphpPlugin($this->config);

        $plugins[] = $instaphpPlugin;

        $this->http = new PluginClient(
          $http,
          $plugins
        );
    }
}
