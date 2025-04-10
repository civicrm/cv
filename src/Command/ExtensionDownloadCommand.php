<?php
namespace Civi\Cv\Command;

use Civi\Cv\Cv;
use Civi\Cv\Exception\QueueTaskException;
use Civi\Cv\ExtensionPolyfill\PfQueueDownloader;
use Civi\Cv\Util\ConsoleSubprocessQueueRunner;
use Civi\Cv\Util\ExtensionTrait;
use Civi\Cv\Util\Filesystem;
use Civi\Cv\Util\HeadlessDownloader;
use Civi\Cv\Util\VerboseApi;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

class ExtensionDownloadCommand extends CvCommand {

  use ExtensionTrait;

  /**
   * @param string|null $name
   */
  public function __construct($name = NULL) {
    parent::__construct($name);
  }

  protected function configure() {
    $this
      ->setName('ext:download')
      ->setAliases(array('dl'))
      ->setDescription('Download and enable an extension')
      ->addOption('bare', 'b', InputOption::VALUE_NONE, 'Perform a basic download in a non-bootstrapped environment. Implies --level=none, --no-install, and no --refresh. You must specify the download URL.')
      ->addOption('refresh', 'r', InputOption::VALUE_NONE, 'Refresh the remote list of extensions (Default: Only refresh on cache-miss)')
      ->addOption('no-install', NULL, InputOption::VALUE_NONE, 'Only download. Skip the installation.')
      ->addOption('force', 'f', InputOption::VALUE_NONE, 'If an extension already exists, download it anyway.')
      ->addOption('to', NULL, InputOption::VALUE_OPTIONAL, 'Download to a specific directory (absolute path).')
      ->addOption('keep', 'k', InputOption::VALUE_NONE, 'If an extension already exists, keep it.')
      ->addOption('dry-run', NULL, InputOption::VALUE_NONE, 'Preview the list of tasks')
      ->addOption('step', NULL, InputOption::VALUE_NONE, 'Run the tasks in steps, pausing before each step')
      ->addArgument('key-or-name', InputArgument::IS_ARRAY, 'One or more extensions to enable. Identify the extension by full key ("org.example.foobar") or short name ("foobar"). Optionally append a URL.')
      ->setHelp('Download and enable an extension

Examples:
  cv ext:download org.example.foobar
  cv dl foobar
  cv dl --dev foobar
  cv dl -b "@https://example.org/files/foobar/info.xml" --to="$PWD/myext"

The extension can be specified using any of the following:

  - Long name (ex: "org.example.foobar"). Resolved via civicrm.org.
  - Short name (ex: "foobar"). Resolved via civicrm.org.
  - Long name + Zip URL (ex: "org.example.foobar@http://example.org/files/foobar-1.2.zip")
  - Info XML URL (ex: "@http://example.org/files/foobar/info.xml")

Note:
  By default, extensions are downloaded to the site\'s writable
  extension folder, but you can optionally specify --to.

  Beginning circa CiviCRM v4.2+, it has been recommended that extensions
  include a unique long name ("org.example.foobar") and a unique short
  name ("foobar"). However, short names are not strongly guaranteed.

  This subcommand does not output parseable data. For parseable output,
  consider using `cv api extension.install`.
');
    $this->configureRepoOptions();
  }

  protected function initialize(InputInterface $input, OutputInterface $output) {
    if ($input->hasOption('bare') && $input->getOption('bare')) {
      $input->setOption('level', 'none');
      $input->setOption('no-install', TRUE);
      if (empty($input->getOption('to'))) {
        throw new \LogicException("If --bare is specified, then --to must also be specified.");
      }
    }
    if ($extRepoUrl = $this->parseRepoUrl($input)) {
      global $civicrm_setting;
      $civicrm_setting['Extension Preferences']['ext_repo_url'] = $extRepoUrl;
    }
    parent::initialize($input, $output);
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    if ($input->getOption('step')) {
      if ($output->getVerbosity() < OutputInterface::VERBOSITY_VERY_VERBOSE) {
        $output->setVerbosity(OutputInterface::VERBOSITY_VERY_VERBOSE);
      }
    }

    $fs = new Filesystem();

    if ($input->getOption('to') && !$fs->isAbsolutePath($input->getOption('to'))) {
      throw new \RuntimeException("The --to argument requires an absolute path.");
    }

    if ($this->isBooted()) {
      $output->writeln("<info>Using extension feed \"" . \CRM_Extension_System::singleton()->getBrowser()->getRepositoryUrl() . "\"</info>");
    }

    // Refresh extensions if (a) ---refresh enabled or (b) there's a cache-miss.
    $refresh = $input->getOption('refresh') ? 'yes' : 'auto';
    while (TRUE) {
      if ($refresh === 'yes' && $this->isBooted()) {
        $output->writeln("<info>Refreshing extension cache</info>");
        $result = VerboseApi::callApi3Success('Extension', 'refresh', array(
          'local' => FALSE,
          'remote' => TRUE,
        ));
        if (!empty($result['is_error'])) {
          return 1;
        }
      }

      [$requestedDownloads, $errors] = $this->parseDownloads($input);
      if ($refresh == 'auto' && !empty($errors)) {
        $output->writeln("<info>Extension cache does not contain requested item(s)</info>");
        $refresh = 'yes';
      }
      else {
        break;
      }
    }

    if (!empty($errors)) {
      foreach ($errors as $error) {
        $output->getErrorOutput()->writeln("<error>$error</error>");
      }
      $output->getErrorOutput()->writeln("<comment>Tip: To customize the feed, review options in \"cv {$input->getFirstArgument()} --help\"");
      $output->getErrorOutput()->writeln("<comment>Tip: To browse available requestedDownloads, run \"cv ext:list -R\"</comment>");
      return 1;
    }

    if ($input->getOption('to') && count($requestedDownloads) > 1) {
      throw new \RuntimeException("When specifying --to, you can only download one extension at a time.");
    }
    elseif (empty($requestedDownloads)) {
      Cv::output()->writeln('Nothing to do.');
      return 0;
    }
    elseif ($input->getOption('bare')) {
      return $this->executeWithBare($requestedDownloads);
    }
    else {
      return $this->executeWithQueue($requestedDownloads);
    }
  }

  /**
   * In a bare download, we don't have access to a copy of CiviCRM. This is useful
   * if you want to grab a specific extension from a feed (before Civi is installed).
   *
   * Ex: cv dl -b "@https://civicrm.org/extdir/ver=$DM_VERSION/$SOME_EXT.xml" --to="$SOME_FOLDER"
   *
   * @param array $requestedDownloads
   * @return int
   */
  protected function executeWithBare(array $requestedDownloads): int {
    foreach ($requestedDownloads as $key => $url) {
      $action = $this->pickAction(Cv::input(), Cv::output(), $key);
      switch ($action) {
        case 'download':
          if ($to = Cv::input()->getOption('to')) {
            Cv::output()->writeln("<info>Downloading extension \"$key\" ($url) to \"$to\"</info>");
            $dl = new HeadlessDownloader();
            $dl->run($url, $key, Cv::input()->getOption('to'), TRUE);
          }
          else {
            throw new \LogicException("Missing option --to");
          }
          break;

        case 'abort':
          Cv::output()->writeln("<error>Aborted</error>");
          return 1;

        case 'install':
        case 'skip':
          Cv::output()->writeln("<comment>Skipped extension \"$key\".</comment>");
          break;

        default:
          throw new \RuntimeException("Unrecognized action: $action");
      }
    }

    return 0;
  }

  /**
   * Perform a normal download. This may involve several steps, such as fetching a ZIP,
   * extracting it, putting it inplace (replacing an old ext), flushing caches,
   * running upgrades, etc.
   *
   * This runs in a queue, and each queue item is launched as a separate subprocess.
   *
   * @param array $requestedDownloads
   * @return int
   */
  protected function executeWithQueue(array $requestedDownloads): int {
    $this->assertBooted();

    if (Cv::input()->getOption('to')) {
      // FIXME
      throw new \RuntimeException('Queued downloader does not currently support --to');
    }

    $queueSpec = $this->createQueueSpec();
    $newQueueSpec = array_merge($queueSpec, ['reset' => TRUE]);
    $reloadQueueSpec = array_merge($queueSpec, ['reset' => FALSE]);
    $queue = \CRM_Queue_Service::singleton()->create($newQueueSpec);

    if (class_exists('CRM_Extension_QueueDownloader') && class_exists('CRM_Extension_QueueTasks')) {
      // Note: 6.1.0 has QueueDownloader but an insufficient signature. Presence of QueueTasks correlates with revised signature (6.1.1-ish).
      $downloader = new \CRM_Extension_QueueDownloader(TRUE, $queue);
      $runner = new ConsoleSubprocessQueueRunner(Cv::io(), $reloadQueueSpec, Cv::input()->getOption('dry-run'), Cv::input()->getOption('step'));
    }
    else {
      $downloader = new PfQueueDownloader(TRUE, $queue);
      $runner = new ConsoleSubprocessQueueRunner(Cv::io(), $reloadQueueSpec, Cv::input()->getOption('dry-run'), Cv::input()->getOption('step'));
    }

    $batch = NULL;
    $updateBatch = function(?string $method, array $data) use (&$batch, $downloader) {
      if ($batch === NULL) {
        $batch = ['method' => $method, 'data' => $data];
      }
      elseif ($batch['method'] === $method) {
        $batch['data'] = array_merge($batch['data'], $data);
      }
      else {
        if ($batch['method'] === 'addDownloads') {
          $downloader->addDownloads($batch['data'], !Cv::input()->getOption('no-install'));
        }
        elseif ($batch['method'] === 'addEnable') {
          $downloader->addEnable($batch['data']);
        }
        else {
          throw new \LogicException("Unrecognized method: $method");
        }
        $batch = ['method' => $method, 'data' => $data];
      }
    };

    foreach ($requestedDownloads as $key => $url) {
      $action = $this->pickAction(Cv::input(), Cv::output(), $key);
      switch ($action) {
        case 'download':
          $updateBatch('addDownloads', [$key => $url]);
          break;

        case 'install':
          $updateBatch('addEnable', [$key]);
          break;

        case 'abort':
          Cv::output()->writeln("<error>Aborted</error>");
          return 1;

        case 'skip':
          Cv::output()->writeln("<comment>Skipped extension \"$key\".</comment>");
          break;

        default:
          throw new \RuntimeException("Unrecognized action: $action");
      }
    }

    $updateBatch(NULL, []);
    $downloader->fillQueue();
    try {
      $runner->runAll();
      return 0;
    }
    catch (QueueTaskException $e) {
      // The earlier handlers will output details.
      return 1;
    }
  }

  protected function createQueueSpec(): array {
    return [
      'name' => 'cli-ext-dl',
      'type' => 'Sql',
      'runner' => 'task',
      'is_autorun' => FALSE,
      'retry_limit' => 0,
      'error' => 'abort',
      'is_persistent' => FALSE,
    ];
  }

  /**
   * Get a list of all available extensions.
   *
   * @return array
   *   ($key => CRM_Extension_Info)
   */
  protected function getRemoteInfos() {
    static $cache = NULL;
    if ($cache === NULL) {
      $this->assertBooted();
      $cache = \CRM_Extension_System::singleton()
        ->getBrowser()->getExtensions();
    }
    return $cache;
  }

  /**
   * @return array
   *   Array(string $shortName => string $longName).
   */
  protected function getRemoteShortMap() {
    static $cache = NULL;
    if ($cache === NULL) {
      $cache = array();
      foreach ($this->getRemoteInfos() as $key => $info) {
        if ($info->file) {
          $cache[$info->file][] = $key;
        }
      }
    }
    return $cache;
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @return array
   *   Array(array $downloads, array $errors).
   */
  protected function parseDownloads(InputInterface $input) {
    // Array(string $key => null|string $url)
    $downloads = array();
    // Array(string $message).
    $errors = array();

    $remoteInfos = NULL;
    $shortMap = NULL;

    if (!$input->getArgument('key-or-name')) {
      $errors[] = 'Error: Please specify at least one extension to download';
    }

    foreach ($input->getArgument('key-or-name') as $keyOrName) {
      $origExpr = $keyOrName;
      $url = NULL;
      if (strpos($keyOrName, '@') !== FALSE) {
        [$keyOrName, $url] = explode('@', $keyOrName, 2);
      }

      if (empty($keyOrName) && !empty($url)) {
        if (!preg_match('/\.xml$/', $url)) {
          $errors[] = "Unclear file reference ($origExpr). Please provide either \"key@http://example/file.zip\" or \"@http://example/file.xml\".";
          continue;
        }
        $xmlString = file_get_contents($url);
        if (empty($xmlString)) {
          $errors[] = "Failed to fetch XML file ($origExpr).";
          continue;
        }
        $xml = simplexml_load_string($xmlString);
        $keyOrName = (string) $xml->attributes()->key;
        $url = (string) $xml->downloadUrl;
        if (!$keyOrName || !$url) {
          $errors[] = "The specified XML file is missing the key and/or downloadUrl ($origExpr).";
          continue;
        }
      }

      if ($this->isBooted() && strpos($keyOrName, '.') === FALSE) {
        if ($shortMap === NULL) {
          $shortMap = $this->getRemoteShortMap();
        }
        if (isset($shortMap[$keyOrName])) {
          if (count($shortMap[$keyOrName]) === 1) {
            $keyOrName = $shortMap[$keyOrName][0];
          }
          else {
            $otherNames = '"' . implode('", "', $shortMap[$keyOrName]) . '"';
            $errors[] = "Ambiguous name \"$keyOrName\". Use a more specific key: $otherNames";
            continue;
          }
        }
      }

      if ($this->isBooted() && empty($url)) {
        if ($remoteInfos === NULL) {
          $remoteInfos = $this->getRemoteInfos();
        }

        if (!empty($remoteInfos[$keyOrName]->downloadUrl)) {
          $url = $remoteInfos[$keyOrName]->downloadUrl;
        }
      }

      if (empty($url)) {
        $errors[] = $this->isBooted()
          ? "Error: Unrecognized extension \"$keyOrName\""
          : "Error: unrecognized extension \"$keyOrName\" cannot be resolved in bare environment";
        continue;
      }

      $downloads[$keyOrName] = $url;
    }
    return array($downloads, $errors);
  }

  /**
   * Determine what action to take with the extension -- e.g. perform
   * a real "download" or merely "install" the existing extension.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @param string $key
   *   Ex: 'org.civicrm.shoreditch'.
   * @return string
   *   Ex: 'download', 'install', 'abort'.
   */
  protected function pickAction(
    InputInterface $input,
    OutputInterface $output,
    $key
  ) {
    if ($input->getOption('to')) {
      $exists = file_exists($input->getOption('to'));
    }
    elseif ($this->isBooted()) {
      $existingExts = \CRM_Extension_System::singleton()
        ->getFullContainer()->getKeys();
      $exists = in_array($key, $existingExts);
    }
    else {
      throw new \RuntimeException("In --bare mode, you must specify the target path with --to.");
    }

    $action = NULL;
    if (!$exists) {
      return 'download';
    }
    elseif ($input->getOption('keep')) {
      return $input->getOption('no-install') ? 'skip' : 'install';
    }
    elseif ($input->getOption('force')) {
      return 'download';
    }
    else {
      $helper = $this->getHelper('question');
      $question = new ChoiceQuestion(
        "The extension \"$key\" already exists. What you like to do?",
        array(
          'k' => 'Keep existing extension. (Default) (Equivalent to option "-k")',
          'd' => 'Download anyway. (Equivalent to option "-f")',
          'a' => 'Abort',
        ),
        'k'
      );
      switch ($helper->ask($input, $output, $question)) {
        case 'd':
          return 'download';

        case 'k':
          return $input->getOption('no-install') ? 'skip' : 'install';

        case 'a':
        default:
          return 'abort';
      }
    }
  }

}
