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

use Instaphp\Exceptions\InvalidResponseFormatException;
use Psr\Http\Message\ResponseInterface as PsrResponse;

/**
 * A generic objec representing a response from the Instagram API.
 *
 * @author Randy Sesser <randy@instaphp.com>
 * @license http://instaphp.mit-license.org MIT License
 *
 * @version 2.0-dev
 */
class Response
{
    /**
     * The HTTP header in the response that holds the rate limit for this request.
     */
    const RATE_LIMIT_HEADER = 'x-ratelimit-limit';

    /**
     * The HTTP header in the response that holds the rate limit remaingin.
     */
    const RATE_LIMIT_REMAINING_HEADER = 'x-ratelimit-remaining';

    /** @var string The request url */
    public $url = '';

    /** @var array The request parameters */
    public $params = [];

    /** @var string The request method */
    public $method = '';

    /** @var array The data collection */
    public $data = [];

    /** @var array The meta collection */
    public $meta = [];

    /** @var array The pagination collection */
    public $pagination = [];

    /** @var string The access_token from OAuth requests */
    public $access_token = null;

    /** @var array The user collection from OAuth requests */
    public $user = [];

    /** @var array The HTTP headers returned from API. */
    public $headers = [];

    /** @var string The raw JSON response from the API */
    public $json = null;

    /** @var int The number of requests you're allowed to make to the API */
    public $limit = 0;

    /** @var int The number of requests you have remaining for this client/access_token */
    public $remaining = 0;

    public function __construct(PsrResponse $response, $uri = null)
    {
        $headers = $response->getHeaders();
        //-- this is a hack on my part and I'm terribly sorry it exists
        //-- but deal with it.
        foreach ($headers as $header => $value) {
            $this->headers[$header] = implode(',', array_values((array) $value));
        }

        if (null !== $uri) {
            $this->url = $uri;

            // set the query params in $this->params
            $query = parse_url($this->url, PHP_URL_QUERY);
            parse_str(($query ?: ''), $this->params);
        }

        $this->json = json_decode($response->getBody(), true);

        switch (json_last_error()) {
            case JSON_ERROR_NONE:
                $error = false;
                break;
            case JSON_ERROR_DEPTH:
                $error = ' - Maximum stack depth exceeded';
                break;
            case JSON_ERROR_STATE_MISMATCH:
                $error = ' - Underflow or the modes mismatch';
                break;
            case JSON_ERROR_CTRL_CHAR:
                $error = ' - Unexpected control character found';
                break;
            case JSON_ERROR_SYNTAX:
                $error = ' - Syntax error, malformed JSON';
                break;
            case JSON_ERROR_UTF8:
                $error = ' - Malformed UTF-8 characters, possibly incorrectly encoded';
                break;
            default:
                $error = ' - Unknown error';
                break;
        }

        if ($error) {
            throw new InvalidResponseFormatException($error);
        }

        // $json = json_decode($this->json, TRUE);
        $this->data = isset($this->json['data']) ? $this->json['data'] : [];
        $this->meta = isset($this->json['meta']) ? $this->json['meta'] : [];
        if (isset($this->json['code']) && $this->json['code'] !== 200) {
            $this->meta     = $this->json;
        }
        $this->pagination   = isset($this->json['pagination']) ? $this->json['pagination'] : [];
        $this->user         = isset($this->json['user']) ? $this->json['user'] : [];
        $this->access_token = isset($this->json['access_token']) ? $this->json['access_token'] : null;
        $this->limit        = (isset($this->headers[self::RATE_LIMIT_HEADER])) ? $this->headers[self::RATE_LIMIT_HEADER] : 0;
        $this->remaining    = (isset($this->headers[self::RATE_LIMIT_REMAINING_HEADER])) ? $this->headers[self::RATE_LIMIT_REMAINING_HEADER] : 0;
    }
}
