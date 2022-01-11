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

	/** @var JsonApi\JsonApiSchemaContainer */
	private JsonApi\JsonApiSchemaContainer $jsonApiSchemaContainer;

	public function __construct(
		JsonApi\JsonApiSchemaContainer $jsonApiSchemaContainer
	) {
		$this->jsonApiSchemaContainer = $jsonApiSchemaContainer;

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
			$schema = $this->jsonApiSchemaContainer->getSchemaByClassName($hydrator->getEntityName());

			if ($schema->getType() === $document->getResource()->getType()) {
				return $hydrator;
			}
		}

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

}
