<?php declare(strict_types = 1);

/**
 * HydratorsContainer.php
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
class HydratorsContainer
{

	/** @var SplObjectStorage<Hydrator, null> */
	private SplObjectStorage $hydrators;

	/** @var DI\Container */
	private DI\Container $container;

	/** @var Log\LoggerInterface */
	private Log\LoggerInterface $logger;

	/** @var JsonApi\JsonApiSchemaContainer|null */
	private ?JsonApi\JsonApiSchemaContainer $jsonApiSchemaContainer = null;

	public function __construct(
		DI\Container $container,
		?Log\LoggerInterface $logger = null
	) {
		$this->container = $container;
		$this->logger = $logger ?? new Log\NullLogger();

		$this->hydrators = new SplObjectStorage();
	}

	/**
	 * @param JsonAPIDocument\IDocument $document
	 *
	 * @return Hydrator|null
	 *
	 * @phpstan-return Hydrator|null
	 */
	public function findHydrator(JsonAPIDocument\IDocument $document): ?Hydrator
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
			'source'   => 'hydrators-container',
			'type'     => 'find-hydrator',
			'document' => [
				'type' => $document->getResource()
					->getType(),
				'id'   => $document->getResource()
					->getId(),
			],
		]);

		return null;
	}

	/**
	 * @param Hydrator $hydrator
	 *
	 * @return void
	 *
	 * @phpstan-param Hydrator $hydrator
	 */
	public function add(Hydrator $hydrator): void
	{
		if (!$this->hydrators->contains($hydrator)) {
			$this->hydrators->attach($hydrator);
		}
	}

	/**
	 * @return JsonApi\JsonApiSchemaContainer
	 */
	private function getSchemaContainer(): JsonApi\JsonApiSchemaContainer
	{
		if ($this->jsonApiSchemaContainer !== null) {
			return $this->jsonApiSchemaContainer;
		}

		$this->jsonApiSchemaContainer = $this->container->getByType(JsonApi\JsonApiSchemaContainer::class);

		return $this->jsonApiSchemaContainer;
	}

}
