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

    $output->writeln("<info>"
        . "Welcome! amp will help setup your PHP applications by creating\n"
        . "databases and virtual-hosts. amp is intended for use during\n"
        . "development and testing."
        . "</info>"
    );
    $output->writeln("");

    $output->writeln("");
    $output->writeln("<info>=============================[ Configure MySQL ]=============================</info>");
    $this->config->setParameter('mysql_type', 'dsn'); // temporary limitation
    $this->askMysqlDsn()->execute($input, $output, $dialog);

    $output->writeln("");
    $output->writeln("<info>=============================[ Configure HTTPD ]=============================</info>");
    $this->askHttpdType()->execute($input, $output, $dialog);
    switch ($this->config->getParameter('httpd_type')) {
      case 'apache':
        $configPath = $this->getContainer()->getParameter('apache_dir');
        $output->writeln("");
        $output->writeln("<comment>Note</comment>: Please ensure that httpd.conf or apache2.conf includes this directive:");
        $output->writeln("");
        $output->writeln("  <comment>Include {$configPath}/*.conf</comment>");
        $configFiles = $this->findApacheConfigFiles();
        if ($configFiles) {
          $output->writeln("");
          $output->writeln("The location of httpd.conf varies, but it may be:");
          $output->writeln("");
          foreach ($configFiles as $configFile) {
            $output->writeln("  <comment>$configFile</comment>");
          }
        }
        $output->writeln("");
        $output->writeln("You will need to restart Apache after adding the directive -- and again");
        $output->writeln("after creating any new sites.");
        break;
      case 'nginx':
        $configPath = $this->getContainer()->getParameter('nginx_dir');
        $output->writeln("");
        $output->writeln("<comment>Note</comment>: Please ensure that nginx.conf includes this directive:");
        $output->writeln("");
        $output->writeln("  <comment>Include {$configPath}/*.conf</comment>");
        $configFiles = $this->findNginxConfigFiles();
        if ($configFiles) {
          $output->writeln("");
          $output->writeln("The location of nginx.conf varies, but it may be:");
          $output->writeln("");
          foreach ($configFiles as $configFile) {
            $output->writeln("  <comment>$configFile</comment>");
          }
        }
        $output->writeln("");
        $output->writeln("You will need to restart nginx after adding the directive -- and again");
        $output->writeln("after creating any new sites.");
        break;
      default:
    }

    $output->writeln("");
    $output->writeln("<info>===================================[ Test ]==================================</info>");
    $output->writeln("");
    $output->writeln("To ensure that amp is correctly configured, you may create a test site by running:");
    $output->writeln("");
    $output->writeln("  <comment>amp test</comment>");

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
          array(
            'apache' => 'Apache 2',
            'nginx' => 'nginx'
          ),
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

  protected function findApacheConfigFiles() {
    $candidates = array();
    $candidates[] = '/etc/apache2/apache2.conf'; // Debian
    $candidates[] = '/etc/apache2/conf.d'; // Debian
    $candidates[] = '/etc/apache2/httpd.conf'; // OS X
    $candidates[] = '/etc/httpd/conf/httpd.conf'; // RedHat (Googled, untested)
    $candidates[] = '/opt/local/apache2/conf/httpd.conf'; // MacPorts (Googled, untested)
    $candidates[] = '/Applications/MAMP/conf/apache/httpd.conf'; // MAMP
    $candidates[] = '/Applications/XAMPP/etc/httpd.conf'; // XAMPP OS X (Googled, untested)
    $candidates[] = '/usr/local/etc/apache2x/httpd.conf'; // FreeBSD (Googled, untested)
    $candidates[] = '/usr/local/etc/apache22/httpd.conf'; // FreeBSD (Googled, untested)

    $matches = array();
    foreach ($candidates as $candidate) {
      if (file_exists($candidate)) {
        $matches[] = $candidate;
      }
    }
    return $matches;
  }
  protected function findNginxConfigFiles() {
    $candidates = array();
    $candidates[] = '/etc/nginx/nginx.conf'; // Debian, RedHat
    $candidates[] = '/opt/local/etc/nginx/nginx.conf'; // MacPorts (Googled, untested)
    $candidates[] = '/usr/local/etc/nginx/nginx.conf '; // FreeBSD (Googled, untested)

    $matches = array();
    foreach ($candidates as $candidate) {
      if (file_exists($candidate)) {
        $matches[] = $candidate;
      }
    }
    return $matches;
  }

}