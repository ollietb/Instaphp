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

include_once 'InstagramTest.php';

use GuzzleHttp\Psr7\Request;
use Http\Discovery\StreamFactoryDiscovery;
use Instaphp\Exceptions\InstagramException;
use Psr\Http\Message\ResponseInterface as Response;

class ErrorsTest extends InstagramTest
{
    /**
     * @var Instagram
     */
    protected $object;

    /**
     * @covers \Instaphp\Instagram\Instagram:parseResponse
     * @expectedException \Instaphp\Exceptions\APIAgeGatedError
     */
    public function testAPIAgeGatedError()
    {
        $this->mockResponse(400, '{"meta": {"error_type": "APIAgeGatedError", "code": 400, "error_message": "you cannot view this resource"}}');

        $this->object = new Users($this->config);

        $this->object->Recent(5830, ['count' => 5]);
    }

    /**
     * @covers \Instaphp\Instagram\Instagram:parseResponse
     * @expectedException \Instaphp\Exceptions\InvalidResponseFormatException
     */
    public function testHTMLPageNotFoundError()
    {
        $this->mockResponse(404, '<html><body>Page not found stub</body></html>', 'text/html');

        $this->object = new Users($this->config);

        $this->object->Recent(5830, ['count' => 5]);
    }

    /**
     * @covers \Instaphp\Instagram\Instagram:parseResponse
     *
     * @expectedException        \Instaphp\Exceptions\Exception
     * @expectedExceptionMessage Test
     */
    public function testErrorEvent()
    {
        $this->config['event.before'] = function (Request $request) {
            $request = new Request('GET', 'http://xxxxx');

            return $request;
        };

        $this->config['event.error'] = function (\Exception $exception) {
            return new InstagramException('Test');
        };

        $this->object = new Users($this->config);

        $this->object->Recent(5830, ['count' => 5]);
    }

    /**
     * Swaps Instagram response with new one.
     * Easiest way of mocking responses without changing Instaphp source code.
     *
     * @param int         $statusCode
     * @param string|null $body
     * @param string|null $contentType
     */
    protected function mockResponse($statusCode, $body = null, $contentType = null)
    {
        /**
         * @param \Psr\Http\Message\ResponseInterface $response
         * @return \Psr\Http\Message\ResponseInterface
         */
        $this->config['event.after'] = function (Response $response) use ($statusCode, $body, $contentType) {
            $response = $response->withStatus($statusCode);

            if (null !== $body) {
                $streamFactory = StreamFactoryDiscovery::find();

                /** @var Response $response */
                $response      = $response->withBody($streamFactory->createStream($body));
            }

            if (null !== $contentType) {
                /** @var Response $response */
                $response = $response->withHeader('content-type', $contentType);
            }

            return $response;
        };
    }
}
