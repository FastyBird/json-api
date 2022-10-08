<?php declare(strict_types = 1);

/**
 * IField.php
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

/**
 * Entity field interface
 *
 * @package        FastyBird:JsonApi!
 * @subpackage     Hydrators
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
interface IField
{

	public function getMappedName(): string;

	public function getFieldName(): string;

	public function isRequired(): bool;

	public function isWritable(): bool;

	/**
	 * @param JsonAPIDocument\Objects\IStandardObject<string, mixed> $attributes
	 */
	public function getValue(JsonAPIDocument\Objects\IStandardObject $attributes): mixed;

}
