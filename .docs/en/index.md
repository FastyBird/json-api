# Quick start

The purpose of this extension is to provide layer for parsing and handling [{JSON:API}](https://jsonapi.org/) requests
and responses

## Installation

The best way to install **fastybird/json-api** is using [Composer](http://getcomposer.org/):

```sh
composer require fastybird/json-api
```

After that, you have to register extension in *config.neon*.

```neon
extensions:
    fbJsonApi: FastyBird\JsonApi\DI\JsonApiExtension
```

## Configuration

This extension has some configuration options:

```neon
fbJsonApi:
    meta:
        author: FastyBird team
        copyright: YourCompany ltd.
```

Where:

- `meta -> author` is author or authors meta attribute. It could be single string for one author, or an array of strings
  for many authors
- `meta -> copyright` is copyright meta attribute

## Entity hydrator

Entity hydrator is useful service which purpose is to transform incoming [{JSON:API}](https://jsonapi.org/) request into
[Doctrine2](https://www.doctrine-project.org) entity. All what you have to do is to define which attributes & relations
could be hydrated.

```php
namespace Your\CoolApplication\Data;

use IPub\JsonAPIDocument;
use FastyBird\JsonApi\Exceptions;
use FastyBird\JsonApi\Hydrators;

use Your\CoolApplication\Models\GroupRepository;
use Your\CoolApplication\Exceptions\NotFoundException;

class ArticleHydrator extends Hydrators\Hydrator
{

    /** @var string[] */
    protected array $attributes = [
        'name',
        'content',
        'comment',
        'case_transform' => 'caseTransform',
    ];

    /** @var string[] */
    protected array $relationships = [
        'author',
        'group',
    ];

    /** @var string */
    protected string $translationDomain = 'your.app.translation.domain'; // This is not required

    /** @var GroupRepository */
    private GroupRepository $groupRepository;

    protected function getEntityName(): string
    {
        return Your\CoolApplication\Entities\ArticleEntity::class;
    }

    protected function hydrateCommentAttribute(
        JsonAPIDocument\Objects\IStandardObject $attributes
    ): ?string {
        if (
            $attributes->get('comment') === null
            || (string) $attributes->get('comment') === ''
        ) {
            return null;
        }
  
        return (string) $attributes->get('comment');
    }

    protected function hydrateGroupsRelationship(
        JsonAPIDocument\Objects\IRelationshipObject $relationship,
        ?JsonAPIDocument\Objects\IResourceObjectCollection $included
    ): ?array {
        if (!$relationship->isHasMany()) {
            return null;
        }
    
        $groups = [];
    
        foreach ($relationship->getIdentifiers() as $identifier) {
            try {
                $groups[] = $this->groupRepository->findById($identifier->getId());
    
            } catch (NotFoundException $ex) {
                throw new Exceptions\JsonApiErrorException(
                    422,
                    'Defined group relation was not found',
                    'Defined group relation was not foun in our application',
                    [
                        'pointer' => '/data/relationships/groups/data/id',
                    ]
                );
            }
        }
    
        if ($groups === []) {
            throw new Exceptions\JsonApiErrorException(
                422,
                'Groups relation is missing',
                'Provide at least one group relation to create or update article',
                [
                    'pointer' => '/data/relationships/groups/data/id',
                ]
            );
        }
    
        return $groups;
    }
} 
```

So now let's go through it step by step:

#### Entity attributes

Attributes are entity fields and with this variable you configure which one will be hydrated.

```php
    /** @var string[] */
    protected array $attributes = [
        'name',
        'content',
        'comment',
        'case_transform' => 'caseTransform',
    ];
```

#### Entity relations

If you entity has some relations like: Article - Author as One-To-One relation or Article - Group as One-To-Many
relation, with this variable you could configure which relation will be hydrated

```php
    /** @var string[] */
    protected array $relationships = [
        'author',
        'group',
    ];
```

#### Entity class

Hydrator need to know what entity class you want to hydrate. Hydrator try to read all class variables - attributes &
relations.

```php
    protected function getEntityName(): string
    {
        return Your\CoolApplication\Entities\ArticleEntity::class;
    }
```

#### Custom attribute parser

There could be cases where you want to handle parsing attribute by your own. For this purpose hydrator is calling your
custom methods. All what you have to do is follow naming convention: hydrate**YourAttributeName**Attribute.

Return value is up to you, it could be string, number or even entity.

```php
    protected function hydrateCommentAttribute(
        JsonAPIDocument\Objects\IStandardObject $attributes
    ): ?string {
        if (
            $attributes->get('comment') === null
            || (string) $attributes->get('comment') === ''
        ) {
            return null;
        }
  
        return (string) $attributes->get('comment');
    }
```

#### Custom relation parser

The logic is same as for *custom attribute parser*.

Naming convention: hydrate**YourRelationName**Relationship.

Return value is again, up to you, usually it is single entity or list of entities for One-To-Many relation.

```php
    protected function hydrateGroupsRelationship(
        JsonAPIDocument\Objects\IRelationshipObject $relationship,
        ?JsonAPIDocument\Objects\IResourceObjectCollection $included
    ): ?array {
        // Your code here...

        return [];
    }
```

### How to hydrate request data?

Now you have created your custom hydrator service. All what you have to do is to
create [{JSON:API}](https://jsonapi.org/) document and pass it to the hydrator.

```php
namespace Your\CoolApplication\Presenters;

use IPub\JsonAPIDocument;

user Your\CoolApplication\Entities;
user Your\CoolApplication\Data;

class ArticlesPresenter
{
    /** @var Data\ArticleHydrator */
    private Data\ArticleHydrator $articleHydrator;

    /** @var Data\ArticlesManager */
    private Data\ArticlesManager $articlesManager;

    public function createAction(IRequest $request): IResponse
    {
        $data = $request->getData();
        
        $document = JsonAPIDocument\Document::create($data);
        
        $createArticleData = $this->articleHydrator->hydrate($document);
        
        $article = $this->articlesManager->create($createArticleData);
        
        return new Response(201, 'Created'); 
    }

    public function updateAction(IRequest $request): IResponse
    {
        $data = $request->getData();
        
        $document = JsonAPIDocument\Document::create($data);

        $resource = $document->getResource();
        
        if ($resource === null) {
            return new Response(404, 'Ivalid document');
        }        

        $id = $resource->getId();

        $article = $this->articlesManager->getById($id);

        $updateArticleData = $this->articleHydrator->hydrate($document, $article);
        
        $article = $this->articlesManager->update($createArticleData);
        
        return new Response(201, 'Updated'); 
    }
}
```

Return value of the *hydrate* method of hydrator is **Nette\Utils\ArrayHash** instance and look like this:

```php
Nette\Utils\ArrayHash::from([
    'id'        => 1, 
    'title'     => 'Article title',
    'content'   => 'This article is supercool',
    'comment'   => null,
    'author'    => instance of AuthorEntity,
]);
```

Value of relation attribute could be instance of some entity, array of entity instancies or an plain key-value array
extracted from **included**

All entity hydrators should be registered as services:

```neon
services:
    - {type: Your\CoolApplication\Data\ArticleHydrator}
```

## Entity schema

Entity schema is useful service which purpose is to transform [Doctrine2](https://www.doctrine-project.org) entity
into [{JSON:API}](https://jsonapi.org/) document.

```php
namespace Your\CoolApplication\Data;

use FastyBird\JsonApi\Schemas;
use IPub\SlimRouter\Routing;
use Neomerx\JsonApi;

use Your\CoolApplication\Entities;

class ArticleSchema extends Schemas\JsonApiSchema
{
    /** @var Routing\IRouter */
    private Routing\IRouter $router;

    public function __construct(
         Routing\IRouter $router
    ) {
        $this->router = $router;
    }

    public function getEntityClass(): string
    {
        return Entities\ArticleEntity::class;
    }

    public function getType(): string
    {
        return 'article';
    }

    public function getAttributes($article, JsonApi\Contracts\Schema\ContextInterface $context): iterable
    {
        return [
            'title' => $article->getTitle(),
            'content' => $article->getContent(),
        ];
    }

    public function getSelfLink($article): JsonApi\Contracts\Schema\LinkInterface
    {
        return new JsonApi\Schema\Link(
            false,
            $this->router->urlFor(
                'article.detail.link',
                [
                    'id' => $article->getId(),
                ]
            ),
            false
        );
    }

    public function getRelationships($article, JsonApi\Contracts\Schema\ContextInterface $context): iterable
    {
        return [
            'author'        => [
                self::RELATIONSHIP_DATA          => $article->getAuthor(),
                self::RELATIONSHIP_LINKS_SELF    => false,
                self::RELATIONSHIP_LINKS_RELATED => true,
            ],
            'groups'    => [
                self::RELATIONSHIP_DATA          => $article->getGroups(),
                self::RELATIONSHIP_LINKS_SELF    => true,
                self::RELATIONSHIP_LINKS_RELATED => true,
            ],
        ];
    }

    public function getRelationshipRelatedLink($article, string $name): JsonApi\Contracts\Schema\LinkInterface
    {
        if ($name === 'groups') {
            return new JsonApi\Schema\Link(
                false,
                $this->router->urlFor(
                    'groups.all.link',
                ),
                true,
                [
                    'count' => count($article->getGroups()),
                ]
            );
  
        } elseif ($name === 'author') {
            return new JsonApi\Schema\Link(
                false,
                $this->router->urlFor(
                    'author.detail.link',
                    [
                        'id' => $article-getAuthor()->getId(),
                    ]
                ),
                false
            );
        }
  
        return parent::getRelationshipRelatedLink($article, $name);
    }

    public function getRelationshipSelfLink($article, string $name): JsonApi\Contracts\Schema\LinkInterface
    {
        if (
            $name === 'author'
            || $name === 'groups'
        ) {
            return new JsonApi\Schema\Link(
                false,
                $this->router->urlFor(
                    'article.relationship.link',
                    [
                        'id' => $article->getId(),
                        'relationship' => $name,
                    ]
                ),
                false
            );
        }
  
        return parent::getRelationshipSelfLink($article, $name);
    }

}
```

All methods are self-described and required are only:

- `getEntityClass` - have to return entity class name
- `getType` - have to return entity type according to [{JSON:API}](https://jsonapi.org/) specification


- `getAttributes` - is here to return all entity fields as data attributes

All entity schemas have to be registered as services:

```neon
services:
    - {type: Your\CoolApplication\Data\ArticleSchema}
```

And this extension wil find it automatically and register them to response encoder. No need to do more.

# Tip

For better results for hydrator you could use [ipub/doctrine-crud](https://github.com/ipublikuj/doctrine-crud) package.
This packages has special annotation for entity atributes and with this annotation you could define which field is
required for new entity or which field is writable:

```php
namespace Your\CoolApplication\Data;

use IPub\DoctrineCrud\Mapping\Annotation as IPubDoctrine;

class ArticleEntity
{

    /**
     * @var string
     *
     * @IPubDoctrine\Crud(is="required")
     * @ORM\Column(type="string", nullable=false)
     */
    protected string $title;

    /**
     * @var string
     *
     * @IPubDoctrine\Crud(is={"required", "writable"})
     * @ORM\Column(type="string", nullable=false)
     */
    protected string $content;

}
```

As you can see, **title** attribute is required and have to be present during hydration, but will be skipped when in
hydration call is used existing entity. And **content** attribute is also writable, so is hydrated when new entity is
created and also when entity is updated.

With this package you could use one hydrator service for both states - creating entity and updating entity.

***
Homepage [https://www.fastybird.com](https://www.fastybird.com) and
repository [https://github.com/FastyBird/json-api](https://github.com/FastyBird/json-api).
