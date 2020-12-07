<?php declare(strict_types = 1);

/**
 * JsonApiErrorException.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:JsonApi!
 * @subpackage     Exceptions
 * @since          0.1.0
 *
 * @date           12.04.19
 */

namespace FastyBird\JsonApi\Exceptions;

use Exception as PHPException;
use Neomerx\JsonApi;

/**
 * Process single error
 *
 * @package        FastyBird:JsonApi!
 * @subpackage     Exceptions
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class JsonApiErrorException extends PHPException implements IException, IJsonApiException
{

	/** @var string|null */
	private $type = null;

	/** @var string|null */
	private $detail = null;

	/** @var mixed[]|null */
	private $source = null;

	/**
	 * @param int $code
	 * @param string $title
	 * @param string|null $detail
	 * @param mixed[]|null $source
	 * @param string|null $type
	 */
	public function __construct(
		int $code,
		string $title,
		?string $detail = null,
		?array $source = null,
		?string $type = null
	) {
		parent::__construct($title, $code);

		$this->detail = $detail;
		$this->source = $source;
		$this->type = $type;
	}

	/**
	 * @return JsonApi\Schema\Error
	 */
	public function getError(): JsonApi\Schema\Error
	{
		return new JsonApi\Schema\Error(
			$this->getType(),
			null,
			null,
			(string) $this->code,
			(string) $this->code,
			$this->message,
			$this->getDetail(),
			$this->getSource()
		);
	}

	/**
	 * @return string|null
	 */
	public function getType(): ?string
	{
		return $this->type;
	}

	/**
	 * @return string|null
	 */
	public function getDetail(): ?string
	{
		return $this->detail;
	}

	/**
	 * @return mixed[]|null
	 */
	public function getSource(): ?array
	{
		return $this->source;
	}

}
