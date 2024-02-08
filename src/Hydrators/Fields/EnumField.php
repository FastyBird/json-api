<?php declare(strict_types = 1);

/**
 * EnumField.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:JsonApi!
 * @subpackage     Hydrators
 * @since          0.1.0
 *
 * @date           08.02.24
 */

namespace FastyBird\JsonApi\Hydrators\Fields;

use IPub\JsonAPIDocument;
use ReflectionClass;

/**
 * Entity consistence enum field
 *
 * @package        FastyBird:JsonApi!
 * @subpackage     Hydrators
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class EnumField extends Field
{

	/**
	 * @param class-string $typeClass
	 */
	public function __construct(
		private readonly string $typeClass,
		private readonly bool $isNullable,
		string $mappedName,
		string $fieldName,
		bool $isRequired,
		bool $isWritable,
	)
	{
		parent::__construct($mappedName, $fieldName, $isRequired, $isWritable);
	}

	/**
	 * @param JsonAPIDocument\Objects\IStandardObject<string, mixed> $attributes
	 */
	public function getValue(JsonAPIDocument\Objects\IStandardObject $attributes): object|null
	{
		$value = $attributes->get($this->getMappedName());

		$rc = new ReflectionClass($this->typeClass);

		if ($rc->isEnum()) {
			$result = $value !== null ? $this->typeClass::from($value) : null;

			return $result instanceof $this->typeClass ? $result : null;
		}

		return null;
	}

	public function isNullable(): bool
	{
		return $this->isNullable;
	}

}
