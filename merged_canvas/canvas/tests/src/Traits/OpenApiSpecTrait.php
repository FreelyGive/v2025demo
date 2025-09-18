<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Traits;

use cebe\openapi\json\JsonPointer;
use cebe\openapi\Reader;
use cebe\openapi\ReferenceContext;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Schema;
use League\OpenAPIValidation\Schema\Exception\KeywordMismatch;
use League\OpenAPIValidation\Schema\Exception\SchemaMismatch;
use League\OpenAPIValidation\Schema\SchemaValidator;

/**
 * Trait for validating against this Drupal module's OpenAPI specification.
 */
trait OpenApiSpecTrait {

  /**
   * Gets the OpenAPI object for this Drupal module's OpenAPI specification.
   *
   * @return \cebe\openapi\spec\OpenApi
   *   OpenAPI object representing this entire OpenAPI specification.
   *
   * @see https://github.com/OAI/OpenAPI-Specification/blob/3.0.2/versions/3.0.2.md#openapi-object
   */
  protected function getSpecification(): OpenApi {
    $specification_path = $this->getSpecificationPath();
    $specification = Reader::readFromYamlFile($specification_path);
    $context = new ReferenceContext($specification, "/");
    $context->throwException = FALSE;
    $context->mode = ReferenceContext::RESOLVE_MODE_ALL;
    $specification->resolveReferences($context);
    $specification->setDocumentContext($specification, new JsonPointer(''));
    return $specification;
  }

  /**
   * Gets the path to the OpenAPI specification.
   */
  private function getSpecificationPath(): string {
    return sprintf(
      '%s/openapi.yml',
      dirname(__DIR__, 3),
    );
  }

  /**
   * Assert data complies with this OpenAPI specification.
   *
   * @param array $data
   *   Data.
   * @param string $schemaType
   *   Schema type.
   */
  protected function assertDataCompliesWithApiSpecification(array $data, string $schemaType): void {
    $validator = new SchemaValidator();
    try {
      $specification = $this->getSpecification();
      assert(!is_null($specification->components));
      assert($specification->components->schemas[$schemaType] instanceof Schema);
      $validator->validate($data, $specification->components->schemas[$schemaType]);
      $this->addToAssertionCount(1);
    }
    catch (KeywordMismatch $e) {
      assert(!is_null($e->dataBreadCrumb()));
      $this->fail(sprintf('%s:%s %s', implode('➡', $e->dataBreadCrumb()->buildChain()), $e->keyword(), $e->getMessage()));
    }
    catch (SchemaMismatch $e) {
      assert(!is_null($e->dataBreadCrumb()));
      $this->fail(sprintf('%s %s', implode('➡', $e->dataBreadCrumb()->buildChain()), $e->getMessage()));
    }
  }

}
