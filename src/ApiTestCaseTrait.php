<?php

/*
 * This file is part of the ApiTestCase package.
 *
 * (c) Łukasz Chruściel
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ApiTestCase;

use Coduo\PHPMatcher\Factory\MatcherFactory;
use Coduo\PHPMatcher\Matcher;
use Coduo\PHPMatcher\PHPMatcher;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Webmozart\Assert\Assert;

/**
 * @mixin ApiTestCase
 */
trait ApiTestCaseTrait
{
    /** @var KernelBrowser|null */
    protected $client;

    /** @var string|null */
    protected $expectedResponsesPath;

    /** @var MatcherFactory */
    protected $matcherFactory;

    /**
     * @before
     */
    public function setUpClient(): void
    {
        $this->client = static::createClient(['debug' => false]);
    }

    protected function buildMatcher(): Matcher
    {
        $matcher = new PHPMatcher();

        return $this->getMatcherFactory()->createMatcher($matcher->backtrace());
    }

    protected function getMatcherFactory(): MatcherFactory
    {
        if (!$this->matcherFactory instanceof MatcherFactory) {
            $this->matcherFactory = new MatcherFactory();
        }

        return $this->matcherFactory;
    }

    protected static function getKernelClass(): string
    {
        if (isset($_SERVER['KERNEL_CLASS'])) {
            return '\\' . ltrim($_SERVER['KERNEL_CLASS'], '\\');
        }

        return parent::getKernelClass();
    }

    /**
     * Gets service from DIC.
     */
    protected function get(string $id)
    {
        $client = $this->client;
        Assert::notNull($client);

        $container = $client->getContainer();
        Assert::notNull($container);

        return $container->get($id);
    }

    protected function assertResponseCode(Response $response, int $statusCode): void
    {
        self::assertEquals($statusCode, $response->getStatusCode(), $response->getContent() ?: '');
    }

    protected function assertHeader(Response $response, string $contentType): void
    {
        $headerContentType = $response->headers->get('Content-Type');
        Assert::string($headerContentType);

        self::assertStringContainsString(
            $contentType,
            $headerContentType
        );
    }

    protected function assertResponseContent(string $actualResponse, string $filename, string $mimeType): void
    {
        $responseSource = $this->getExpectedResponsesFolder();

        $contents = file_get_contents(PathBuilder::build($responseSource, sprintf('%s.%s', $filename, $mimeType)));
        Assert::string($contents);

        $expectedResponse = trim($contents);

        $matcher = $this->buildMatcher();
        $actualResponse = trim($actualResponse);
        $result = $matcher->match($actualResponse, $expectedResponse);

        if (!$result) {
            $diff = new \Diff(explode(\PHP_EOL, $expectedResponse), explode(\PHP_EOL, $actualResponse), []);

            self::fail($matcher->getError() . \PHP_EOL . $diff->render(new \Diff_Renderer_Text_Unified()));
        }
    }

    private function getExpectedResponsesFolder(): string
    {
        if (null === $this->expectedResponsesPath) {
            $paths = array_filter(
                [
                    isset($_SERVER['EXPECTED_RESPONSE_DIR']) ?
                        PathBuilder::build($this->getProjectDir(), $_SERVER['EXPECTED_RESPONSE_DIR']) :
                        null,
                    PathBuilder::build($this->getCalledClassFolder(), 'Responses'),
                    PathBuilder::build($this->getCalledClassFolder(), '..', 'Responses'),
                ]
            );

            foreach ($paths as $path) {
                if (\is_dir($path)) {
                    $this->expectedResponsesPath = $path;

                    break;
                }
            }
        }

        if (null === $this->expectedResponsesPath) {
            throw new \RuntimeException();
        }

        return $this->expectedResponsesPath;
    }

    /** @ignore
    private function getExpectedResponsesFolder(): string
    {
        if (null === $this->expectedResponsesPath) {
            $this->expectedResponsesPath = isset($_SERVER['EXPECTED_RESPONSE_DIR']) ?
                PathBuilder::build($this->getProjectDir(), $_SERVER['EXPECTED_RESPONSE_DIR']) :
                PathBuilder::build($this->getCalledClassFolder(), '..', 'Responses');
        }

        return $this->expectedResponsesPath;
    }*/
    private function getCalledClassFolder(): string
    {
        $calledClass = get_called_class();

        /** @var string $fileName */
        $fileName = (new \ReflectionClass($calledClass))->getFileName();
        $calledClassFolder = dirname($fileName);

        $this->assertSourceExists($calledClassFolder);

        return $calledClassFolder;
    }

    private function assertSourceExists(string $source): void
    {
        if (!file_exists($source)) {
            throw new \RuntimeException(sprintf('File %s does not exist', $source));
        }
    }

    private function getProjectDir(): string
    {
        /** @var KernelInterface $kernel */
        $kernel = $this->get('kernel');

        return $kernel->getProjectDir();
    }
}
