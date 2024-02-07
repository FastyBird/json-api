<?php declare(strict_types = 1);

/**
 * Text.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:JsonApi!
 * @subpackage     Hydrators
 * @since          0.1.0
 *
 * @date           26.05.20
 */

namespace FastyBird\JsonApi\Hydrators\Fields;

use IPub\JsonAPIDocument;
use function is_scalar;

/**
 * Entity text field
 *
 * @package        FastyBird:JsonApi!
 * @subpackage     Hydrators
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class TextField extends Field
{

	public function __construct(
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
	public function getValue(JsonAPIDocument\Objects\IStandardObject $attributes): string|null
	{
		$value = $attributes->get($this->getMappedName());

		return $value !== null && is_scalar($value)
			? ($this->isNullable && $value === '' ? null : (string) $value)
			: null;
	}

	public function isNullable(): bool
	{
		return $this->isNullable;
	}

}
