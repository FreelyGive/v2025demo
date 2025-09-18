<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Unit;

use Drupal\canvas\JsonSchemaInterpreter\JsonSchemaType;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\RegexValidator;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

// cspell:ignore fitok itok Bwidth

/**
 * Tests (and documents) the `pattern`s used in schema.json.
 *
 * @covers schema.json
 * @group canvas
 */
class SchemaJsonPatternsTest extends UnitTestCase {

  /**
   * @testWith [true, "https://example.com/path/to/foo.png"]
   *           [true, "http://example.com/path/to/foo.png"]
   *           [true, "//example.com/path/to/foo.png"]
   *           [true, "/path/to/foo.png"]
   *           [true, "path/to/foo.png"]
   *           [true, "foo.png"]
   *           [false, "ftp://example.com/path/to/foo.png"]
   *           [false, "hi mom"]
   *           [false, "/vfs://root/sites/simpletest/91669142/files/test.png?alternateWidths=/vfs%3A//root/sites/simpletest/91669142/files/styles/canvas_parametrized_width--%7Bwidth%7D/public/test.png.webp%3Fitok%3DFEbY0Wjq"]
   *           [true, "/sites/simpletest/14043668/files/2025-05/cats-1.jpg?alternateWidths=/sites/simpletest/14043668/files/styles/canvas_parametrized_width--%7Bwidth%7D/public/2025-05/cats-1.jpg.webp%3Fitok%3DZx0T1Bpm"]
   *           [true, "http://core.test/sites/simpletest/28370209/files/2025-05/cats-1.jpg?alternateWidths=http%3A//core.test/sites/simpletest/28370209/files/styles/canvas_parametrized_width--%7Bwidth%7D/public/2025-05/cats-1.jpg.webp%3Fitok%3Dq3ey5yYT"]
   *
   * The 3 last ones are evaluated results of `src_with_alternate_widths`:
   * - kernel test without VfsPublicStreamUrlTrait: nonsensical VFS URLs ❌
   * - kernel test with VfsPublicStreamUrlTrait: root-relative URLs ✅
   * - functional test/recipe: absolute URLs ✅
   *
   * Breaking down the pattern:
   * - `^(\/|https?:\/\/)` → must start with `/` or `http://` or `https://`
   * - `?` → actually, allow it to start with neither of the three
   * - `(?!.*\:\/\/)` → but then disallow any other protocol: forbid `://`
   * - `[^\s]+$` → require the absence of spaces anywhere
   * The latter is surprising, but is needed to ensure that a relative URL
   * such as `cat.png` is accepted, but a string such as `hello cat` causes
   * the regex to not match, because everything before is optional. It is
   * imperfect, but that is okay: detailed URL validation happens in a lower
   * level, this is just to catch obviously invalid values.
   */
  public function testImageUriPattern(bool $is_valid, string $file_url): void {
    // Load (and convert to PCRE) the pattern from /schema.json only once.
    static $pcre_pattern;
    if (!isset($pcre_pattern)) {
      $module_root = dirname(__DIR__, 3);
      // @phpstan-ignore-next-line argument.type
      $json = json_decode(file_get_contents("$module_root/schema.json"), TRUE);
      $json_schema_pattern = $json['$defs']['image-uri']['pattern'];
      $pcre_pattern = JsonSchemaType::patternToPcre($json_schema_pattern);
    }

    $context = $this->createMock(ExecutionContextInterface::class);
    if ($is_valid) {
      $context->expects($this->never())->method('buildViolation');
    }
    else {
      $context->expects($this->once())->method('buildViolation');
    }

    $v = new RegexValidator();
    $v->initialize($context);
    $v->validate($file_url, new Regex(pattern: $pcre_pattern));
  }

}
