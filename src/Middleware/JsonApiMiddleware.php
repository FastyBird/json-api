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
use IPub;
use Neomerx;
use Neomerx\JsonApi\Contracts;
use Neomerx\JsonApi\Schema;
use Nette\DI;
use Psr\Http\Message;
use Psr\Http\Server;
use Psr\Log;
use Throwable;

if (!class_exists('IPub\SlimRouter\Exceptions\HttpException')) {
	class_alias('IPub\SlimRouter\Exceptions\HttpException', Throwable::class);
}

/**
 * {JSON:API} formatting output handling middleware
 *
 * @package        FastyBird:JsonApi!
 * @subpackage     Middleware
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class JsonApiMiddleware implements Server\MiddlewareInterface
{

	/** @var Message\ResponseFactoryInterface */
	private Message\ResponseFactoryInterface $responseFactory;

	/** @var Log\LoggerInterface */
	private Log\LoggerInterface $logger;

	/** @var DI\Container */
	private DI\Container $container;

	/**
	 * @param Message\ResponseFactoryInterface $responseFactory
	 * @param DI\Container $container
	 * @param Log\LoggerInterface|null $logger
	 */
	public function __construct(
		Message\ResponseFactoryInterface $responseFactory,
		DI\Container $container,
		?Log\LoggerInterface $logger = null
	) {
		$this->responseFactory = $responseFactory;
		$this->logger = $logger ?? new Log\NullLogger();
		$this->container = $container;
	}

	/**
	 * @param Message\ServerRequestInterface $request
	 * @param Server\RequestHandlerInterface $handler
	 *
	 * @return Message\ResponseInterface
	 */
	public function process(Message\ServerRequestInterface $request, Server\RequestHandlerInterface $handler): Message\ResponseInterface
	{
		try {
			return $handler->handle($request);

		} catch (Throwable $ex) {
			$response = $this->responseFactory->createResponse(StatusCodeInterface::STATUS_BAD_REQUEST);

			if ($ex instanceof Exceptions\IJsonApiException) {
				$response = $response->withStatus($ex->getCode());

				if ($ex instanceof Exceptions\JsonApiErrorException) {
					$content = $this->getEncoder()
						->encodeError($ex->getError());

					$response->getBody()->write($content);
				} elseif ($ex instanceof Exceptions\JsonApiMultipleErrorException) {
					$content = $this->getEncoder()
						->encodeErrors($ex->getErrors());

					$response->getBody()->write($content);
				}
			} elseif ($ex instanceof IPub\SlimRouter\Exceptions\HttpException) {
				$response = $response->withStatus($ex->getCode());

				$content = $this->getEncoder()
					->encodeError(new Schema\Error(
						null,
						null,
						null,
						(string) $ex->getCode(),
						(string) $ex->getCode(),
						$ex->getTitle(),
						$ex->getDescription()
					));

				$response->getBody()
					->write($content);
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

				$response->getBody()->write($content);
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
