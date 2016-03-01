<?php
namespace Amp\Command;

use Amp\Instance;
use Amp\Util\Filesystem;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Templating\EngineInterface;

class DatadirCommand extends ContainerAwareCommand {

  /**
   * @param \Amp\Application $app
   * @param string|null $name
   */
  public function __construct(\Amp\Application $app, $name = NULL) {
    $this->fs = new Filesystem();
    parent::__construct($app, $name);
  }

  protected function configure() {
    $this
      ->setName('datadir')
      ->setDescription('Create or mark a data directory')
      ->addArgument("path", InputArgument::IS_ARRAY | InputArgument::REQUIRED, "Path to a web-writable data-directory");
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $perm = $this->getContainer()->get('perm');
    foreach ($input->getArgument('path') as $path) {
      if (!$this->fs->exists($path)) {
        $output->writeln("<info>Create data directory: $path</info>");
        $this->fs->mkdir($path);
        $perm->applyDirPermission('write', $path);
      } else {
        $output->writeln("<info>Update data directory: $path</info>");
        $perm->applyDirPermission('write', $path);
      }
    }
  }
}
