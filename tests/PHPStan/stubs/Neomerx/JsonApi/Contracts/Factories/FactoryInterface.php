<?php declare(strict_types=1);

namespace Neomerx\JsonApi\Contracts\Factories;

use Neomerx\JsonApi\Contracts\Schema\SchemaContainerInterface;

interface FactoryInterface
{
	/**
	 * @param iterable<string, mixed> $schemas
	 *
	 * @return SchemaContainerInterface
	 */
	public function createSchemaContainer(iterable $schemas): SchemaContainerInterface;

}
