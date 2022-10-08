<?php declare(strict_types = 1);

/**
 * CrudReader.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:JsonApi!
 * @subpackage     Helpers
 * @since          0.2.0
 *
 * @date           20.05.21
 */

namespace FastyBird\JsonApi\Helpers;

use Doctrine\Common;
use IPub\DoctrineCrud;
use ReflectionProperty;

/**
 * Doctrine CRUD annotation reader
 *
 * @package            FastyBird:JsonApi!
 * @subpackage         Helpers
 *
 * @author             Adam Kadlec <adam.kadlec@fastybird.com>
 */
class CrudReader
{

	private Common\Annotations\Reader $annotationReader;

	public function __construct(Common\Cache\Cache|null $cache = null)
	{
		$this->annotationReader = $cache !== null ? new Common\Annotations\PsrCachedReader(
			new Common\Annotations\AnnotationReader(),
			Common\Cache\Psr6\CacheAdapter::wrap($cache),
		) : new Common\Annotations\AnnotationReader();
	}

	/**
	 * @return Array<bool>
	 */
	public function read(ReflectionProperty $rp): array
	{
		/** @phpstan-ignore-next-line */
		$crud = $this->annotationReader->getPropertyAnnotation($rp, DoctrineCrud\Mapping\Annotation\Crud::class);

		/** @phpstan-ignore-next-line */
		if (!$crud instanceof DoctrineCrud\Mapping\Annotation\Crud) {
			return [false, false];
		}

		/** @phpstan-ignore-next-line */
		return [$crud->isRequired(), $crud->isWritable()];
	}

}
