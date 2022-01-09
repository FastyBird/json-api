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

use FastyBird\JsonApi\Helpers;
use FastyBird\JsonApi\JsonApi;
use FastyBird\JsonApi\Middleware;
use FastyBird\JsonApi\Schemas;
use Nette;
use Nette\DI;
use Nette\Schema;
use stdClass;

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

	/**
	 * @param Nette\Configurator $config
	 * @param string $extensionName
	 *
	 * @return void
	 */
	public static function register(
		Nette\Configurator $config,
		string $extensionName = 'fbJsonApi'
	): void {
		$config->onCompile[] = function (
			Nette\Configurator $config,
			DI\Compiler $compiler
		) use ($extensionName): void {
			$compiler->addExtension($extensionName, new JsonApiExtension());
		};
	}

	/**
	 * {@inheritDoc}
	 */
	public function getConfigSchema(): Schema\Schema
	{
		return Schema\Expect::structure([
			'meta' => Schema\Expect::structure([
				'author'    => Schema\Expect::anyOf(Schema\Expect::string(), Schema\Expect::array())
					->default('FastyBird team'),
				'copyright' => Schema\Expect::string()
					->default(null)->nullable(),
			]),
			'middleware' => Schema\Expect::structure([
				'priority' => Schema\Expect::int()->default(100),
			]),
		]);
	}

	/**
	 * {@inheritDoc}
	 */
	public function loadConfiguration(): void
	{
		$builder = $this->getContainerBuilder();
		/** @var stdClass $configuration */
		$configuration = $this->getConfig();

		$builder->addDefinition($this->prefix('middlewares.jsonapi'), new DI\Definitions\ServiceDefinition())
			->setType(Middleware\JsonApiMiddleware::class)
			->setArgument('metaAuthor', $configuration->meta->author)
			->setArgument('metaCopyright', $configuration->meta->copyright)
			->setTags([
				'middleware' => [
					'priority' => $configuration->middleware->priority,
				],
			]);

		$builder->addDefinition($this->prefix('schemas.container'), new DI\Definitions\ServiceDefinition())
			->setType(JsonApi\JsonApiSchemaContainer::class);

		if (class_exists('\IPub\DoctrineCrud\Mapping\Annotation\Crud')) {
			$builder->addDefinition($this->prefix('helpers.crudReader'), new DI\Definitions\ServiceDefinition())
				->setType(Helpers\CrudReader::class);
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function beforeCompile(): void
	{
		parent::beforeCompile();

		$builder = $this->getContainerBuilder();

		/**
		 * JSON:API SCHEMAS
		 */

		$schemaContainerServiceName = $builder->getByType(JsonApi\JsonApiSchemaContainer::class, true);

		if ($schemaContainerServiceName !== null) {
			$schemaContainerService = $builder->getDefinition($schemaContainerServiceName);
			assert($schemaContainerService instanceof DI\Definitions\ServiceDefinition);

			$schemasServices = $builder->findByType(Schemas\IJsonApiSchema::class);

			foreach ($schemasServices as $schemasService) {
				$schemaContainerService->addSetup('add', [$schemasService]);
			}
		}
	}

}
