<?php

namespace Drupal\drd_install_core\Plugin\WebformElement;

use Drupal\webform\Plugin\WebformElement\Url;

/**
 * Provides a 'drd_domain' element.
 *
 * @WebformElement(
 *   id = "drd_domain",
 *   api = "https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Render!Element!Url.php/class/Url",
 *   label = @Translation("DRD Domain"),
 *   description = @Translation("Provides a form element for input of a domain for DRD."),
 *   category = @Translation("Advanced elements"),
 * )
 *
 * @todo Improve this widget by ...
 *   - make it always required
 *   - make it always unique and validate against existing DRD domains too
 *   - make sure the url is always with https
 */
class Domain extends Url {

}
