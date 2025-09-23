<?php

declare(strict_types=1);

namespace Drupal\canvas_test_autocomplete\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class AutocompleteController {

  private array $items = [
    ['value' => 'apple', 'label' => 'Apple'],
    ['value' => 'banana', 'label' => 'Banana'],
    ['value' => 'pear', 'label' => 'Pear'],
    ['value' => 'mango', 'label' => 'Mango'],
  ];

  public function result(Request $request): JsonResponse {
    $search_string = $request->query->get('q');
    assert(is_string($search_string));
    $results = array_filter($this->items, fn($item) => str_contains($item['value'], strtolower($search_string)));
    return new JsonResponse(array_values($results));
  }

}
