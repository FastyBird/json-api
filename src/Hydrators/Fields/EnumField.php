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
 * @date           26.05.20
 */

namespace FastyBird\JsonApi\Hydrators\Fields;

use Consistence;
use IPub\JsonAPIDocument;
use function call_user_func;
use function is_callable;

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

	public function __construct(
		private string $typeClass,
		private bool $isNullable,
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
	public function getValue(JsonAPIDocument\Objects\IStandardObject $attributes): Consistence\Enum\Enum|null
	{
		$value = $attributes->get($this->getMappedName());

		$callable = [$this->typeClass, 'get'];

		if (is_callable($callable)) {
			$result = $value !== null ? call_user_func($callable, $value) : null;

			return $result instanceof Consistence\Enum\Enum ? $result : null;
		}

		return null;
	}

	public function isNullable(): bool
	{
		return $this->isNullable;
	}

}
