<?php declare(strict_types = 1);

namespace Tests\Cases;

use FastyBird\NodeJsonApi\DI;
use FastyBird\NodeJsonApi\Middleware;
use FastyBird\NodeLibs\Boot;
use Ninjify\Nunjuck\TestCase\BaseTestCase;
use Tester\Assert;

require_once __DIR__ . '/../../../bootstrap.php';

final class ExtensionTest extends BaseTestCase
{

	public function testCompilersServices(): void
	{
		$configurator = Boot\Bootstrap::boot();
		$configurator->addParameters([
			'origin'   => 'com.fastybird.node',
			'rabbitmq' => [
				'queueName' => 'testingQueueName',
				'routing'   => [],
			],
		]);

		$configurator->addConfig(__DIR__ . DS . '..' . DS . '..' . DS . '..' . DS . 'common.neon');

		DI\NodeJsonApiExtension::register($configurator);

		$container = $configurator->createContainer();

		Assert::notNull($container->getByType(Middleware\JsonApiMiddleware::class));
	}

}

$test_case = new ExtensionTest();
$test_case->run();
