<?php declare(strict_types = 1);

/**
 * Number.php
 *
 * @license        More in license.md
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

/**
 * Entity numeric field
 *
 * @package        FastyBird:JsonApi!
 * @subpackage     Hydrators
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class NumberField extends Field
{

	/** @var bool */
	private $isDecimal = true;

	/** @var bool */
	private $isNullable = true;

	public function __construct(
		bool $isDecimal,
		bool $isNullable,
		string $mappedName,
		string $fieldName,
		bool $isRequired,
		bool $isWritable
	) {
		parent::__construct($mappedName, $fieldName, $isRequired, $isWritable);

		$this->isDecimal = $isDecimal;
		$this->isNullable = $isNullable;
	}

	/**
	 * @param JsonAPIDocument\Objects\IStandardObject<mixed> $attributes
	 *
	 * @return float|int|null
	 */
	public function getValue(JsonAPIDocument\Objects\IStandardObject $attributes)
	{
		$value = $attributes->get($this->getMappedName());

		return $value !== null ? ($this->isDecimal ? (float) $value : (int) $value) : null;
	}

	/**
	 * @return bool
	 */
	public function isNullable(): bool
	{
		return $this->isNullable;
	}

}
