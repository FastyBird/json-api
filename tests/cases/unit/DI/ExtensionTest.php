<?php declare(strict_types = 1);

namespace FastyBird\JsonApi\Tests\Cases\Unit\DI;

use FastyBird\JsonApi\Builder;
use FastyBird\JsonApi\DI;
use FastyBird\JsonApi\JsonApi;
use FastyBird\JsonApi\Middleware;
use Nette;
use PHPUnit\Framework\TestCase;
use function md5;
use function time;

final class ExtensionTest extends TestCase
{

	/**
	 * @throws Nette\DI\MissingServiceException
	 */
	public function testCompilersServices(): void
	{
		$container = $this->createContainer();

		self::assertNotNull($container->getByType(Builder\Builder::class, false));
		self::assertNotNull($container->getByType(Middleware\JsonApi::class, false));
		self::assertNotNull($container->getByType(JsonApi\SchemaContainer::class, false));
	}

	protected function createContainer(): Nette\DI\Container
	{
		$rootDir = __DIR__ . '/../../../..';

		$config = new Nette\Bootstrap\Configurator();
		$config->setTempDirectory($rootDir . '/var/tmp');

		$config->addStaticParameters(['container' => ['class' => 'SystemContainer_' . md5((string) time())]]);
		$config->addStaticParameters(['appDir' => $rootDir, 'wwwDir' => $rootDir]);

		$config->addConfig(__DIR__ . '/../../../common.neon');

		DI\JsonApiExtension::register($config);

		return $config->createContainer();
	}

}
