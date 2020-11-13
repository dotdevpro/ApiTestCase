<?php

declare(strict_types=1);

/*
 * This file is part of the ApiTestCase package.
 *
 * (c) Łukasz Chruściel
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ApiTestCase;

use Symfony\Component\HttpFoundation\Response;

/**
 * @mixin ApiTestCase
 */
trait JsonApiTestCaseTrait
{
    use ApiTestCaseTrait;

    /**
     * Asserts that response has JSON content.
     * If filename is set, asserts that response content matches the one in given file.
     * If statusCode is set, asserts that response has given status code.
     *
     * @param Response|object $response
     *
     * @throws \Exception
     */
    protected function assertResponse($response, string $filename, int $statusCode = 200): void
    {
        if (isset($_SERVER['OPEN_ERROR_IN_BROWSER']) && true === $_SERVER['OPEN_ERROR_IN_BROWSER']) {
            $this->showErrorInBrowserIfOccurred($response);
        }

        if ($response instanceof Response) {
            $this->assertResponseCode($response, $statusCode);
            $this->assertJsonHeader($response);
        }

        $this->assertJsonResponseContent($response, $filename);
    }

    protected function assertJsonHeader(Response $response): void
    {
        parent::assertHeader($response, 'application');
        parent::assertHeader($response, 'json');
    }

    /**
     * Asserts that response has JSON content matching the one given in file.
     *
     * @param Response|object $response
     */
    protected function assertJsonResponseContent($response, string $filename): void
    {
        $this->assertResponseContent($this->prettifyJson($response->getContent()), $filename, 'json');
    }

    protected function prettifyJson($content): string
    {
        $jsonFlags = \JSON_PRETTY_PRINT;
        if (!isset($_SERVER['ESCAPE_JSON']) || true !== $_SERVER['ESCAPE_JSON']) {
            $jsonFlags |= \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES;
        }

        /** @var string $encodedContent */
        $encodedContent = json_encode(json_decode($content, true), $jsonFlags);

        return $encodedContent;
    }
}
