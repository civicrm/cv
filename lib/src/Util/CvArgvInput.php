<?php

namespace Civi\Cv\Util;

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputDefinition;

/**
 * @internal
 */
class CvArgvInput extends ArgvInput {

  protected $originalArgv;

  public function __construct(?array $argv = NULL, ?InputDefinition $definition = NULL) {
    $argv = $argv ?? $_SERVER['argv'] ?? [];
    $this->originalArgv = $argv;
    parent::__construct($argv, $definition);
  }

  public function getOriginalArgv(): array {
    return $this->originalArgv;
  }

}
