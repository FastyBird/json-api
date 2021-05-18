<?php declare(strict_types = 1);

/**
 * Hydrator.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:JsonApi!
 * @subpackage     Hydrators
 * @since          0.1.0
 *
 * @date           26.05.20
 */

namespace FastyBird\JsonApi\Hydrators;

use ArrayAccess;
use Consistence;
use Contributte\Translation;
use DateTimeInterface;
use Doctrine\Common;
use Doctrine\ORM;
use Doctrine\Persistence;
use FastyBird\JsonApi\Exceptions;
use FastyBird\JsonApi\Hydrators;
use Fig\Http\Message\StatusCodeInterface;
use IPub\DoctrineCrud;
use IPub\JsonAPIDocument;
use Nette;
use Nette\Utils;
use phpDocumentor;
use Ramsey\Uuid;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionProperty;
use Throwable;

/**
 * Entity hydrator
 *
 * @package        FastyBird:JsonApi!
 * @subpackage     Hydrators
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
abstract class Hydrator
{

	use Nette\SmartObject;

	protected const IDENTIFIER_KEY = 'id';

	/**
	 * Whether the resource has a client generated id
	 *
	 * @var string|null
	 */
	protected ?string $entityIdentifier = null;

	/**
	 * The resource attribute keys to hydrate
	 *
	 * For example:
	 *
	 * ```
	 * $attributes = [
	 *  'foo',
	 *  'bar' => 'baz'
	 * ];
	 * ```
	 *
	 * Will transfer the `foo` resource attribute to the model `foo` attribute, and the
	 * resource `bar` attribute to the model `baz` attribute.
	 *
	 * @var mixed[]
	 */
	protected array $attributes = [];

	/**
	 * The resource composited attribute keys to hydrate
	 *
	 * For example:
	 *
	 * ```
	 * $attributes = [
	 *  'params',
	 *  'bar' => 'baz'
	 * ];
	 * ```
	 *
	 * Will transfer the `foo` resource attribute to the model `foo` attribute, and the
	 * resource `bar` attribute to the model `baz` attribute.
	 *
	 * @var mixed[]
	 */
	protected array $compositedAttributes = [];

	/**
	 * Resource relationship keys that should be automatically hydrated
	 *
	 * @var string[]
	 */
	protected array $relationships = [];

	/** @var Translation\PrefixedTranslator */
	protected Translation\PrefixedTranslator $translator;

	/** @var string */
	protected string $translationDomain = '';

	/** @var mixed[]|null */
	private ?array $normalizedAttributes = null;

	/** @var mixed[]|null */
	private ?array $normalizedCompositedAttributes = null;

	/** @var mixed[]|null */
	private ?array $normalizedRelationships = null;

	/** @var Persistence\ManagerRegistry */
	private Persistence\ManagerRegistry $managerRegistry;

	/** @var Common\Annotations\Reader */
	private Common\Annotations\Reader $annotationReader;

	/** @var Exceptions\JsonApiMultipleErrorException */
	private Exceptions\JsonApiMultipleErrorException $errors;

	/**
	 * @param Persistence\ManagerRegistry $managerRegistry
	 * @param Translation\Translator $translator
	 *
	 * @throws Common\Annotations\AnnotationException
	 */
	public function __construct(
		Persistence\ManagerRegistry $managerRegistry,
		Translation\Translator $translator
	) {
		$this->managerRegistry = $managerRegistry;

		$this->annotationReader = new Common\Annotations\AnnotationReader();

		$this->errors = new Exceptions\JsonApiMultipleErrorException();

		$this->translator = new Translation\PrefixedTranslator($translator, $this->translationDomain);
	}

	/**
	 * @param JsonAPIDocument\IDocument<JsonAPIDocument\Objects\StandardObject> $document
	 * @param DoctrineCrud\Entities\IEntity|null $entity
	 *
	 * @return Utils\ArrayHash
	 *
	 * @throws Exceptions\IJsonApiException
	 * @throws Throwable
	 */
	public function hydrate(
		JsonAPIDocument\IDocument $document,
		?DoctrineCrud\Entities\IEntity $entity = null
	): Utils\ArrayHash {
		$entityMapping = $this->mapEntity($this->getEntityName());

		$attributes = $this->hydrateAttributes(
			$this->getEntityName(),
			$document->getResource()
				->getAttributes(),
			$entityMapping,
			$entity,
			null
		);

		$relationships = $this->hydrateRelationships(
			$document->getResource()
				->getRelationships(),
			$entityMapping,
			$document->getIncluded(),
			$entity
		);

		if ($this->errors->hasErrors()) {
			throw $this->errors;
		}

		$result = Utils\ArrayHash::from(array_merge(
			[
				'entity' => $this->getEntityName(),
			],
			$attributes,
			$relationships
		));

		if ($entity === null) {
			$identifierKey = $this->entityIdentifier ?? self::IDENTIFIER_KEY;

			try {
				$identifier = $document->getResource()
					->getIdentifier()
					->getId();

				if (!Uuid\Uuid::isValid($identifier)) {
					throw new Exceptions\JsonApiErrorException(
						StatusCodeInterface::STATUS_UNPROCESSABLE_ENTITY,
						$this->translator->translate('//jsonApi.hydrator.identifierInvalid.heading'),
						$this->translator->translate('//jsonApi.hydrator.identifierInvalid.message'),
						[
							'pointer' => 'data/id',
						]
					);
				}

				$result[$identifierKey] = Uuid\Uuid::fromString($identifier);

			} catch (JsonAPIDocument\Exceptions\RuntimeException $ex) {
				$result[$identifierKey] = Uuid\Uuid::uuid4();
			}
		}

		return $result;
	}

	/**
	 * @param string $entityClassName
	 *
	 * @return Hydrators\Fields\IField[]
	 *
	 * @phpstan-param class-string $entityClassName
	 */
	protected function mapEntity(string $entityClassName): array
	{
		$entityManager = $this->managerRegistry->getManagerForClass($entityClassName);

		if ($entityManager === null) {
			return [];
		}

		$classMetadata = $entityManager->getClassMetadata($entityClassName);

		$reflectionProperties = [];

		try {
			if (class_exists($entityClassName)) {
				$rc = new ReflectionClass($entityClassName);

			} else {
				throw new Exceptions\InvalidStateException('Entity could not be parsed');
			}

		} catch (ReflectionException $ex) {
			throw new Exceptions\InvalidStateException('Entity could not be parsed');
		}

		foreach ($rc->getProperties() as $rp) {
			$reflectionProperties[] = $rp->getName();
		}

		$entityFields = array_unique(array_merge(
			$reflectionProperties,
			$classMetadata->getFieldNames(),
			$classMetadata->getAssociationNames()
		));

		$fields = [];

		foreach ($entityFields as $fieldName) {
			try {
				// Check if property in entity class exists
				$rp = $rc->getProperty($fieldName);

			} catch (ReflectionException $ex) {
				continue;
			}

			$crud = $this->annotationReader->getPropertyAnnotation($rp, DoctrineCrud\Mapping\Annotation\Crud::class);

			if (!$crud instanceof DoctrineCrud\Mapping\Annotation\Crud) {
				continue;
			}

			// Check if field is updatable
			if (!$crud->isRequired() && !$crud->isWritable()) {
				continue;
			}

			$isRelationship = false;

			if ($this->getRelationshipKey($fieldName) !== null) {
				// Transform entity field name to schema relationship name
				$mappedKey = $this->getRelationshipKey($fieldName);

				$isRelationship = true;

			} elseif ($this->getAttributeKey($fieldName) !== null) {
				$mappedKey = $this->getAttributeKey($fieldName);

			} elseif ($this->getCompositedAttributeKey($fieldName) !== null) {
				$mappedKey = $this->getCompositedAttributeKey($fieldName);

			} else {
				continue;
			}

			// Extract all entity property annotations
			$propertyAnnotations = array_map((function ($annotation): string {
				return get_class($annotation);
			}), $this->annotationReader->getPropertyAnnotations($rp));

			if (in_array(ORM\Mapping\OneToOne::class, $propertyAnnotations, true)) {
				/** @var ORM\Mapping\OneToOne $mapping */
				$mapping = $this->annotationReader->getPropertyAnnotation($rp, ORM\Mapping\OneToOne::class);
				$className = $mapping->targetEntity;

				// Check if class is callable
				if (class_exists($className)) {
					$fields[] = new Hydrators\Fields\SingleEntityField($className, false, $mappedKey, $isRelationship, $fieldName, $crud->isRequired(), $crud->isWritable());
				}

			} elseif (in_array(ORM\Mapping\OneToMany::class, $propertyAnnotations, true)) {
				/** @var ORM\Mapping\OneToMany $mapping */
				$mapping = $this->annotationReader->getPropertyAnnotation($rp, ORM\Mapping\OneToMany::class);
				$className = $mapping->targetEntity;

				// Check if class is callable
				if (class_exists($className)) {
					$fields[] = new Hydrators\Fields\CollectionField($className, true, $mappedKey, $isRelationship, $fieldName, $crud->isRequired(), $crud->isWritable());
				}

			} elseif (in_array(ORM\Mapping\ManyToMany::class, $propertyAnnotations, true)) {
				/** @var ORM\Mapping\ManyToMany $mapping */
				$mapping = $this->annotationReader->getPropertyAnnotation($rp, ORM\Mapping\ManyToMany::class);
				$className = $mapping->targetEntity;

				// Check if class is callable
				if (class_exists($className)) {
					$fields[] = new Hydrators\Fields\CollectionField($className, true, $mappedKey, $isRelationship, $fieldName, $crud->isRequired(), $crud->isWritable());
				}

			} elseif (in_array(ORM\Mapping\ManyToOne::class, $propertyAnnotations, true)) {
				/** @var ORM\Mapping\ManyToOne $mapping */
				$mapping = $this->annotationReader->getPropertyAnnotation($rp, ORM\Mapping\ManyToOne::class);
				$className = $mapping->targetEntity;

				// Check if class is callable
				if (class_exists($className)) {
					$fields[] = new Hydrators\Fields\SingleEntityField($className, false, $mappedKey, $isRelationship, $fieldName, $crud->isRequired(), $crud->isWritable());
				}

			} else {
				$varAnnotation = $this->parseAnnotation($rp, 'var');

				try {
					$rm = $rc->getMethod('get' . ucfirst($fieldName));

					$returnType = $rm->getReturnType();

					if ($returnType instanceof ReflectionNamedType) {
						$varAnnotation = ($varAnnotation === null ? '' : $varAnnotation . '|') . $returnType->getName() . ($returnType->allowsNull() ? '|null' : '');
					}

				} catch (ReflectionException $ex) {
					// Nothing to do
				}

				if ($varAnnotation === null) {
					continue;
				}

				$className = null;

				$isString = false;
				$isNumber = false;
				$isDecimal = false;
				$isArray = false;
				$isBool = false;
				$isClass = false;

				$isNullable = false;

				$typesFound = 0;

				if (strpos($varAnnotation, '|') !== false) {
					$varDatatypes = explode('|', $varAnnotation);
					$varDatatypes = array_unique($varDatatypes);

				} else {
					$varDatatypes = [$varAnnotation];
				}

				$className = null;

				foreach ($varDatatypes as $varDatatype) {
					if (class_exists($varDatatype) || interface_exists($varDatatype)) {
						$className = $varDatatype;
						$isClass = true;

						$typesFound++;

					} elseif (strtolower($varDatatype) === 'string') {
						$isString = true;

						$typesFound++;

					} elseif (strtolower($varDatatype) === 'int') {
						$isNumber = true;

						$typesFound++;

					} elseif (strtolower($varDatatype) === 'float') {
						$isDecimal = true;

						$typesFound++;

					} elseif (strtolower($varDatatype) === 'array' || strtolower($varDatatype) === 'mixed[]') {
						$isArray = true;

						$typesFound++;

					} elseif (strtolower($varDatatype) === 'bool') {
						$isBool = true;

						$typesFound++;

					} elseif (strtolower($varDatatype) === 'null') {
						$isNullable = true;
					}
				}

				if ($typesFound > 0) {
					if ($typesFound > 1) {
						$fields[] = new Hydrators\Fields\MixedField(
							$isNullable,
							$mappedKey,
							$fieldName,
							$crud->isRequired(),
							$crud->isWritable()
						);

					} elseif ($isClass && $className !== null) {
						try {
							$typeRc = new ReflectionClass($className);

							if ($typeRc->implementsInterface(DateTimeInterface::class) || $className === DateTimeInterface::class) {
								$fields[] = new Hydrators\Fields\DateTimeField(
									$isNullable,
									$mappedKey,
									$fieldName,
									$crud->isRequired(),
									$crud->isWritable()
								);

							} elseif ($typeRc->isSubclassOf(Consistence\Enum\Enum::class)) {
								$fields[] = new Hydrators\Fields\EnumField(
									$className,
									$isNullable,
									$mappedKey,
									$fieldName,
									$crud->isRequired(),
									$crud->isWritable()
								);

							} elseif ($typeRc->implementsInterface(ArrayAccess::class)) {
								$fields[] = new Hydrators\Fields\ArrayField(
									$isNullable,
									$mappedKey,
									$fieldName,
									$crud->isRequired(),
									$crud->isWritable()
								);

							} else {
								$fields[] = new Hydrators\Fields\SingleEntityField(
									$className,
									$isNullable,
									$mappedKey,
									$isRelationship,
									$fieldName,
									$crud->isRequired(),
									$crud->isWritable()
								);
							}

						} catch (ReflectionException $ex) {
							$fields[] = new Hydrators\Fields\SingleEntityField(
								$className,
								$isNullable,
								$mappedKey,
								$isRelationship,
								$fieldName,
								$crud->isRequired(),
								$crud->isWritable()
							);
						}

					} elseif ($isString) {
						$fields[] = new Hydrators\Fields\TextField(
							$isNullable,
							$mappedKey,
							$fieldName,
							$crud->isRequired(),
							$crud->isWritable()
						);

					} elseif ($isNumber || $isDecimal) {
						$fields[] = new Hydrators\Fields\NumberField(
							$isDecimal,
							$isNullable,
							$mappedKey,
							$fieldName,
							$crud->isRequired(),
							$crud->isWritable()
						);

					} elseif ($isArray) {
						$fields[] = new Hydrators\Fields\ArrayField(
							$isNullable,
							$mappedKey,
							$fieldName,
							$crud->isRequired(),
							$crud->isWritable()
						);

					} elseif ($isBool) {
						$fields[] = new Hydrators\Fields\BooleanField(
							$isNullable,
							$mappedKey,
							$fieldName,
							$crud->isRequired(),
							$crud->isWritable()
						);
					}
				}
			}
		}

		return $fields;
	}

	/**
	 * Get the model method name for a resource relationship key
	 *
	 * @param string $entityKey
	 *
	 * @return string|null
	 */
	private function getRelationshipKey(string $entityKey): ?string
	{
		$this->normalizeRelationships();

		return $this->normalizedRelationships[$entityKey] ?? null;
	}

	/**
	 * @return void
	 */
	private function normalizeRelationships(): void
	{
		if (is_array($this->normalizedRelationships)) {
			return;
		}

		$this->normalizedRelationships = [];

		if ($this->relationships !== []) {
			foreach ($this->relationships as $resourceKey => $entityKey) {
				if (is_numeric($resourceKey)) {
					$resourceKey = $entityKey;
				}

				$this->normalizedRelationships[$entityKey] = $resourceKey;
			}
		}
	}

	/**
	 * @param string $entityKey
	 *
	 * @return string|null
	 */
	private function getAttributeKey(string $entityKey): ?string
	{
		$this->normalizeAttributes();

		return $this->normalizedAttributes[$entityKey] ?? null;
	}

	/**
	 * @return void
	 */
	private function normalizeAttributes(): void
	{
		if (is_array($this->normalizedAttributes)) {
			return;
		}

		$this->normalizedAttributes = [];

		if ($this->attributes !== []) {
			foreach ($this->attributes as $resourceKey => $entityKey) {
				if (is_numeric($resourceKey)) {
					$resourceKey = $entityKey;
				}

				$this->normalizedAttributes[$entityKey] = $resourceKey;
			}
		}
	}

	/**
	 * @param string $entityKey
	 *
	 * @return string|null
	 */
	private function getCompositedAttributeKey(string $entityKey): ?string
	{
		$this->normalizeCompositeAttributes();

		return $this->normalizedCompositedAttributes[$entityKey] ?? null;
	}

	/**
	 * @return void
	 */
	private function normalizeCompositeAttributes(): void
	{
		if (is_array($this->normalizedCompositedAttributes)) {
			return;
		}

		$this->normalizedCompositedAttributes = [];

		if ($this->compositedAttributes !== []) {
			foreach ($this->compositedAttributes as $resourceKey => $entityKey) {
				if (is_numeric($resourceKey)) {
					$resourceKey = $entityKey;
				}

				$this->normalizedCompositedAttributes[$entityKey] = $resourceKey;
			}
		}
	}

	/**
	 * @param ReflectionProperty $rp
	 * @param string $name
	 *
	 * @return string|null
	 */
	private function parseAnnotation(ReflectionProperty $rp, string $name): ?string
	{
		if ($rp->getDocComment() === false) {
			return null;
		}

		$factory = phpDocumentor\Reflection\DocBlockFactory::createInstance();
		$docblock = $factory->create($rp->getDocComment());

		foreach ($docblock->getTags() as $tag) {
			if ($tag->getName() === $name) {
				return trim((string) $tag);
			}
		}

		return null;
	}

	/**
	 * @return string
	 *
	 * @phpstan-return class-string
	 */
	abstract protected function getEntityName(): string;

	/**
	 * @param string $className
	 * @param JsonAPIDocument\Objects\IStandardObject<mixed> $attributes
	 * @param Hydrators\Fields\IField[] $entityMapping
	 * @param DoctrineCrud\Entities\IEntity|null $entity
	 * @param string|null $rootField
	 *
	 * @return mixed[]
	 */
	protected function hydrateAttributes(
		string $className,
		JsonAPIDocument\Objects\IStandardObject $attributes,
		array $entityMapping,
		?DoctrineCrud\Entities\IEntity $entity,
		?string $rootField
	): array {
		$data = [];

		$isNew = $entity === null;

		foreach ($entityMapping as $field) {
			if ($field instanceof Hydrators\Fields\EntityField && $field->isRelationship()) {
				continue;
			}

			// Continue only if attribute is present
			if (
				!$attributes->has($field->getMappedName())
				&& !in_array($field->getFieldName(), $this->compositedAttributes, true)
			) {
				continue;
			}

			// If there is a specific method for this attribute, we'll hydrate that
			$value = $this->callHydrateAttribute($field->getFieldName(), $attributes, $entity);
			$value = $value ?? $field->getValue($attributes);

			if ($value === null && $field->isRequired() && $isNew) {
				$this->errors->addError(
					StatusCodeInterface::STATUS_UNPROCESSABLE_ENTITY,
					$this->translator->translate('//jsonApi.hydrator.missingRequiredAttribute.heading'),
					$this->translator->translate('//jsonApi.hydrator.missingRequiredAttribute.message'),
					[
						'pointer' => 'data/attributes/' . $field->getMappedName(),
					]
				);

			} elseif ($field->isWritable() || ($isNew && $field->isRequired())) {
				if ($field instanceof Hydrators\Fields\SingleEntityField) {
					// Get attribute entity class name
					$fieldClassName = $field->getClassName();

					/** @var string|JsonAPIDocument\Objects\IStandardObject<mixed> $fieldAttributes */
					$fieldAttributes = $attributes->get($field->getMappedName());

					if ($fieldAttributes instanceof JsonAPIDocument\Objects\IStandardObject) {
						$data[$field->getFieldName()] = $this->hydrateAttributes(
							$fieldClassName,
							$fieldAttributes,
							$this->mapEntity($fieldClassName),
							$entity,
							$field->getMappedName()
						);

						if (!isset($data[$field->getFieldName()]['entity'])) {
							$data[$field->getFieldName()]['entity'] = $fieldClassName;
						}

					} elseif ($value !== null || $field->isNullable()) {
						$data[$field->getFieldName()] = $value;
					}

				} else {
					$data[$field->getFieldName()] = $value;
				}
			}
		}

		try {
			if (class_exists($className)) {
				$rc = new ReflectionClass($className);

				if ($rc->getConstructor() !== null) {
					$constructor = $rc->getConstructor();

					foreach ($constructor->getParameters() as $num => $parameter) {
						if (
							!$parameter->isVariadic()
							&& $attributes->has($this->getAttributeKey($parameter->getName()) ?? $parameter->getName())
						) {
							// If there is a specific method for this attribute, we'll hydrate that
							$value = $this->callHydrateAttribute($parameter->getName(), $attributes, $entity);
							$value = $value ?? $attributes->get($this->getAttributeKey($parameter->getName()) ?? $parameter->getName());

							$data[$parameter->getName()] = $value;

						} elseif ($attributes->has($this->getAttributeKey((string) $num) ?? (string) $num)) {
							// If there is a specific method for this attribute, we'll hydrate that
							$value = $this->callHydrateAttribute((string) $num, $attributes, $entity);
							$value = $value ?? $attributes->get($this->getAttributeKey((string) $num) ?? (string) $num);

							$data[$num] = $value;
						}
					}
				}

				$data['entity'] = $className;
			}

		} catch (Throwable $ex) {
			// Nothing to do here
		}

		if (!$isNew) {
			foreach ($data as $attribute => $value) {
				$isAllowed = false;

				foreach ($entityMapping as $field) {
					if ($field instanceof Hydrators\Fields\EntityField && $field->isRelationship()) {
						$isAllowed = true;

						continue;
					}

					if ($field->getFieldName() === $attribute) {
						$isAllowed = true;
					}
				}

				if (!$isAllowed) {
					unset($data[$attribute]);
				}
			}
		}

		return $data;
	}

	/**
	 * Hydrate a attribute by invoking a method on this hydrator.
	 *
	 * @param string $attributeKey
	 * @param JsonAPIDocument\Objects\IStandardObject<mixed> $attributes
	 * @param DoctrineCrud\Entities\IEntity|null $entity
	 *
	 * @return mixed|null
	 */
	private function callHydrateAttribute(
		string $attributeKey,
		JsonAPIDocument\Objects\IStandardObject $attributes,
		?DoctrineCrud\Entities\IEntity $entity = null
	) {
		$method = $this->methodForAttribute($attributeKey);

		if ($method === '' || !method_exists($this, $method)) {
			return null;
		}

		$callable = [$this, $method];

		if (is_callable($callable)) {
			return call_user_func($callable, $attributes, $entity);
		}

		return null;
	}

	/**
	 * Return the method name to call for hydrating the specific attribute.
	 *
	 * If this method returns an empty value, or a value that is not callable, hydration
	 * of the the relationship will be skipped
	 *
	 * @param string $key
	 *
	 * @return string
	 */
	private function methodForAttribute(string $key): string
	{
		return sprintf('hydrate%sAttribute', $this->classify($key));
	}

	/**
	 * Gets the upper camel case form of a string.
	 *
	 * @param string $value
	 *
	 * @return string
	 */
	private function classify(string $value): string
	{
		$converted = ucwords(str_replace(['-', '_'], ' ', $value));

		return str_replace(' ', '', $converted);
	}

	/**
	 * @param JsonAPIDocument\Objects\IRelationships<mixed> $relationships
	 * @param Hydrators\Fields\IField[] $entityMapping
	 * @param JsonAPIDocument\Objects\IResourceObjectCollection<JsonAPIDocument\Objects\IResourceObject>|null $included
	 * @param DoctrineCrud\Entities\IEntity|null $entity
	 *
	 * @return mixed[]
	 */
	protected function hydrateRelationships(
		JsonAPIDocument\Objects\IRelationships $relationships,
		array $entityMapping,
		?JsonAPIDocument\Objects\IResourceObjectCollection $included = null,
		?DoctrineCrud\Entities\IEntity $entity = null
	): array {
		$data = [];

		foreach ($entityMapping as $field) {
			if ($field instanceof Hydrators\Fields\EntityField && $field->isRelationship()) {
				if ($relationships->has($field->getMappedName())) {
					$relationship = $relationships->getRelationship($field->getMappedName());

					// If there is a specific method for this relationship, we'll hydrate that
					$result = $this->callHydrateRelationship(
						$field->getMappedName(),
						$relationship,
						$included,
						$entity
					);

					if ($result !== null) {
						$data[$field->getFieldName()] = $result;

						continue;
					}

					// If this is a has-one, we'll hydrate it
					if ($relationship->isHasOne()) {
						$relationshipEntity = $this->hydrateHasOne(
							$field,
							$relationship,
							$entity,
							$entityMapping
						);

						$data[$field->getFieldName()] = $relationshipEntity;

					} elseif ($relationship->isHasMany()) {
						$relationshipEntities = $this->hydrateHasMany(
							$field,
							$relationship,
							$entity,
							$entityMapping
						);

						$data[$field->getFieldName()] = $relationshipEntities;
					}

				} elseif ($field->isRequired() && $entity === null) {
					$this->errors->addError(
						StatusCodeInterface::STATUS_UNPROCESSABLE_ENTITY,
						$this->translator->translate('//jsonApi.hydrator.missingRequiredRelation.heading'),
						$this->translator->translate('//jsonApi.hydrator.missingRequiredRelation.message'),
						[
							'pointer' => 'data/relationships/' . $field->getMappedName() . '/data/id',
						]
					);
				}
			}
		}

		return $data;
	}

	/**
	 * Hydrate a relationship by invoking a method on this hydrator.
	 *
	 * @param string $relationshipKey
	 * @param JsonAPIDocument\Objects\IRelationship<mixed> $relationship
	 * @param JsonAPIDocument\Objects\IResourceObjectCollection<JsonAPIDocument\Objects\IResourceObject>|null $included
	 * @param DoctrineCrud\Entities\IEntity|null $entity
	 *
	 * @return mixed[]|DoctrineCrud\Entities\IEntity|null
	 */
	private function callHydrateRelationship(
		string $relationshipKey,
		JsonAPIDocument\Objects\IRelationship $relationship,
		?JsonAPIDocument\Objects\IResourceObjectCollection $included = null,
		?DoctrineCrud\Entities\IEntity $entity = null
	) {
		$method = $this->methodForRelationship($relationshipKey);

		if ($method === '' || !method_exists($this, $method)) {
			return null;
		}

		$callable = [$this, $method];

		if (is_callable($callable)) {
			$result = call_user_func($callable, $relationship, $included, $entity);

			if (
				$result === null
				|| is_array($result)
				|| $result instanceof DoctrineCrud\Entities\IEntity
			) {
				return $result;
			}

			throw new Exceptions\InvalidStateException(sprintf('Relationship have to be an array or entity instance, %s provided.', get_class($result)));
		}

		return null;
	}

	/**
	 * Return the method name to call for hydrating the specific relationship.
	 *
	 * If this method returns an empty value, or a value that is not callable, hydration
	 * of the the relationship will be skipped
	 *
	 * @param string $key
	 *
	 * @return string
	 */
	private function methodForRelationship(string $key): string
	{
		return sprintf('hydrate%sRelationship', $this->classify($key));
	}

	/**
	 * Hydrate a resource has-one relationship
	 *
	 * @param Hydrators\Fields\IField $field
	 * @param JsonAPIDocument\Objects\IRelationship<mixed> $relationship
	 * @param DoctrineCrud\Entities\IEntity|null $entity
	 * @param Hydrators\Fields\IField[] $entityMapping
	 *
	 * @return DoctrineCrud\Entities\IEntity|null
	 */
	protected function hydrateHasOne(
		Hydrators\Fields\IField $field,
		JsonAPIDocument\Objects\IRelationship $relationship,
		?DoctrineCrud\Entities\IEntity $entity,
		array $entityMapping
	): ?DoctrineCrud\Entities\IEntity {
		// Find relationship field
		if (
			$field instanceof Hydrators\Fields\EntityField
			&& $field->isRelationship()
		) {
			if ($field->isWritable() || ($entity === null && $field->isRequired())) {
				if ($relationship->getData() !== null && $relationship->getIdentifier() !== null) {
					$relationEntity = $this->findRelated($field->getClassName(), $relationship->getIdentifier());

					if ($relationEntity !== null) {
						return $relationEntity;

					} elseif ($entity === null && $field->isRequired()) {
						$this->errors->addError(
							StatusCodeInterface::STATUS_UNPROCESSABLE_ENTITY,
							$this->translator->translate('//jsonApi.hydrator.missingRequiredRelation.heading'),
							$this->translator->translate('//jsonApi.hydrator.missingRequiredRelation.message'),
							[
								'pointer' => 'data/relationships/' . $field->getMappedName() . '/data/id',
							]
						);
					}

				} elseif ($entity === null && $field->isRequired()) {
					$this->errors->addError(
						StatusCodeInterface::STATUS_UNPROCESSABLE_ENTITY,
						$this->translator->translate('//jsonApi.hydrator.missingRequiredRelation.heading'),
						$this->translator->translate('//jsonApi.hydrator.missingRequiredRelation.message'),
						[
							'pointer' => 'data/relationships/' . $field->getMappedName() . '/data/id',
						]
					);
				}
			}
		}

		return null;
	}

	/**
	 * @param string $entityClassName
	 * @param JsonAPIDocument\Objects\IResourceIdentifier<mixed> $identifier
	 *
	 * @return DoctrineCrud\Entities\IEntity|null
	 */
	private function findRelated(
		string $entityClassName,
		JsonAPIDocument\Objects\IResourceIdentifier $identifier
	): ?DoctrineCrud\Entities\IEntity {
		if (!Uuid\Uuid::isValid($identifier->getId())) {
			return null;
		}

		if (!class_exists($entityClassName)) {
			return null;
		}

		$entityManager = $this->managerRegistry->getManagerForClass($entityClassName);

		if ($entityManager !== null) {
			/** @var DoctrineCrud\Entities\IEntity|null $entity */
			$entity = $entityManager
				->getRepository($entityClassName)
				->find($identifier->getId());

			return $entity;
		}

		return null;
	}

	/**
	 * Hydrate a resource has-many relationship
	 *
	 * @param Hydrators\Fields\IField $field
	 * @param JsonAPIDocument\Objects\IRelationship<mixed> $relationship
	 * @param DoctrineCrud\Entities\IEntity|null $entity
	 * @param Hydrators\Fields\IField[] $entityMapping
	 *
	 * @return DoctrineCrud\Entities\IEntity[]
	 */
	protected function hydrateHasMany(
		Hydrators\Fields\IField $field,
		JsonAPIDocument\Objects\IRelationship $relationship,
		?DoctrineCrud\Entities\IEntity $entity,
		array $entityMapping
	): array {
		$relations = [];

		// Find relationship field
		if (
			$field instanceof Hydrators\Fields\EntityField
			&& $field->isRelationship()
		) {
			if ($field->isWritable() || ($entity === null && $field->isRequired())) {
				if ($relationship->getData() instanceof JsonAPIDocument\Objects\IResourceIdentifierCollection) {
					foreach ($relationship->getData() as $relation) {
						if ($relation instanceof JsonAPIDocument\Objects\IResourceIdentifier) {
							$relationEntity = $this->findRelated($field->getClassName(), $relation);

							if ($relationEntity !== null) {
								$relations[] = $relationEntity;
							}
						}
					}
				}

				if ($entity === null && $field->isRequired() && count($relations) === 0) {
					$this->errors->addError(
						StatusCodeInterface::STATUS_UNPROCESSABLE_ENTITY,
						$this->translator->translate('//jsonApi.hydrator.missingRequiredRelation.heading'),
						$this->translator->translate('//jsonApi.hydrator.missingRequiredRelation.message'),
						[
							'pointer' => 'data/relationships/' . $field->getMappedName() . '/data',
						]
					);
				}
			}

			return $relations;
		}

		return [];
	}

}
