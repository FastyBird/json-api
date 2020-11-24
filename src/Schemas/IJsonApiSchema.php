<?php declare(strict_types = 1);

/**
 * IJsonApiSchema.php
 *
 * @license        More in license.md
 * @copyright      https://fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:JsonApi!
 * @subpackage     Schemas
 * @since          0.1.0
 *
 * @date           13.03.20
 */

namespace FastyBird\JsonApi\Schemas;

use Neomerx\JsonApi\Contracts;

/**
 * @template T of object
 * @extends  Contracts\Schema\SchemaInterface<T>
 */
interface IJsonApiSchema extends Contracts\Schema\SchemaInterface
{

	/**
	 * @return string
	 */
	public function getEntityClass(): string;

}
