<?php declare(strict_types = 1);

/**
 * SingleEntityField.php
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
 * Entity one to one relation entity field
 *
 * @package        FastyBird:NodeJsonApi!
 * @subpackage     Hydrators
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class SingleEntityField extends EntityField
{

	/**
	 * @param JsonAPIDocument\Objects\IStandardObject<mixed> $attributes
	 *
	 * @return mixed[]|null
	 */
	public function getValue(JsonAPIDocument\Objects\IStandardObject $attributes): ?array
	{
		if ($this->isRelationship()) {
			throw new Exceptions\InvalidStateException(sprintf('Single entity field \'%s\' could not be mapped as attribute.', $this->getMappedName()));
		}

		$value = $attributes->get($this->getMappedName());

		if ($value instanceof JsonAPIDocument\Objects\IStandardObject) {
			$value = $value->toArray();
		}

		if (is_array($value) && $value !== []) {
			$value['entity'] = $this->getClassName();

		} elseif ($this->isNullable()) {
			return null;
		}

		return $value;
	}

}
