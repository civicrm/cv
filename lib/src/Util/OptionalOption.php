<?php

namespace Civi\Cv\Util;

class OptionalOption {

  /**
   * Parse an option's data. This is for options where the default behavior
   * (of total omission) differs from the activated behavior
   * (of an active but unspecified option).
   *
   * Example, suppose we want these interpretations:
   *   cv en         ==> Means "--refresh=auto"; see $omittedDefault
   *   cv en -r      ==> Means "--refresh=yes"; see $activeDefault
   *   cv en -r=yes  ==> Means "--refresh=yes"
   *   cv en -r=no   ==> Means "--refresh=no"
   *
   * @param \CvDeps\Symfony\Component\Console\Input\InputInterface|\Symfony\Component\Console\Input\InputInterface $input
   * @param array $rawNames
   *   Ex: array('-r', '--refresh').
   * @param string $omittedDefault
   *   Value to use if option is completely omitted.
   * @param string $activeDefault
   *   Value to use if option is activated without data.
   * @return string
   */
  public static function parse($input, $rawNames, $omittedDefault, $activeDefault) {
    $value = NULL;
    foreach ($rawNames as $rawName) {
      if ($input->hasParameterOption($rawName)) {
        if (NULL === $input->getParameterOption($rawName)) {
          return $activeDefault;
        }
        else {
          return $input->getParameterOption($rawName);
        }
      }
    }
    return $omittedDefault;
  }

}
