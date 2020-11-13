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

abstract class XmlApiTestCase extends ApiTestCase
{
    use XmlApiTestCaseTrait;

    /**
     * @before
     */
    public function setUpClient(): void
    {
        $this->client = static::createClient([], ['HTTP_ACCEPT' => 'application/xml']);
    }
}
