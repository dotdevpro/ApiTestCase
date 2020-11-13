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

use Coduo\PHPMatcher\Factory\MatcherFactory;
use Coduo\PHPMatcher\Matcher;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManager;
use Fidry\AliceDataFixtures\LoaderInterface;
use Fidry\AliceDataFixtures\ProcessorInterface;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\Service\ResetInterface;
use Webmozart\Assert\Assert;

abstract class ApiTestCase extends WebTestCase
{
    use ApiTestCaseTrait;

    /** @var KernelInterface */
    protected static $sharedKernel;

    /** @var string */
    protected $dataFixturesPath;

    /** @var LoaderInterface|null */
    private $fixtureLoader;

    /** @var EntityManager|null */
    private $entityManager;

    public function __construct(?string $name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);

        $this->matcherFactory = new MatcherFactory();
    }

    /**
     * @beforeClass
     */
    public static function createSharedKernel(): void
    {
        static::$sharedKernel = static::createKernel(['debug' => false]);
        static::$sharedKernel->boot();
    }

    /**
     * @afterClass
     */
    public static function ensureSharedKernelShutdown(): void
    {
        if (null !== static::$sharedKernel) {
            $container = static::$sharedKernel->getContainer();
            static::$sharedKernel->shutdown();
            if ($container instanceof ResetInterface) {
                $container->reset();
            }
        }
    }

    /**
     * @before
     */
    public function setUpClient(): void
    {
        $this->client = static::createClient(['debug' => false]);
    }

    /**
     * @before
     */
    public function setUpDatabase(): void
    {
        if (isset($_SERVER['IS_DOCTRINE_ORM_SUPPORTED']) && $_SERVER['IS_DOCTRINE_ORM_SUPPORTED']) {
            $container = static::$sharedKernel->getContainer();
            Assert::notNull($container);

            /** @var EntityManager $entityManager */
            $entityManager = $container->get('doctrine.orm.entity_manager');
            Assert::notNull($entityManager);

            $this->entityManager = $entityManager;
            $this->entityManager->getConnection()->connect();

            /** @var LoaderInterface $fixtureLoader */
            $fixtureLoader = $container->get('fidry_alice_data_fixtures.loader.doctrine');
            $this->fixtureLoader = $fixtureLoader;

            $this->purgeDatabase();
        }
    }

    protected function tearDown(): void
    {
        $this->client = null;
        $this->entityManager = null;
        $this->fixtureLoader = null;

        parent::tearDown();
    }

    abstract protected function buildMatcher(): Matcher;

    /**
     * @return ProcessorInterface[]
     */
    protected function getFixtureProcessors(): array
    {
        return [];
    }

    protected function purgeDatabase(): void
    {
        $purger = new ORMPurger($this->getEntityManager());
        $purger->purge();

        $this->getEntityManager()->clear();
    }

    /**
     * @throws \Exception
     */
    protected function showErrorInBrowserIfOccurred(Response $response): void
    {
        if (!$response->isSuccessful()) {
            $openCommand = $_SERVER['OPEN_BROWSER_COMMAND'] ?? 'open %s';
            $tmpDir = $_SERVER['TMP_DIR'] ?? sys_get_temp_dir();

            $filename = PathBuilder::build(rtrim($tmpDir, \DIRECTORY_SEPARATOR), uniqid() . '.html');
            file_put_contents($filename, $response->getContent());
            system(sprintf($openCommand, escapeshellarg($filename)));

            throw new \Exception('Internal server error.');
        }
    }

    protected function loadFixturesFromDirectory(string $source = ''): array
    {
        $source = $this->getFixtureRealPath($source);
        $this->assertSourceExists($source);

        $finder = new Finder();
        $finder->files()->name('*.yml')->in($source);

        if (0 === $finder->count()) {
            throw new \RuntimeException(sprintf('There is no files to load in folder %s', $source));
        }

        $files = [];
        foreach ($finder as $file) {
            $files[] = $file->getRealPath();
        }

        return $this->getFixtureLoader()->load(array_filter($files));
    }

    protected function loadFixturesFromFile(string $source): array
    {
        $source = $this->getFixtureRealPath($source);
        $this->assertSourceExists($source);

        return $this->getFixtureLoader()->load([$source]);
    }

    /**
     * @param string[] $sources
     *
     * @return object[]
     */
    protected function loadFixturesFromFiles(array $sources): array
    {
        $realPaths = [];

        foreach ($sources as $source) {
            $source = $this->getFixtureRealPath($source);
            $this->assertSourceExists($source);

            $realPaths[] = $source;
        }

        return $this->getFixtureLoader()->load($realPaths);
    }

    protected function getFixtureLoader(): LoaderInterface
    {
        if (null === $this->fixtureLoader) {
            throw new \RuntimeException('Please, set up a database before you will try to use a fixture loader');
        }

        return $this->fixtureLoader;
    }

    protected function getEntityManager(): EntityManager
    {
        $entityManager = $this->entityManager;
        if (null === $entityManager || !$entityManager->getConnection()->isConnected()) {
            static::fail('Could not establish test database connection.');

            // PHPStan can not figure out that this part of the code should never be reached
            throw new InvalidArgumentException('Could not establish test database connection.');
        }

        return $entityManager;
    }

    private function getFixtureRealPath(string $source): string
    {
        $baseDirectory = $this->getFixturesFolder();

        return PathBuilder::build($baseDirectory, $source);
    }

    private function getFixturesFolder(): string
    {
        if (null === $this->dataFixturesPath) {
            $this->dataFixturesPath = isset($_SERVER['FIXTURES_DIR']) ?
                PathBuilder::build($this->getProjectDir(), $_SERVER['FIXTURES_DIR']) :
                PathBuilder::build($this->getCalledClassFolder(), '..', 'DataFixtures', 'ORM');
        }

        return $this->dataFixturesPath;
    }
}
