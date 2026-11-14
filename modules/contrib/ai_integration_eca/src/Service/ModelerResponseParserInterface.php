<?php

namespace Drupal\ai_integration_eca\Service;

/**
 * Splits an assistant reply into prose and a structured envelope.
 *
 * The assistant is asked to answer in prose and, when it has something
 * machine readable to add, to append a single fenced JSON code block. This
 * service is the counterpart of that instruction: it finds the block, checks
 * that its contents are usable, and hands back the prose without it.
 */
interface ModelerResponseParserInterface {

  /**
   * Parses an assistant reply.
   *
   * The parser never fails. Anything it cannot make sense of is treated as
   * prose, because an answer the user can read is always better than an error
   * message about a code block they never asked for.
   *
   * @param string $reply
   *   The raw reply as returned by the chat processor.
   * @param array $context
   *   What a proposed plan may refer to, passed straight through to the plan
   *   validator. All keys are optional: "nodes" and "edges" hold the IDs
   *   currently on the canvas, "plugins" the plugin IDs this site offers.
   *
   * @return array
   *   An array with these keys:
   *   - reply: The prose, with any recognized envelope removed.
   *   - suggestions: A list of validated component suggestions, each an array
   *     with the "plugin", "componentType", "label" and "reason" keys. Empty
   *     when the reply carried none.
   *   - operations: A validated plan of graph operations, ready to be applied
   *     in order. Empty when the reply proposed none or the plan was refused.
   *   - operationsReason: Why a proposed plan was refused, as one of the
   *     validator's REASON_* constants, or an empty string. Callers turn this
   *     into a sentence for the user rather than into an error.
   */
  public function parse(string $reply, array $context = []): array;

}
