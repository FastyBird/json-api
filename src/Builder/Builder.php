<?php declare(strict_types = 1);

/**
 * JsonApiMiddleware.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:JsonApi!
 * @subpackage     Builder
 * @since          0.1.0
 *
 * @date           17.04.19
 */

namespace FastyBird\JsonApi\Builder;

use FastyBird\JsonApi\JsonApi;
use Neomerx;
use Neomerx\JsonApi\Contracts;
use Neomerx\JsonApi\Schema;
use Nette\DI;
use Nette\Utils;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

/**
 * {JSON:API} formatting output handling middleware
 *
 * @package        FastyBird:JsonApi!
 * @subpackage     Builder
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class Builder
{

	private const LINK_SELF = Contracts\Schema\DocumentInterface::KEYWORD_SELF;
	private const LINK_RELATED = Contracts\Schema\DocumentInterface::KEYWORD_RELATED;
	private const LINK_FIRST = Contracts\Schema\DocumentInterface::KEYWORD_FIRST;
	private const LINK_LAST = Contracts\Schema\DocumentInterface::KEYWORD_LAST;
	private const LINK_NEXT = Contracts\Schema\DocumentInterface::KEYWORD_NEXT;
	private const LINK_PREV = Contracts\Schema\DocumentInterface::KEYWORD_PREV;

	/** @var string|string[] */
	private $metaAuthor;

	/** @var string|null */
	private ?string $metaCopyright;

	/** @var DI\Container */
	private DI\Container $container;

	/**
	 * @param DI\Container $container
	 * @param string|string[] $metaAuthor
	 * @param string|null $metaCopyright
	 */
	public function __construct(
		DI\Container $container,
		$metaAuthor,
		?string $metaCopyright = null
	) {
		$this->container = $container;

		$this->metaAuthor = $metaAuthor;
		$this->metaCopyright = $metaCopyright;
	}

	/**
	 * @param ServerRequestInterface $request
	 * @param ResponseInterface $response
	 * @param object|Array<object>|null $entity
	 * @param int|null $totalCount
	 *
	 * @return ResponseInterface
	 */
	public function build(
		ServerRequestInterface $request,
		ResponseInterface $response,
		$entity,
		?int $totalCount = null,
		?callable $linkValidator = null
	): ResponseInterface {
		$encoder = $this->getEncoder();

		$links = [
			self::LINK_SELF => new Schema\Link(false, $this->uriToString($request->getUri()), false),
		];

		$meta = $this->getBaseMeta();

		if ($totalCount !== null) {
			$meta = array_merge($meta, [
				'totalCount' => $totalCount,
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
				$lastPage = (int) round($totalCount / $pageLimit) * $pageLimit;

				if ($lastPage === $totalCount) {
					$lastPage = $totalCount - $pageLimit;
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

				if ((($totalCount - $pageLimit) - ($pageOffset + $pageLimit)) >= 0) {
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

		if (Utils\Strings::contains($request->getUri()->getPath(), '/relationships/')) {
			$encodedData = $encoder->encodeDataAsArray($entity);

			// Try to get "self" link from encoded entity as array
			if (
				isset($encodedData['data'])
				&& isset($encodedData['data']['links']) // @phpstan-ignore-line
				&& isset($encodedData['data']['links'][self::LINK_SELF]) // @phpstan-ignore-line
			) {
				$encoder->withLinks(array_merge($links, [
					// @phpstan-ignore-next-line
					self::LINK_RELATED => new Schema\Link(false, strval($encodedData['data']['links'][self::LINK_SELF]), false),
				]));

			} else {
				if ($linkValidator !== null) {
					$uriRelated = $request->getUri();

					$linkRelated = str_replace('/relationships/', '/', $this->uriToString($uriRelated));

					$isValid = call_user_func_array($linkValidator, [$linkRelated]);

					if ($isValid === true) {
						$encoder->withLinks(array_merge($links, [
							self::LINK_RELATED => new Schema\Link(false, $linkRelated, false),
						]));
					}
				}
			}

			$content = $encoder->encodeIdentifiers($entity);

		} else {
			if (array_key_exists('include', $request->getQueryParams())) {
				$encoder->withIncludedPaths(explode(',', $request->getQueryParams()['include']));
			}

			$content = $encoder->encodeData($entity);
		}

		$response->getBody()->write($content);

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

		if ($this->metaCopyright !== null) {
			$meta['copyright'] = $this->metaCopyright;
		}

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

}
