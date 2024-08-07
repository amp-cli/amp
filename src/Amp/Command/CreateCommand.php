<?php
namespace Amp\Command;

use Amp\Instance;
use Amp\Util\Filesystem;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CreateCommand extends ContainerAwareCommand {

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
      ->setName('create')
      ->setDescription('Create a DB+HTTPD instance')
      ->addOption('root', 'r', InputOption::VALUE_REQUIRED, 'The local path to the document root', getcwd())
      ->addOption('name', 'N', InputOption::VALUE_REQUIRED, 'Brief technical identifier for the service', '')
      ->addOption('skip-db', NULL, InputOption::VALUE_NONE, 'Do not generate a DB')
      ->addOption('skip-url', NULL, InputOption::VALUE_NONE, 'Do not expose on the web')
      ->addOption('url', NULL, InputOption::VALUE_REQUIRED, 'Specify the preferred web URL for this service. (Omit to auto-generate)')
      ->addOption('visibility', NULL, InputOption::VALUE_REQUIRED, 'Which interfaces the vhost should be available on ("local","all")', '')
      ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite any pre-existing httpd/db container')
      ->addOption('perm', NULL, InputOption::VALUE_REQUIRED, 'Permission level of the DB User ("admin","super")', "admin")
      ->addOption('prefix', NULL, InputOption::VALUE_REQUIRED, 'Prefix to place in front of each outputted variable', 'AMP_')
      ->addOption('output-file', 'o', InputOption::VALUE_REQUIRED, 'Output environment variables to file instead of stdout')
      ->setHelp('
Create a SQL database and HTTP virtual host.

The database credentials and HTTP configuration are determined by
the current configuration. For more, see "amp config".

Note: Some applications may require custom HTTP configurations,
especially if deployed on nginx. You can override the default
HTTP template by setting an environment variable:

 * NGINX_VHOST_TPL=/path/to/my-template.php
 * APACHE_VHOST_TPL=/path/to/my-template.php
 * APACHE24_VHOST_TPL=/path/to/my-template.php

Or by creating a file in the web-root (or its parent):

 * .amp/nginx-vhost.php
 * .amp/apache-vhost.php
 * .amp/apache24-vhost.php
');
  }

  protected function initialize(InputInterface $input, OutputInterface $output) {
    $root = $this->fs->toAbsolutePath($input->getOption('root'));
    if (!$this->fs->exists($root)) {
      throw new \Exception("Failed to locate root: " . $root);
    }
    else {
      $input->setOption('root', $root);
    }
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $instances = $this->getContainer()->get('instances');
    $instances->lock();

    $container = $this->getContainer();
    $db_type = $container->getParameter('db_type');
    if ($db_type == "") {
      $this->doCommand($output, OutputInterface::VERBOSITY_NORMAL, 'config', array());
      $this->getApplication()->loadContainer();
      $instances = $this->getContainer()->get('instances');
    }
    $instance = $instances->find(Instance::makeId($input->getOption('root'), $input->getOption('name')));
    if ($instance === NULL) {
      $instance = new Instance();
      $instance->setRoot($input->getOption('root'));
      $instance->setName($input->getOption('name'));
    }
    elseif (!$input->getOption('force')) {
      throw new \Exception("Cannot create instance. Use -f to overwrite existing instance.");
    }

    if ($input->getOption('url')) {
      $url = $input->getOption('url');
      $urlScheme = parse_url($url, PHP_URL_SCHEME);
      if (!$urlScheme) {
        $url = 'http://' . ltrim($url, '/');
      }
      $instance->setUrl($url);
    }
    if ($input->getOption('visibility')) {
      $instance->setVisibility($input->getOption('visibility'));
    }

    $instances->create($instance, !$input->getOption('skip-url'), !$input->getOption('skip-db'), $input->getOption('perm'));
    $instances->save();

    if ($output->getVerbosity() > OutputInterface::VERBOSITY_QUIET) {
      $this->export($instance->getRoot(), $instance->getName(), $input->getOption('prefix'), $input->getOption('output-file'), $output);
    }

    return 0;
  }

  protected function export($root, $name, $prefix, $output_file_path, OutputInterface $output) {
    $command = $this->getApplication()->find('export');
    $arguments = array(
      'command' => 'export',
      '--root' => $root,
      '--name' => $name,
      '--prefix' => $prefix,
    );
    if ($output_file_path != '') {
      $arguments['--output-file'] = $output_file_path;
    }
    return $command->run(new \Symfony\Component\Console\Input\ArrayInput($arguments), $output);
  }

}
