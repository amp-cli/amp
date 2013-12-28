<?php
namespace Amp\Command;

use Amp\Instance;
use Amp\Permission\PermissionInterface;
use Amp\Util\Filesystem;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Templating\EngineInterface;

class DatadirCommand extends ContainerAwareCommand {

  /**
   * @var PermissionInterface
   */
  var $perm;

  /**
   * @param \Amp\Application $app
   * @param string|null $name
   * @param PermissionInterface $perm)
   */
  public function __construct(\Amp\Application $app, $name = NULL, PermissionInterface $perm) {
    $this->fs = new Filesystem();
    $this->perm = $perm;
    parent::__construct($app, $name);
  }

  protected function configure() {
    $this
      ->setName('datadir')
      ->setDescription('Create or mark a data directory')
      ->addArgument("path", InputArgument::IS_ARRAY | InputArgument::REQUIRED, "Path to a web-writable data-directory");
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    foreach ($input->getArgument('path') as $path) {
      if (!$this->fs->exists($path)) {
        $output->writeln("<info>Create data directory: $path</info>");
        $this->fs->mkdir($path);
        $this->perm->applyDirPermission('write', $path);
      } else {
        $output->writeln("<info>Update data directory: $path</info>");
        $this->perm->applyDirPermission('write', $path);
      }
    }
  }
}
