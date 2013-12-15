<?php
namespace Amp\Command;

use Amp\ConfigRepository;
use Symfony\Component\Console\Helper\DialogHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ConfigCommand extends ContainerAwareCommand {

  /**
   * @var ConfigRepository
   */
  private $config;

  /**
   * @param \Amp\Application $app
   * @param string|null $name
   * @param array $parameters list of configuration parameters to accept ($key => $label)
   */
  public function __construct(\Amp\Application $app, $name = NULL, ConfigRepository $config = NULL) {
    $this->config = $config;
    parent::__construct($app, $name);
  }

  protected function configure() {
    $this
      ->setName('config')
      ->setDescription('Interactively configure amp');
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $dialog = $this->getHelperSet()->get('dialog');

    $output->writeln("<info>Welcome! amp will help setup your PHP applications.</info>");

    $output->writeln("");
    $output->writeln("<info>Configure MySQL</info>");
    $this->config->setParameter('mysql_type', 'dsn'); // temporary limitation
    $this->askMysqlDsn()->execute($input, $output, $dialog);

    //$output->writeln("");
    //$output->writeln("<info>Configure HTTPD</info>");
    //$this->askHttpdType()->execute($input, $output, $dialog);

    $this->config->save();
  }

  protected function askMysqlDsn() {
    return $this->createPrompt('mysql_dsn')
      ->setAsk(
      function ($default, InputInterface $input, OutputInterface $output, DialogHelper $dialog) {
        $value = $dialog->askAndValidate(
          $output,
          "> ",
          function ($dsn) {
            return static::validateDsn($dsn);
          },
          FALSE,
          $default
        );
        return (empty($value)) ? FALSE : $value;
      }
    );
  }

  protected function askHttpdType() {
    return $this->createPrompt('httpd_type')
      ->setAsk(
      function ($default, InputInterface $input, OutputInterface $output, DialogHelper $dialog) {
        return $dialog->select($output,
          "",
          array('apache' => 'Apache 2', 'nginx' => 'nginx'),
          $default
        );
      }
    );
  }

  /**
   * @param string $dsn
   * @return mixed $dsn (if valid)
   * @throws \RuntimeException (if $dsn is malformed or fails to connect)
   */
  protected static function validateDsn($dsn) {
    if (empty($dsn)) {
      throw new \RuntimeException("Value is required");
    }
    $datasource = new \Amp\Database\Datasource(array(
      'civi_dsn' => $dsn,
    ));
    if ($datasource->isValid()) {
      return $dsn;
    }
    else {
      throw new \RuntimeException("Connection failed");
    }
  }

  protected function createPrompt($parameter) {
    $prompt = new \Amp\ConfigPrompt($this->getContainer(), $this->config, $parameter);
    return $prompt;
  }
}