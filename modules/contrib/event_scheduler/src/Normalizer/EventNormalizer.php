<?php
/**
 * Created by PhpStorm.
 * User: steve
 * Date: 29/08/18
 * Time: 11:13
 */

namespace Drupal\event_scheduler\Normalizer;

use Drupal\event_scheduler\Event\EventScheduleInterface;
use Symfony\Component\Serializer\Exception\BadMethodCallException;
use Symfony\Component\Serializer\Exception\CircularReferenceException;
use Symfony\Component\Serializer\Exception\ExtraAttributesException;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Exception\RuntimeException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareTrait;

class EventNormalizer implements NormalizerInterface, DenormalizerInterface {

  const FORMAT = 'json';

  use SerializerAwareTrait;

  /**
   * Normalizes an object into a set of arrays/scalars.
   *
   * @param mixed $object Object to normalize
   * @param string $format Format the normalization result will be encoded as
   * @param array $context Context options for the normalizer
   *
   * @return array|string|int|float|bool
   *
   * @throws InvalidArgumentException   Occurs when the object given is not an attempted type for the normalizer
   * @throws CircularReferenceException Occurs when the normalizer detects a circular reference when no circular
   *                                    reference handler can fix it
   * @throws LogicException             Occurs when the normalizer is not called in an expected context
   */
  public function normalize($object, $format = NULL, array $context = []) {
    if (!$object instanceof EventScheduleInterface) {
      throw new InvalidArgumentException('Supplied object to be normalized does not implement "EventScheduleInterface".');
    }
    if ($format !== static::FORMAT) {
      throw new InvalidArgumentException('Supplied normalization format is not supported.');
    }

    // Just use PHP serialization as the normalization,
    // which can then be serialized again. It works fine.
    return serialize($object);
  }

  /**
   * Checks whether the given class is supported for normalization by this normalizer.
   *
   * @param mixed $data Data to normalize
   * @param string $format The format being (de-)serialized from or into
   *
   * @return bool
   */
  public function supportsNormalization($data, $format = NULL): bool {
    return $format === static::FORMAT && $data instanceof EventScheduleInterface;
  }

  /**
   * Denormalizes data back into an object of the given class.
   *
   * @param mixed $data Data to restore
   * @param string $class The expected class to instantiate
   * @param string $format Format the given data was extracted from
   * @param array $context Options available to the denormalizer
   *
   * @return object
   *
   * @throws BadMethodCallException   Occurs when the normalizer is not called in an expected context
   * @throws InvalidArgumentException Occurs when the arguments are not coherent or not supported
   * @throws UnexpectedValueException Occurs when the item cannot be hydrated with the given data
   * @throws ExtraAttributesException Occurs when the item doesn't have attribute to receive given data
   * @throws LogicException           Occurs when the normalizer is not supposed to denormalize
   * @throws RuntimeException         Occurs if the class cannot be instantiated
   */
  public function denormalize($data, $class, $format = NULL, array $context = []): object {
    if ($format !== static::FORMAT) {
      throw new InvalidArgumentException('Supplied denormalization format is not supported.');
    }

    // No cleverness, we used serialize() to create it so...
    return unserialize($data);
  }

  /**
   * Checks whether the given class is supported for denormalization by this normalizer.
   *
   * @param mixed $data Data to denormalize from
   * @param string $type The class to which the data should be denormalized
   * @param string $format The format being deserialized from
   *
   * @return bool
   */
  public function supportsDenormalization($data, $type, $format = NULL): bool {
    return $format === static::FORMAT && is_a($type, EventScheduleInterface::class, TRUE);
  }
}