<?php

namespace Drupal\Tests\rdf\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\DataProvider;
use Drupal\Tests\BrowserTestBase;
use Drupal\rdf\RdfMappingHelper;

/**
 * Test RSS View integration.
 */
#[Group('rdf')]
#[RunTestsInSeparateProcesses]
class RssViewIntegrationTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'rdf_test_namespaces',
    'views',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Data provider for ::testRdfNamespacesAreAddedToRssViews().
   *
   * @return array[]
   *   The test cases.
   */
  public static function providerRdfNamespacesAreAddedToRssViews(): array {
    return [
      'content with default settings' => [
        'rdf-rss-test-node',
      ],
      'content as fields' => [
        'rdf-rss-test-node-fields',
      ],
      'users as fields' => [
        'rdf-rss-test-user-fields',
      ],
    ];
  }

  /**
   * Tests that RSS views have RDF's XML namespaces defined.
   *
   * @param string $path
   *   The path of the RSS feed to visit.
   *
   * @dataProvider providerRdfNamespacesAreAddedToRssViews
   */
  #[DataProvider('providerRdfNamespacesAreAddedToRssViews')]
  public function testRdfNamespacesAreAddedToRssViews(string $path): void {
    $this->drupalGet($path);
    $this->assertSession()->statusCodeEquals(200);

    // Mink insists on treating the page as an HTML document, so we have to use
    // PHP's built-in DOM extension to examine the RSS feed.
    $xml = $this->getSession()->getPage()->getContent();
    $document = new \DOMDocument();
    $this->assertTrue($document->loadXML($xml));

    // Ensure that RDF's namespaces are defined on the root <rss> element.
    $namespaces = \Drupal::service(RdfMappingHelper::class)->getNamespaces();
    // Views' RSS feed plugins unconditionally override the `dc` namespace.
    // @see \Drupal\views\Plugin\views\row\RssFields::render()
    // @see \Drupal\views\Plugin\views\style\Rss::render()
    $namespaces['dc'] = 'http://purl.org/dc/elements/1.1/';
    foreach ($namespaces as $prefix => $uri) {
      $this->assertSame($uri, $document->lookupNamespaceURI($prefix));
    }
  }

}
