<?php

namespace Civi\Cv;

class ClassAliases {

  public static function register() {
    // Plugins need to access some classes from the cv dependency tree.
    // Concrete class names may be prefixed depending on build/environment.
    // These aliases work with or without prefixing.

    class_alias('Symfony\Component\Console\Command\Command', 'CvDeps\Symfony\Component\Console\Command\Command');
    class_alias('Symfony\Component\Console\Command\LockableTrait', 'CvDeps\Symfony\Component\Console\Command\LockableTrait');

    class_alias('Symfony\Component\Console\Input\InputInterface', 'CvDeps\Symfony\Component\Console\Input\InputInterface');
    class_alias('Symfony\Component\Console\Input\InputArgument', 'CvDeps\Symfony\Component\Console\Input\InputArgument');
    class_alias('Symfony\Component\Console\Input\InputDefinition', 'CvDeps\Symfony\Component\Console\Input\InputDefinition');
    class_alias('Symfony\Component\Console\Input\InputOption', 'CvDeps\Symfony\Component\Console\Input\InputOption');

    class_alias('Symfony\Component\Console\Output\OutputInterface', 'CvDeps\Symfony\Component\Console\Output\OutputInterface');

    class_alias('Symfony\Component\Console\Style\OutputStyle', 'CvDeps\Symfony\Component\Console\Style\OutputStyle');
    class_alias('Symfony\Component\Console\Style\SymfonyStyle', 'CvDeps\Symfony\Component\Console\Style\SymfonyStyle');
    class_alias('Symfony\Component\Console\Style\StyleInterface', 'CvDeps\Symfony\Component\Console\Style\StyleInterface');

    // NOTE: Using a static list of class-names serves two purposes:
    // 1. It helps IDEs/static-analyzers recognize the aliases.
    // 2. It allows php-scoper to rewrite the concrete names to their final/contingent form.
  }

}
