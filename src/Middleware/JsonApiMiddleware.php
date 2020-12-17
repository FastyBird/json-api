<?php declare(strict_types = 1);

/**
 * JsonApiMiddleware.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:JsonApi!
 * @subpackage     Controllers
 * @since          0.1.0
 *
 * @date           17.04.19
 */

namespace FastyBird\JsonApi\Middleware;

use FastRoute;
use FastRoute\RouteCollector as FastRouteCollector;
use FastRoute\RouteParser\Std;
use FastyBird\JsonApi\Exceptions;
use FastyBird\JsonApi\JsonApi;
use FastyBird\WebServer\Http as WebServerHttp;
use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use IPub\SlimRouter;
use IPub\SlimRouter\Routing\FastRouteDispatcher;
use IPub\SlimRouter\Routing\IRoute;
use Neomerx;
use Neomerx\JsonApi\Contracts;
use Neomerx\JsonApi\Schema;
use Nette\DI;
use Nette\Utils;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log;
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

	private const LINK_SELF = Contracts\Schema\DocumentInterface::KEYWORD_SELF;
	private const LINK_RELATED = Contracts\Schema\DocumentInterface::KEYWORD_RELATED;
	private const LINK_FIRST = Contracts\Schema\DocumentInterface::KEYWORD_FIRST;
	private const LINK_LAST = Contracts\Schema\DocumentInterface::KEYWORD_LAST;
	private const LINK_NEXT = Contracts\Schema\DocumentInterface::KEYWORD_NEXT;
	private const LINK_PREV = Contracts\Schema\DocumentInterface::KEYWORD_PREV;

	/** @var string|string[] */
	private $metaAuthor;

	/** @var string */
	private string $metaCopyright;

	/** @var WebServerHttp\ResponseFactory */
	private WebServerHttp\ResponseFactory $responseFactory;

	/** @var Log\LoggerInterface */
	private Log\LoggerInterface $logger;

	/** @var DI\Container */
	private DI\Container $container;

	/** @var FastRouteDispatcher|null */
	private ?FastRouteDispatcher $routerDispatcher = null;

	/**
	 * @param WebServerHttp\ResponseFactory $responseFactory
	 * @param DI\Container $container
	 * @param string|string[] $metaAuthor
	 * @param string $metaCopyright
	 * @param Log\LoggerInterface|null $logger
	 */
	public function __construct(
		WebServerHttp\ResponseFactory $responseFactory,
		DI\Container $container,
		$metaAuthor,
		string $metaCopyright,
		?Log\LoggerInterface $logger = null
	) {
		$this->responseFactory = $responseFactory;
		$this->logger = $logger ?? new Log\NullLogger();
		$this->container = $container;

		$this->metaAuthor = $metaAuthor;
		$this->metaCopyright = $metaCopyright;
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
			$response = $handler->handle($request);

			if ($response instanceof WebServerHttp\Response) {
				$entity = $response->getEntity();

				if ($entity instanceof WebServerHttp\ScalarEntity) {
					$encoder = $this->getEncoder();

					$links = [
						self::LINK_SELF => new Schema\Link(false, $this->uriToString($request->getUri()), false),
					];

					$meta = $this->getBaseMeta();

					if ($response->hasAttribute(WebServerHttp\ResponseAttributes::ATTR_TOTAL_COUNT)) {
						$meta = array_merge($meta, [
							'totalCount' => $response->getAttribute(WebServerHttp\ResponseAttributes::ATTR_TOTAL_COUNT),
						]);

						if (array_key_exists('page', $request->getQueryParams())) {
							$queryParams = $request->getQueryParams();

							$pageOffset = isset($queryParams['page']['offset']) ? (int) $queryParams['page']['offset'] : null;
							$pageLimit = isset($queryParams['page']['limit']) ? (int) $queryParams['page']['limit'] : null;

						} else {
							$pageOffset = null;
							$pageLimit = null;
						}

						if ($pageOffset !== null && $pageLimit !== null) {
							$lastPage = (int) round($response->getAttribute(WebServerHttp\ResponseAttributes::ATTR_TOTAL_COUNT) / $pageLimit) * $pageLimit;

							if ($lastPage === $response->getAttribute(WebServerHttp\ResponseAttributes::ATTR_TOTAL_COUNT)) {
								$lastPage = $response->getAttribute(WebServerHttp\ResponseAttributes::ATTR_TOTAL_COUNT) - $pageLimit;
							}

							$uri = $request->getUri();

							$uriSelf = $uri->withQuery($this->buildPageQuery($pageOffset, $pageLimit));
							$uriFirst = $uri->withQuery($this->buildPageQuery(0, $pageLimit));
							$uriLast = $uri->withQuery($this->buildPageQuery($lastPage, $pageLimit));
							$uriPrev = $uri->withQuery($this->buildPageQuery(($pageOffset - $pageLimit), $pageLimit));
							$uriNext = $uri->withQuery($this->buildPageQuery(($pageOffset + $pageLimit), $pageLimit));

							$links = array_merge($links, [
								self::LINK_SELF  => new Schema\Link(false, $this->uriToString($uriSelf), false),
								self::LINK_FIRST => new Schema\Link(false, $this->uriToString($uriFirst), false),
							]);

							if (($pageOffset - 1) >= 0) {
								$links = array_merge($links, [
									self::LINK_PREV => new Schema\Link(false, $this->uriToString($uriPrev), false),
								]);
							}

							if ((($response->getAttribute(WebServerHttp\ResponseAttributes::ATTR_TOTAL_COUNT) - $pageLimit) - ($pageOffset + $pageLimit)) >= 0) {
								$links = array_merge($links, [
									self::LINK_NEXT => new Schema\Link(false, $this->uriToString($uriNext), false),
								]);
							}

							$links = array_merge($links, [
								self::LINK_LAST => new Schema\Link(false, $this->uriToString($uriLast), false),
							]);
						}
					}

					$encoder->withMeta($meta);

					$encoder->withLinks($links);

					if (Utils\Strings::contains($request->getUri()
						->getPath(), '/relationships/')) {
						$encodedData = $encoder->encodeDataAsArray($entity->getData());

						// Try to get "self" link from encoded entity as array
						if (
							isset($encodedData['data'])
							&& isset($encodedData['data']['links'])
							&& isset($encodedData['data']['links'][self::LINK_SELF])
						) {
							$encoder->withLinks(array_merge($links, [
								self::LINK_RELATED => new Schema\Link(false, $encodedData['data']['links'][self::LINK_SELF], false),
							]));

						} else {
							$uriRelated = $request->getUri();

							$linkRelated = str_replace('/relationships/', '/', $this->uriToString($uriRelated));

							$results = $this->getRouterDispatcher()
								->dispatch(
									RequestMethodInterface::METHOD_GET,
									$linkRelated
								);

							if ($results[0] === SlimRouter\Routing\RoutingResults::FOUND) {
								$encoder->withLinks(array_merge($links, [
									self::LINK_RELATED => new Schema\Link(false, $linkRelated, false),
								]));
							}
						}

						$content = $encoder->encodeIdentifiers($entity->getData());

					} else {
						if (array_key_exists('include', $request->getQueryParams())) {
							$encoder->withIncludedPaths(explode(',', $request->getQueryParams()['include']));
						}

						$content = $encoder->encodeData($entity->getData());
					}

					$response->getBody()
						->write($content);
				}
			}

		} catch (Throwable $ex) {
			$response = $this->responseFactory->createResponse();

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

			} elseif ($ex instanceof SlimRouter\Exceptions\HttpException) {
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

				$response->getBody()
					->write($content);
			}
		}

		$allowedMethods = [
			RequestMethodInterface::METHOD_GET,
			RequestMethodInterface::METHOD_POST,
			RequestMethodInterface::METHOD_PATCH,
			RequestMethodInterface::METHOD_DELETE,
			RequestMethodInterface::METHOD_OPTIONS,
		];

		// Setup content type
		return $response
			// CORS headers
			->withHeader('Access-Control-Allow-Origin', '*')
			->withHeader('Access-Control-Allow-Methods', implode(',', $allowedMethods))
			->withHeader('Access-Control-Allow-Credentials', 'true')
			->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-Api-Key')
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

	/**
	 * @param UriInterface $uri
	 *
	 * @return string
	 */
	private function uriToString(UriInterface $uri): string
	{
		$result = '';

		// Add a leading slash if necessary.
		if (substr($uri->getPath(), 0, 1) !== '/') {
			$result .= '/';
		}

		$result .= $uri->getPath();

		if ($uri->getQuery() !== '') {
			$result .= '?' . $uri->getQuery();
		}

		if ($uri->getFragment() !== '') {
			$result .= '#' . $uri->getFragment();
		}

		return $result;
	}

	/**
	 * @return mixed[]
	 */
	private function getBaseMeta(): array
	{
		$meta = [];

		if ($this->metaAuthor !== null) {
			if (is_array($this->metaAuthor)) {
				$meta['authors'] = $this->metaAuthor;

			} else {
				$meta['author'] = $this->metaAuthor;
			}
		}

		$meta['copyright'] = $this->metaCopyright;

		return $meta;
	}

	/**
	 * @param int $offset
	 * @param int|string $limit
	 *
	 * @return string
	 */
	private function buildPageQuery(int $offset, $limit): string
	{
		$query = [
			'page' => [
				'offset' => $offset,
				'limit'  => $limit,
			],
		];

		return http_build_query($query);
	}

	/**
	 * @return FastRouteDispatcher
	 */
	private function getRouterDispatcher(): FastRouteDispatcher
	{
		if ($this->routerDispatcher !== null) {
			return $this->routerDispatcher;
		}

		$router = $this->container->getByType(SlimRouter\Routing\IRouter::class);

		$routeDefinitionCallback = function (FastRouteCollector $r) use ($router): void {
			$basePath = $router->getBasePath();

			/** @var IRoute $route */
			foreach ($router->getIterator() as $route) {
				$r->addRoute($route->getMethods(), $basePath . $route->getPattern(), $route->getIdentifier());
			}
		};

		/** @var FastRouteDispatcher $dispatcher */
		$dispatcher = FastRoute\simpleDispatcher($routeDefinitionCallback, [
			'dispatcher'  => FastRouteDispatcher::class,
			'routeParser' => new Std(),
		]);

		$this->routerDispatcher = $dispatcher;

		return $this->routerDispatcher;
	}

}
