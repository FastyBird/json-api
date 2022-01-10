<?php declare(strict_types = 1);

/**
 * JsonApiMiddleware.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:JsonApi!
 * @subpackage     Middleware
 * @since          0.1.0
 *
 * @date           17.04.19
 */

namespace FastyBird\JsonApi\Middleware;

use FastyBird\JsonApi\Exceptions;
use FastyBird\JsonApi\JsonApi;
use Fig\Http\Message\StatusCodeInterface;
use Neomerx;
use Neomerx\JsonApi\Contracts;
use Neomerx\JsonApi\Schema;
use Nette\DI;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log;
use Sunrise\Http\Message;
use Throwable;

/**
 * {JSON:API} formatting output handling middleware
 *
 * @package        FastyBird:JsonApi!
 * @subpackage     Middleware
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class JsonApiMiddleware implements MiddlewareInterface
{

	/** @var Log\LoggerInterface */
	private Log\LoggerInterface $logger;

	/** @var DI\Container */
	private DI\Container $container;

	/**
	 * @param DI\Container $container
	 * @param Log\LoggerInterface|null $logger
	 */
	public function __construct(
		DI\Container $container,
		?Log\LoggerInterface $logger = null
	) {
		$this->logger = $logger ?? new Log\NullLogger();
		$this->container = $container;
	}

	/**
	 * @param ServerRequestInterface $request
	 * @param RequestHandlerInterface $handler
	 *
	 * @return ResponseInterface
	 */
	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
	{
		try {
			return $handler->handle($request);

		} catch (Throwable $ex) {
			$response = (new Message\ResponseFactory())->createResponse(StatusCodeInterface::STATUS_BAD_REQUEST);

			if ($ex instanceof Exceptions\IJsonApiException) {
				$response = $response->withStatus($ex->getCode());

				if ($ex instanceof Exceptions\JsonApiErrorException) {
					$content = $this->getEncoder()
						->encodeError($ex->getError());

					$response->getBody()
						->write($content);
				} elseif ($ex instanceof Exceptions\JsonApiMultipleErrorException) {
					$content = $this->getEncoder()
						->encodeErrors($ex->getErrors());

					$response->getBody()
						->write($content);
				}
			} else {
				$this->logger->error('[FB::JSON_API] An error occurred during request handling', [
					'exception' => [
						'message' => $ex->getMessage(),
						'code'    => $ex->getCode(),
					],
				]);

				$response = $response->withStatus(StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR);

				$content = $this->getEncoder()
					->encodeError(new Schema\Error(
						null,
						null,
						null,
						(string) StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
						(string) $ex->getCode(),
						'Server error',
						'There was an server error, please try again later'
					));

				$response->getBody()
					->write($content);
			}
		}

		// Setup content type
		return $response
			// Content headers
			->withHeader('Content-Type', Contracts\Http\Headers\MediaTypeInterface::JSON_API_MEDIA_TYPE);
	}

	/**
	 * @return JsonApi\JsonApiEncoder
	 */
	private function getEncoder(): JsonApi\JsonApiEncoder
	{
		$encoder = new JsonApi\JsonApiEncoder(
			new Neomerx\JsonApi\Factories\Factory(),
			$this->container->getByType(Contracts\Schema\SchemaContainerInterface::class)
		);

		$encoder->withEncodeOptions(JSON_PRETTY_PRINT);

		$encoder->withJsonApiVersion(Contracts\Encoder\EncoderInterface::JSON_API_VERSION);

		return $encoder;
	}

}
