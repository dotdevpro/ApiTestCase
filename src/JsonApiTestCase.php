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

use Coduo\PHPMatcher\Matcher;
use Coduo\PHPMatcher\PHPMatcher;
use Symfony\Component\HttpFoundation\Response;

abstract class JsonApiTestCase extends ApiTestCase
{
    use JsonApiTestCaseTrait;

    /**
     * @before
     */
    public function setUpClient(): void
    {
        $this->client = static::createClient([], ['HTTP_ACCEPT' => 'application/json']);
    }
}
