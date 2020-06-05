<?php declare(strict_types = 1);

/**
 * CollectionField.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:NodeJsonApi!
 * @subpackage     Hydrators
 * @since          0.1.0
 *
 * @date           26.05.20
 */

namespace FastyBird\NodeJsonApi\Hydrators\Fields;

use FastyBird\NodeJsonApi\Exceptions;
use IPub\JsonAPIDocument;

/**
 * Entity entities collection field
 *
 * @package        FastyBird:NodeJsonApi!
 * @subpackage     Hydrators
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class CollectionField extends EntityField
{

	/**
	 * @param JsonAPIDocument\Objects\IStandardObject<mixed> $attributes
	 *
	 * @return void
	 */
	public function getValue(JsonAPIDocument\Objects\IStandardObject $attributes): void
	{
		throw new Exceptions\InvalidStateException(sprintf('Collection field \'%s\' could not be mapped as attribute.', $this->getMappedName()));
	}

}
