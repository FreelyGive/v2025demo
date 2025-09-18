<?php

declare(strict_types=1);

namespace Drupal\canvas_e2e_support\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class KeyValueController extends ControllerBase {

  public function __construct(
    #[Autowire(service: 'keyvalue')]
    private readonly KeyValueFactoryInterface $keyValueFactory,
  ) {
  }

  public function setKeyValue(string $collection, Request $request): Response {
    $values = json_decode($request->getContent(), TRUE);
    foreach ($values as $key => $value) {
      $this->keyValueFactory->get($collection)->set($key, $value);
    }
    return new Response('Key values set successfully.', Response::HTTP_OK);
  }

}
