<?php
namespace Civi\Cv\Util;

// **********************
// ** WORK IN PROGRESS **
// **********************

class CliEditor {

  /**
   * @var callable|NULL
   */
  protected $validator = NULL;

  /**
   * @param string $buffer
   * @param int $attempts
   * @return null|string
   *   Update $buffer, or NULL on error.
   */
  public function editBuffer($buffer, $attempts = 2) {
    $tmpFile = tempnam(sys_get_temp_dir(), 'cv-editor-');
    chmod($tmpFile, 0600);
    file_put_contents($tmpFile, $buffer);

    if (!$this->editFile($tmpFile, $attempts)) {
      unlink($tmpFile);
      return NULL;
    }

    $buffer = file_get_contents($tmpFile);
    unlink($tmpFile);
    return $buffer;
  }

  /**
   * Open the editor with a given file.
   *
   * @param string $file
   * @param int $maxAttempts
   * @return bool
   *   TRUE on success.
   */
  public function editFile($file, $maxAttempts = 2) {
    $attempt = 0;
    do {
      $attempt++;
      $cmd = $this->pick();
      if (!$cmd) {
        return FALSE;
      }
      passthru("$cmd " . escapeshellarg($file), $return);
      if (!$return !== 0) {
        return FALSE;
      }


      if ($this->validator === NULL) {
        $isValid = TRUE;
      }
      else {
        list ($isValid, $message) = call_user_func($this->validator, $file);
        if (!$isValid && $attempt >= $maxAttempts) {
          return FALSE;
        }
        if (!$isValid && $message) {
          file_put_contents($file, $message . file_get_contents($file));
        }
      }

    } while (!$isValid);
    return TRUE;
  }

  /**
   * Determine the name of the editor.
   *
   * @return string|NULL
   */
  public function pick() {
    if (getenv('VISUAL') && $this->findCommand(getenv('VISUAL'))) {
      return getenv('VISUAL');
    }
    elseif (getenv('EDITOR') && $this->findCommand(getenv('EDITOR'))) {
      return getenv('EDITOR');
    }
    elseif ($this->findCommand('editor')) {
      return 'editor';
    }
    elseif ($this->findCommand('vi')) {
      return 'vi';
    }
    else {
      return NULL;
    }
  }

  protected function findCommand($name) {
    $paths = explode(PATH_SEPARATOR, getenv('PATH'));
    foreach ($paths as $path) {
      if (file_exists("$path/$name")) {
        return "$path/$name";
      }
    }
    return NULL;
  }

  /**
   * @return callable|NULL
   */
  public function getValidator() {
    return $this->validator;
  }

  /**
   * @param callable|NULL $validator
   */
  public function setValidator($validator) {
    $this->validator = $validator;
  }

}
