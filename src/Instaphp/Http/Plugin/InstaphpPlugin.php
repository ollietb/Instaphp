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

namespace Instaphp\Http\Plugin;

use Http\Client\Common\Plugin;
use Http\Promise\Promise;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class InstaphpPlugin implements Plugin
{
    /**
     * @var array
     */
    private $config;

    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * Handle the request and return the response coming from the next callable.
     *
     * @param RequestInterface $request
     * @param callable         $next    Next middleware in the chain, the request is passed as the first argument
     * @param callable         $first   First middleware in the chain, used to to restart a request
     *
     * @return Promise resolves a PSR-7 Response or fails with an Http\Client\Exception (The same as HttpAsyncClient)
     */
    public function handleRequest(
      RequestInterface $request,
      callable $next,
      callable $first
    ) {
        if (!empty($this->config['event.before']) && is_callable($this->config['event.before'])) {
            $request = call_user_func_array($this->config['event.before'], [$request]);
        }

        $config = $this->config;

        return $next($request)->then(function (ResponseInterface $response) use ($config) {
            if (!empty($config['event.after']) && is_callable($config['event.after'])) {
                $response = call_user_func_array($this->config['event.after'], [$response]);
            }

            return $response;
        },
        // The failure callback
        function (\Exception $exception) use ($config) {
            if (!empty($config['event.error']) && is_callable($config['event.error'])) {
                $exception = call_user_func_array($this->config['event.error'], [$exception]);
            }
            throw $exception;
        });
    }
}
