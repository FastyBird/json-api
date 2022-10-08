<?php declare(strict_types = 1);

/**
 * Container.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:JsonApi!
 * @subpackage     Hydrators
 * @since          0.7.0
 *
 * @date           11.01.22
 */

namespace FastyBird\JsonApi\Hydrators;

use FastyBird\JsonApi\JsonApi;
use IPub\JsonAPIDocument;
use Nette\DI;
use Psr\Log;
use SplObjectStorage;

/**
 * API hydrators container
 *
 * @package        FastyBird:JsonApi!
 * @subpackage     Hydrators
 */
class Container
{

	/** @var SplObjectStorage<Hydrator, null> */
	private SplObjectStorage $hydrators;

	private Log\LoggerInterface $logger;

	private JsonApi\SchemaContainer|null $jsonApiSchemaContainer = null;

	public function __construct(
		private DI\Container $container,
		Log\LoggerInterface|null $logger = null,
	)
	{
		$this->logger = $logger ?? new Log\NullLogger();

		$this->hydrators = new SplObjectStorage();
	}

	/**
	 * @phpstan-return Hydrator|null
	 */
	public function findHydrator(JsonAPIDocument\IDocument $document): Hydrator|null
	{
		$this->hydrators->rewind();

		foreach ($this->hydrators as $hydrator) {
			$schema = $this->getSchemaContainer()
				->getSchemaByClassName($hydrator->getEntityName());

			if ($schema->getType() === $document->getResource()->getType()) {
				return $hydrator;
			}
		}

		$this->logger->debug('Hydrator for given document was not found', [
			'source' => 'hydrators-container',
			'type' => 'find-hydrator',
			'document' => [
				'type' => $document->getResource()
					->getType(),
				'id' => $document->getResource()
					->getId(),
			],
		]);

		return null;
	}

	/**
	 * @phpstan-param Hydrator $hydrator
	 */
	public function add(Hydrator $hydrator): void
	{
		if (!$this->hydrators->contains($hydrator)) {
			$this->hydrators->attach($hydrator);
		}
	}

	private function getSchemaContainer(): JsonApi\SchemaContainer
	{
		if ($this->jsonApiSchemaContainer !== null) {
			return $this->jsonApiSchemaContainer;
		}

		$this->jsonApiSchemaContainer = $this->container->getByType(JsonApi\SchemaContainer::class);

		return $this->jsonApiSchemaContainer;
	}

}
