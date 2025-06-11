<?php

namespace Civi\Cv\Log;

class MultiLoggerException extends \RuntimeException {

  /**
   * @var \Throwable[]
   */
  protected $errors;

  /**
   * @param \Throwable[] $errors
   */
  public function __construct($errors = []) {
    $messages = [];
    foreach ($errors as $error) {
      $messages[] = $error->getMessage();
    }
    $this->errors = $errors;
    parent::__construct(implode("\n", $messages));
  }

}
