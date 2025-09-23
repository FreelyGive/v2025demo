<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Unit;

use cebe\openapi\json\JsonPointer;
use cebe\openapi\ReferenceContext;
use cebe\openapi\spec\Schema;
use Drupal\Component\Serialization\Yaml;
use Drupal\Tests\canvas\Traits\OpenApiSpecTrait;
use Drupal\Tests\UnitTestCase;
use DrupalFinder\DrupalFinderComposerRuntime;
use JsonSchema\Validator;
use League\OpenAPIValidation\Schema\Exception\KeywordMismatch;
use League\OpenAPIValidation\Schema\Exception\SchemaMismatch;
use League\OpenAPIValidation\Schema\SchemaValidator;

/**
 * Validates this Drupal module's OpenAPI spec against the OpenAPI JSON schema.
 *
 * @group canvas.
 *
 * @requires function \cebe\openapi\Reader::readFromYamlFile
 * @requires function \DrupalFinder\DrupalFinderComposerRuntime::getVendorDir
 * @requires function \League\OpenAPIValidation\Schema\SchemaValidator::validate
 */
final class OpenApiSpecValidationTest extends UnitTestCase {

  use OpenApiSpecTrait;

  /**
   * Path to OpenAPI 3.0 document.
   */
  private ?string $documentLocation = NULL;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $tested_paths = [];

    $finder = new DrupalFinderComposerRuntime();
    $vendor_directory = $finder->getVendorDir();

    if ($vendor_directory) {
      $document_location = __DIR__ . '/../../../openapi-v3.1.json';
      if (file_exists($document_location)) {
        $this->documentLocation = $document_location;
      }
      else {
        $tested_paths[] = $document_location;
      }
    }

    if (!$this->documentLocation) {
      throw new \Exception(sprintf('Could not find OpenAPI schema at %s.', implode(' or ', $tested_paths)));
    }
  }

  /**
   * Tests OpenAPI specification is valid.
   */
  public function testSpecIsValid(): void {
    $specification = $this->getSpecification();
    $specification->validate();
    $this->assertSame([], $specification->getErrors());
    $validator = new Validator();
    $open_api_data = $specification->getSerializableData();
    $validator->validate($open_api_data, (object) ['$ref' => 'file://' . $this->documentLocation]);
    $this->assertTrue($validator->isValid(), implode(array_map(function (array $error) {
      return sprintf('%s:%s%s', $error['property'], $error['message'], \PHP_EOL);
    }, $validator->getErrors())));
  }

  public function testForbidPatternProperties(): void {
    $file = file_get_contents(__DIR__ . '/../../../openapi.yml');
    assert(!empty($file));
    $encoded = json_encode(Yaml::decode($file));
    assert(is_string($encoded));
    // Check the encoded string to allow 'patternProperties' in comments.
    $this->assertFalse(str_contains($encoded, 'patternProperties'), '`patternProperties` in the the openapi.yml file is not supported use `additionalProperties` instead.');
  }

  public function testCliFixtures(): void {
    $file = \file_get_contents(\dirname(__DIR__, 3) . '/cli/assets/templates/hello-world/component.yml');
    \assert(\is_string($file));
    $data = Yaml::decode($file);
    $data += ['importedJsComponents' => []];

    $specification = $this->getSpecification();
    $context = new ReferenceContext($specification, "/");
    $context->throwException = FALSE;
    $context->mode = ReferenceContext::RESOLVE_MODE_ALL;
    $specification->resolveReferences($context);
    $specification->setDocumentContext($specification, new JsonPointer(''));
    try {
      $schema = $specification->components?->schemas['NewCodeComponent'];
      \assert($schema instanceof Schema);
      (new SchemaValidator())->validate($data, $schema);
      $this->addToAssertionCount(1);
    }
    catch (KeywordMismatch | SchemaMismatch $e) {
      $fields_chain = $e->dataBreadCrumb()?->buildChain() ?? [];
      $field = \array_pop($fields_chain);
      self::fail(\sprintf('%s: %s - %s', $field, \implode('.', $fields_chain), $e->getMessage()));
    }
  }

}
