<?php declare(strict_types = 1);

/**
 * JsonApiExtension.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:JsonApi!
 * @subpackage     DI
 * @since          0.1.0
 *
 * @date           27.05.20
 */

namespace FastyBird\JsonApi\DI;

use Contributte\Translation;
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
class JsonApiExtension extends DI\CompilerExtension implements Translation\DI\TranslationProviderInterface
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
					->default('FastyBird s.r.o'),
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

		$builder->addDefinition(null)
			->setType(Middleware\JsonApiMiddleware::class)
			->setArgument('metaAuthor', $configuration->meta->author)
			->setArgument('metaCopyright', $configuration->meta->copyright)
			->setTags([
				'middleware' => [
					'priority' => 100,
				],
			]);

		$builder->addDefinition(null)
			->setType(JsonApi\JsonApiSchemaContainer::class);
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

	/**
	 * @return string[]
	 */
	public function getTranslationResources(): array
	{
		return [
			__DIR__ . '/../Translations',
		];
	}

}
