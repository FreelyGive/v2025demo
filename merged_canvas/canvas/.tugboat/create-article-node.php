<?php

declare(strict_types=1);

// We assume the "Standard" profile is installed at this point, along with the
// Drupal Canvas modules.

use Drupal\node\Entity\Node;

$node = Node::create([
  'type' => 'article',
  'title' => 'Canvas Needs This For The Time Being',
]);
$node->save();
