<?php declare(strict_types = 1);

/**
 * MultipleErrorException.php
 *
 * @license        More in license.md
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
use Neomerx\JsonApi;

/**
 * Process multiple error
 *
 * @package        FastyBird:JsonApi!
 * @subpackage     Exceptions
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class JsonApiMultipleErrorException extends PHPException implements IJsonApiException
{

	/** @var JsonApi\Schema\Error[] */
	private array $errors = [];

	public function __construct()
	{
		parent::__construct(
			'Json:API multiple errors',
			StatusCodeInterface::STATUS_UNPROCESSABLE_ENTITY
		);
	}

	/**
	 * @param int $code
	 * @param string $title
	 * @param string|null $detail
	 * @param string[]|null $source
	 * @param string|null $type
	 *
	 * @return void
	 */
	public function addError(
		int $code,
		string $title,
		?string $detail = null,
		?array $source = null,
		?string $type = null
	): void {
		$this->errors[] = new JsonApi\Schema\Error(
			$type,
			null,
			null,
			(string) $code,
			(string) $code,
			$title,
			$detail,
			$source
		);
	}

	/**
	 * @return bool
	 */
	public function hasErrors(): bool
	{
		return $this->errors !== [];
	}

	/**
	 * @return JsonApi\Schema\Error[]
	 */
	public function getErrors(): array
	{
		return $this->errors;
	}

}
