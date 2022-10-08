<?php declare(strict_types = 1);

/**
 * JsonApiExtension.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:JsonApi!
 * @subpackage     DI
 * @since          0.1.0
 *
 * @date           27.05.20
 */

namespace FastyBird\JsonApi\DI;

use FastyBird\JsonApi\Builder;
use FastyBird\JsonApi\Helpers;
use FastyBird\JsonApi\Hydrators;
use FastyBird\JsonApi\JsonApi;
use FastyBird\JsonApi\Middleware;
use FastyBird\JsonApi\Schemas;
use Nette;
use Nette\DI;
use Nette\Schema;
use stdClass;
use function assert;
use function class_exists;

/**
 * {JSON:API} api extension container
 *
 * @package        FastyBird:JsonApi!
 * @subpackage     DI
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class JsonApiExtension extends DI\CompilerExtension
{

	public static function register(
		Nette\Configurator $config,
		string $extensionName = 'fbJsonApi',
	): void
	{
		$config->onCompile[] = static function (
			Nette\Configurator $config,
			DI\Compiler $compiler,
		) use ($extensionName): void {
			$compiler->addExtension($extensionName, new JsonApiExtension());
		};
	}

	public function getConfigSchema(): Schema\Schema
	{
		return Schema\Expect::structure([
			'meta' => Schema\Expect::structure([
				'author' => Schema\Expect::anyOf(Schema\Expect::string(), Schema\Expect::array())
					->default('FastyBird team'),
				'copyright' => Schema\Expect::string()
					->default(null)
					->nullable(),
			]),
		]);
	}

	public function loadConfiguration(): void
	{
		$builder = $this->getContainerBuilder();
		$configuration = $this->getConfig();
		assert($configuration instanceof stdClass);

		$builder->addDefinition($this->prefix('builder'), new DI\Definitions\ServiceDefinition())
			->setType(Builder\Builder::class)
			->setArgument('metaAuthor', $configuration->meta->author)
			->setArgument('metaCopyright', $configuration->meta->copyright);

		$builder->addDefinition($this->prefix('middlewares.jsonapi'), new DI\Definitions\ServiceDefinition())
			->setType(Middleware\JsonApi::class);

		$builder->addDefinition($this->prefix('hydrators.container'), new DI\Definitions\ServiceDefinition())
			->setType(Hydrators\Container::class);

		$builder->addDefinition($this->prefix('schemas.container'), new DI\Definitions\ServiceDefinition())
			->setType(JsonApi\SchemaContainer::class);

		if (class_exists('\IPub\DoctrineCrud\Mapping\Annotation\Crud')) {
			$builder->addDefinition($this->prefix('helpers.crudReader'), new DI\Definitions\ServiceDefinition())
				->setType(Helpers\CrudReader::class);
		}
	}

	public function beforeCompile(): void
	{
		parent::beforeCompile();

		$builder = $this->getContainerBuilder();

		/**
		 * JSON:API SCHEMAS
		 */

		$schemaContainerServiceName = $builder->getByType(JsonApi\SchemaContainer::class, true);

		if ($schemaContainerServiceName !== null) {
			$schemaContainerService = $builder->getDefinition($schemaContainerServiceName);
			assert($schemaContainerService instanceof DI\Definitions\ServiceDefinition);

			$schemasServices = $builder->findByType(Schemas\JsonApi::class);

			foreach ($schemasServices as $schemasService) {
				$schemaContainerService->addSetup('add', [$schemasService]);
			}
		}

		/**
		 * JSON:API HYDRATORS
		 */

		$hydratorContainerServiceName = $builder->getByType(Hydrators\Container::class, true);

		if ($hydratorContainerServiceName !== null) {
			$hydratorContainerService = $builder->getDefinition($hydratorContainerServiceName);
			assert($hydratorContainerService instanceof DI\Definitions\ServiceDefinition);

			$hydratorsServices = $builder->findByType(Hydrators\Hydrator::class);

			foreach ($hydratorsServices as $hydratorService) {
				$hydratorContainerService->addSetup('add', [$hydratorService]);
			}
		}
	}

}
