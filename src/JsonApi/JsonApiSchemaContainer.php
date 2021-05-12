<?php declare(strict_types = 1);

/**
 * JsonApiSchemaContainer.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:JsonApi!
 * @subpackage     JsonApi
 * @since          0.1.0
 *
 * @date           13.03.20
 */

namespace FastyBird\JsonApi\JsonApi;

use FastyBird\JsonApi\Schemas;
use Neomerx\JsonApi;

/**
 * Json:API schemas container
 *
 * @package        FastyBird:JsonApi!
 * @subpackage     JsonApi
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class JsonApiSchemaContainer extends JsonApi\Schema\SchemaContainer
{

	private const DOCTRINE_MARKER = '__CG__';
	private const DOCTRINE_MARKER_LENGTH = 6;

	public function __construct()
	{
		parent::__construct(new JsonApi\Factories\Factory(), []);
	}

	/**
	 * @param Schemas\IJsonApiSchema $schema
	 *
	 * @return void
	 *
	 * @phpstan-template T of Schemas\JsonApiSchema
	 * @phpstan-param    T $schema
	 */
	public function add(Schemas\IJsonApiSchema $schema): void
	{
		$this->setProviderMapping($schema->getEntityClass(), get_class($schema));
		$this->setResourceToJsonTypeMapping($schema->getType(), $schema->getEntityClass());
		$this->setCreatedProvider($schema->getEntityClass(), $schema);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function getResourceType($resource): string
	{
		if (interface_exists('\Doctrine\Common\Persistence\Proxy')) {
			$class = get_class($resource);

			$pos = strrpos($class, '\\' . self::DOCTRINE_MARKER . '\\');

			if ($pos === false) {
				return $class;
			}

			return substr($class, $pos + self::DOCTRINE_MARKER_LENGTH + 2);
		}

		return parent::getResourceType($resource);
	}

}
