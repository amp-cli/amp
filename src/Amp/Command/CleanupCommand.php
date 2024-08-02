<?php
namespace Amp\Command;

use Amp\Util\Filesystem;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CleanupCommand extends ContainerAwareCommand {

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
      ->setName('cleanup')
      ->setDescription('Destroy any stale MySQL/Apache instances (linked to old/non-existent paths)')
      ->addOption('force', 'f', InputOption::VALUE_NONE, 'Destroy ALL databases (regardless of whether the code exists)');
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $instances = $this->getContainer()->get('instances');
    $instances->lock();
    $count = 0;
    foreach ($instances->findAll() as $instance) {
      if ($input->getOption('force') || !file_exists($instance->getRoot())) {
        $output->writeln("Destroy (root={$instance->getRoot()}, name={$instance->getName()}, dsn={$instance->getDsn()})");
        $instances->remove($instance->getId());
        $count++;
      }
      else {
        $output->writeln("Skip (root={$instance->getRoot()}, name={$instance->getName()}, dsn={$instance->getDsn()})");
      }
    }

    $output->writeln("Destroyed {$count} instance(s)");

    $instances->save();
    return 0;
  }

}
