<?php declare(strict_types = 1);

/**
 * MultipleErrorException.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:JsonApi!
 * @subpackage     Exceptions
 * @since          0.1.0
 *
 * @date           06.03.18
 */

namespace FastyBird\JsonApi\Exceptions;

use Exception as PHPException;
use Fig\Http\Message\StatusCodeInterface;
use Neomerx\JsonApi as NeomerxJsonApi;
use Stringable;

/**
 * Process multiple error
 *
 * @package        FastyBird:JsonApi!
 * @subpackage     Exceptions
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class JsonApiMultipleError extends PHPException implements JsonApi
{

	/** @var Array<NeomerxJsonApi\Schema\Error> */
	private array $errors = [];

	public function __construct()
	{
		parent::__construct(
			'Json:API multiple errors',
			StatusCodeInterface::STATUS_UNPROCESSABLE_ENTITY,
		);
	}

	/**
	 * @param Array<string>|null $source
	 */
	public function addError(
		int $code,
		string|Stringable $title,
		string|Stringable|null $detail = null,
		array|null $source = null,
		string|Stringable|null $type = null,
	): void
	{
		$this->errors[] = new NeomerxJsonApi\Schema\Error(
			$type,
			null,
			null,
			(string) $code,
			(string) $code,
			$title,
			$detail,
			$source,
		);
	}

	public function hasErrors(): bool
	{
		return $this->errors !== [];
	}

	/**
	 * @return Array<NeomerxJsonApi\Schema\Error>
	 */
	public function getErrors(): array
	{
		return $this->errors;
	}

}
