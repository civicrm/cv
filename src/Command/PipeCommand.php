<?php
namespace Civi\Cv\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PipeCommand extends CvCommand {

  /**
   * @param string|null $name
   */
  public function __construct($name = NULL) {
    parent::__construct($name);
  }

  protected function configure() {
    $this
      ->setName('pipe')
      ->setDescription('Start a Civi::pipe session (JSON-RPC 2.0)')
      ->setHelp('Start a Civi::pipe session (JSON-RPC 2.0)

The Civi::pipe protocol provides a line-oriented session for executing multiple requests in a single CiviCRM instance.

Callers may request *connection flags*, such as:

* v: Show version
* l: Show login support
* t: Enable trusted mode
* u: Enable untrusted mode

Examples:

  $ cv pipe
  {"Civi::pipe":{"v":"5.47.alpha1","t":"trusted","l":["nologin"]}}

  $ cv pipe uv
  {"Civi::pipe":{"u":"untrusted","v":"5.47.alpha1"}}

See also: https://docs.civicrm.org/dev/en/latest/framework/pipe
');
    $this->addArgument('connection-flags', InputArgument::OPTIONAL, 'List of connection-flags (Default: Determined by civicrm-core)', NULL);
    // Tempting to add separate CLI flags that map to 'connection-flags', but this seems easier given that:
    // 1. The existing flags have different meanings (eg (v)erbose vs (v)ersion).
    // 2. The connection-flags are owned by civicrm-core.git. Don't want to update this whenever there's a new one.
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    if (!is_callable(['Civi', 'pipe'])) {
      fwrite(STDERR, "This version of CiviCRM does not include Civi::pipe() support.\n");
      return 1;
    }
    if ($flags = $input->getArgument('connection-flags')) {
      \Civi::pipe($flags);
    }
    else {
      \Civi::pipe();
    }
    return 0;
  }

}
