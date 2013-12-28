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
        . "development and testing.\n"
        . "\n"
        . "Please fill in a few configuration options so that we can properly\n"
        . "install the PHP application."
        . "</info>"
    );

    $output->writeln("");
    $output->writeln("<info>=============================[ Configure MySQL ]=============================</info>");
    $this->config->setParameter('mysql_type', 'dsn'); // temporary limitation
    $this->askMysqlDsn()->execute($input, $output, $dialog);

    $output->writeln("");
    $output->writeln("<info>=======================[ Configure File Permissions ]========================</info>");
    $output->writeln("");
    $currentUser = \Amp\Util\User::getCurrentUser();
    $output->writeln("<info>"
        . "It appears that you are currently working as user \"{$currentUser}\".\n"
        . "\n"
        . "If the web server executes PHP requests as the same user, then no special\n"
        . "permissions are required.\n"
        . "\n"
        . "If the web server executes PHP requests as a different user (such as\n"
        . "\"www-data\" or \"apache\"), then special permissions will be required\n"
        . "for any web-writable data directories."
        . "</info>"
    );
    $this->askPermType()->execute($input, $output, $dialog);
    switch ($this->config->getParameter("perm_type")) {
      case 'linuxAcl':
      case 'osxAcl':
        $this->askPermUser()->execute($input, $output, $dialog);
        break;

      case 'custom':
        $this->askPermCommand()->execute($input, $output, $dialog);
        break;

      default:
        break;
    }

    $output->writeln("");
    $output->writeln("<info>=============================[ Configure HTTPD ]=============================</info>");
    $this->askHttpdType()->execute($input, $output, $dialog);
    switch ($this->config->getParameter('httpd_type')) {
      case 'apache':
        $configPath = $this->getContainer()->getParameter('apache_dir');
        $output->writeln("");
        $output->writeln("<comment>Note</comment>: Please add this line to the httpd.conf or apache2.conf:");
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
    // FIXME: auto-detect "amp" vs "./bin/amp" vs "./amp"

    $this->config->save();
  }

  protected function askMysqlDsn() {
    return $this->createPrompt('mysql_dsn')
      ->setAsk(
      function ($default, InputInterface $input, OutputInterface $output, DialogHelper $dialog) {
        $value = $dialog->askAndValidate(
          $output,
          "Enter mysql_dsn> ",
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

  protected function askPermType() {
    return $this->createPrompt('perm_type')
      ->setAsk(
      function ($default, InputInterface $input, OutputInterface $output, DialogHelper $dialog) {
        $options = array(
          'none' => '"none": Do not set any special permissions for the web user',
          'worldWritable' => '"worldWritable": Set loose, generic permissions [chmod 1777]',
          'linuxAcl' => '"linuxAcl": Set tight, inheritable permissions with Linux ACLs [setfacl]',
          'osxAcl' => '"osxAcl": Set tight, inheritable permissions with OS X ACLs [chmod +a]',
          'custom' => '"custom": Set permissions with a custom command',
        );
        $optionKeys = array_keys($options);

        $defaultPos = array_search($default, $optionKeys);
        if ($defaultPos === FALSE) {
          $defaultPos = '0';
        }
        $selectedNum = $dialog->select($output,
          "Enter perm_type",
          array_values($options),
          $defaultPos
        );
        return $optionKeys[$selectedNum];
      }
    );
  }

  protected function askPermUser() {
    return $this->createPrompt('perm_user')
      ->setAsk(
      function ($default, InputInterface $input, OutputInterface $output, DialogHelper $dialog) {
        $value = $dialog->askAndValidate(
          $output,
          "Enter perm_user> ",
          function ($user) {
            return \Amp\Util\User::validateUser($user);
          },
          FALSE,
          $default
        );
        return (empty($value)) ? FALSE : $value;
      }
    );
  }

  protected function askPermCommand() {
    return $this->createPrompt('perm_custom_command')
      ->setAsk(
      function ($default, InputInterface $input, OutputInterface $output, DialogHelper $dialog) {
        $value = $dialog->askAndValidate(
          $output,
          "Enter perm_custom_command> ",
          function ($command) use ($output) {
            $testDir = $this->getContainer()->getParameter('app_dir') . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . \Amp\Util\String::createRandom(16);
            $output->writeln("<info>Executing against test directory ($testDir)</info>");
            $result = \Amp\Permission\External::validateDirCommand($testDir, $command);
            $output->writeln("<info>OK (Executed without error)</info>");
            return $result;
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
          "Enter httpd_type",
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