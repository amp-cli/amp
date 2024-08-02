<?php
namespace Amp\Command;

use Amp\Util\Filesystem;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DatadirCommand extends ContainerAwareCommand {

  /**
   * @var \Amp\Util\Filesystem
   */
  private $fs;

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

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $perm = $this->getContainer()->get('perm');
    foreach ($input->getArgument('path') as $path) {
      if (!$this->fs->exists($path)) {
        $output->writeln("<info>Create data directory: $path</info>");
        $this->fs->mkdir($path);
        $perm->applyDirPermission('write', $path);
      }
      else {
        $output->writeln("<info>Update data directory: $path</info>");
        $perm->applyDirPermission('write', $path);
      }
    }

    return 0;
  }

}
