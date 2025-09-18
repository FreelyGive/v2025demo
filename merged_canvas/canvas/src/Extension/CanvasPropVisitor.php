<?php

declare(strict_types=1);

namespace Drupal\canvas\Extension;

use Drupal\Core\Template\TwigNodeTrans;
use Masterminds\HTML5;
use Twig\Environment;
use Twig\Node\Expression\NameExpression;
use Twig\Node\ModuleNode;
use Twig\Node\Node;
use Twig\Node\Nodes;
use Twig\Node\PrintNode;
use Twig\Node\TextNode;
use Twig\NodeVisitor\NodeVisitorInterface;

/**
 * Defines a Twig node visitor for reacting to print node calls.
 */
final class CanvasPropVisitor implements NodeVisitorInterface {

  /**
   * Keeps track of text buffer for a given node.
   *
   * @var string[]
   */
  protected array $buffer;

  /**
   * Recursion prevention.
   *
   * @var bool
   */
  protected bool $replacingFilter = FALSE;

  /**
   * TwigNodeTrans can't handle compound nodes.
   *
   * It calls $node->getAttribute('data') without checking that the node is an
   * instance of \Twig\Node\TextNode. We can't add property boundaries inside
   * {% trans %} tags.
   *
   * @var bool
   *
   * @see https://www.drupal.org/project/drupal/issues/3486273
   */
  protected bool $inTranslationTag = FALSE;

  /**
   * {@inheritdoc}
   */
  public function enterNode(Node $node, Environment $env): Node {
    // We've entered a new Twig template (ModuleNode). We start a buffer
    // entry for the given template so we can keep track of the printed HTML.
    // We want to wrap twig print statements (i.e. {{ variable }}) in an HTML
    // comment, but we can only do this in contexts where HTML comments are
    // allowed. For example, we can wrap 'variable' here because HTML comments
    // are allowed in an element's inner HTML.
    // @code
    // <div>{{ variable }}</div>
    // @endcode
    // But we cannot wrap 'variable' here because HTML comments are not allowed
    // in attribute values.
    // @code
    // <div class="{{ variable }}"></div>
    // @endcode
    if ($node instanceof ModuleNode && $node->getSourceContext() !== NULL) {
      // Initialize the buffer for this template.
      $this->buffer[$node->getSourceContext()->getName()] = '';
      return $node;
    }
    // We're visiting a text node, Twig uses this for any text or markup inside
    // a template. For example consider this template.
    // @code
    // <div class="{{ className }}">Hi {{ name }}</div>
    // {% if new %}<span class="marker">New</span>{% endif %}
    // @code
    // When parsed, the Twig token stream would contain the following TextNodes
    // - <div class="
    // - ">Hi
    // - </div>
    // - <span class="marker">New</span>
    // The other control structures would be represented by other Twig node
    // types.
    if ($node instanceof TextNode && $node->getSourceContext() !== NULL && $node->hasAttribute('data')) {
      // Append the text node's contents to the buffer for this template.
      $this->buffer[$node->getSourceContext()->getName()] .= $node->getAttribute('data');
      return $node;
    }
    if ($node instanceof TwigNodeTrans) {
      // Keep track that we've visited a translation tag and toggle off our
      // replacements for all child traversals.
      $this->inTranslationTag = TRUE;
      return $node;
    }
    // We've reached a PrintNode, Twig uses this for outputting a variable, e.g.
    // @code
    // {{ variable }}
    // @endcode
    if ($node instanceof PrintNode &&
      // We're not inside a {% trans %} wrapper.
      !$this->inTranslationTag &&
      // We're not revisiting a node we just replaced - Twig calls node visitors
      // recursively, including on any elements we return here as replacements.
      !$this->replacingFilter &&
      // We have access to the HTML buffer for the parent template.
      $node->getSourceContext()) {
      $expr = $node->getNode('expr');
      // We are printing the outcome of another expression rather than a
      // variable.
      if (!$expr instanceof NameExpression || !$expr->hasAttribute('name')) {
        return $node;
      }
      // Try to parse the current buffer to ascertain if we're in a context
      // where HTML comments are allowed.
      $html5 = new HTML5(['disable_html_ns' => TRUE, 'encoding' => 'UTF-8']);
      $html5->loadHTMLFragment($this->buffer[$node->getSourceContext()->getName()]);
      if (!$html5->hasErrors()) {
        // We have valid HTML5 in the buffer.
        $name = $expr->getAttribute('name');
        $line_number = $node->getTemplateLine();
        // Prevent recursive replacement when Twig traverses into the replaced
        // node.
        $this->replacingFilter = TRUE;
        // Build our replacement.
        $nodes = [
          new CanvasWrapperNode($name, TRUE, $line_number),
          $node,
          new CanvasWrapperNode($name, FALSE, $line_number),
        ];
        return new Nodes($nodes);
      }
    }
    return $node;
  }

  /**
   * {@inheritdoc}
   */
  public function leaveNode(Node $node, Environment $env): Node {
    if ($node instanceof ModuleNode && $node->getSourceContext() !== NULL) {
      // We have left a template, we no longer need the buffer for it, so flush
      // it.
      unset($this->buffer[$node->getSourceContext()->getName()]);
      return $node;
    }
    if ($node instanceof PrintNode) {
      // We have finished our replacement, so can safely turn off the flag that
      // prevents recursion.
      $this->replacingFilter = FALSE;
      return $node;
    }
    if ($node instanceof TwigNodeTrans) {
      // We are leaving the {% trans %} tag.
      $this->inTranslationTag = FALSE;
      return $node;
    }

    return $node;
  }

  /**
   * {@inheritdoc}
   */
  public function getPriority(): int {
    // Runs before the EscapeNodeVisitor, which has priority 0.
    return -1;
  }

}
