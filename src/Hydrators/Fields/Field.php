<?php declare(strict_types = 1);

/**
 * Field.php
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

use Nette;

/**
 * Entity field
 *
 * @package        FastyBird:JsonApi!
 * @subpackage     Hydrators
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
abstract class Field implements IField
{

	use Nette\SmartObject;

	public function __construct(
		private string $mappedName,
		private string $fieldName,
		private bool $isRequired,
		private bool $isWritable,
	)
	{
	}

	public function getMappedName(): string
	{
		return $this->mappedName;
	}

	public function getFieldName(): string
	{
		return $this->fieldName;
	}

	public function isRequired(): bool
	{
		return $this->isRequired;
	}

	public function isWritable(): bool
	{
		return $this->isWritable;
	}

}
