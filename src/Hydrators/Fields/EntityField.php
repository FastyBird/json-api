<?php declare(strict_types = 1);

/**
 * Entity.php
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

/**
 * Entity field
 *
 * @package        FastyBird:JsonApi!
 * @subpackage     Hydrators
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
abstract class EntityField extends Field
{

	/**
	 * @var string
	 *
	 * @phpstan-var class-string
	 */
	private string $className;

	/** @var bool */
	private bool $nullable;

	/** @var bool */
	private bool $isRelationship;

	/**
	 * @param string $className
	 * @param bool $nullable
	 * @param string $mappedName
	 * @param bool $isRelationship
	 * @param string $fieldName
	 * @param bool $isRequired
	 * @param bool $isWritable
	 *
	 * @phpstan-param class-string $className
	 */
	public function __construct(
		string $className,
		bool $nullable,
		string $mappedName,
		bool $isRelationship,
		string $fieldName,
		bool $isRequired,
		bool $isWritable
	) {
		parent::__construct($mappedName, $fieldName, $isRequired, $isWritable);

		$this->className = $className;
		$this->nullable = $nullable;
		$this->isRelationship = $isRelationship;
	}

	/**
	 * @return string
	 *
	 * @phpstan-return class-string
	 */
	public function getClassName(): string
	{
		return $this->className;
	}

	/**
	 * @return bool
	 */
	public function isNullable(): bool
	{
		return $this->nullable;
	}

	/**
	 * @return bool
	 */
	public function isRelationship(): bool
	{
		return $this->isRelationship;
	}

}
