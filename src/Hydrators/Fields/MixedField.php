<?php declare(strict_types = 1);

/**
 * Mixed.php
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
 * Entity mixed value field
 *
 * @package        FastyBird:JsonApi!
 * @subpackage     Hydrators
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class MixedField extends Field
{

	/** @var bool */
	private $isNullable = true;

	public function __construct(
		bool $isNullable,
		string $mappedName,
		string $fieldName,
		bool $isRequired,
		bool $isWritable
	) {
		parent::__construct($mappedName, $fieldName, $isRequired, $isWritable);

		$this->isNullable = $isNullable;
	}

	/**
	 * @param JsonAPIDocument\Objects\IStandardObject<mixed> $attributes
	 *
	 * @return mixed|null
	 */
	public function getValue(JsonAPIDocument\Objects\IStandardObject $attributes)
	{
		return $attributes->get($this->getMappedName());
	}

	/**
	 * @return bool
	 */
	public function isNullable(): bool
	{
		return $this->isNullable;
	}

}
