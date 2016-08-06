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
   * @param ConfigRepository $config
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
    $output->writeln("<info>=============================[ Configure Database ]=============================</info>");
    $output->writeln("");
    $output->writeln("<info>"
      . "Amp creates a unique database user for each generated instance.\n"
      . "To accomplish this amp needs GRANT-level privileges. It is\n"
      . "recommended that you supply the root/administrator credentials\n"
      . "for this task. If you wish to create a new user for amp to use\n"
      . "please assign it appropriate privileges eg:\n\n"
      // FIXME
      . "MySQL: <fg=cyan;bg=black;option=bold>GRANT ALL ON *.* to '#user'@'localhost' IDENTIFIED BY '#pass' WITH GRANT OPTION</fg=cyan;bg=black;option=bold>\n"
      . "PgSQL: <fg=cyan;bg=black;option=bold>\$ createuser --superuser --createdb --createrole -P #user</fg=cyan;bg=black;option=bold>\n"
      . "       <fg=cyan;bg=black;option=bold>Add 'local all #user md5' to pg_hba.conf</fg=cyan;bg=black;option=bold>\n"
      . "       <fg=cyan;bg=black;option=bold>Test $ psql -U #user -W template1</fg=cyan;bg=black;option=bold>"
      . "</info>"
    );

    $this->askDbType()->execute($input, $output, $dialog);
    $db_type = $this->getContainer()->getParameter('db_type');
    if (in_array($db_type, array('mysql_dsn', 'pg_dsn'))) {
      $this->askDbDsn()->execute($input, $output, $dialog);
    }

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
    $output->writeln("<info>=============================[ Configure Hostnames ]=============================</info>");
    $output->writeln("<info>"
      . "When defining a new vhost (e.g. \"http://example-1.localhost\"), the hostname must\n"
      . "be mapped to an IP address.\n"
      . "\n"
      . "amp can attempt to register hostnames automatically in the /etc/hosts file.\n"
      . "However, if you use wildcard DNS, dnsmasq, or manually manage hostnames, then\n"
      . "this feature can be disabled.\n"
      . "</info>"
    );

    $this->askHostsType(
      $this->getContainer()->getParameter('hosts_file'),
      $this->getContainer()->getParameter('hosts_ip')
    )->execute($input, $output, $dialog);

    $output->writeln("");
    $output->writeln("<info>=============================[ Configure HTTPD ]=============================</info>");
    $this->askHttpdType()->execute($input, $output, $dialog);

    switch ($this->config->getParameter('httpd_type')) {
      case 'apache':
      case 'apache24':
      case 'nginx':
        $output->writeln("<info>\n"
          . "Whenever you create a new vhost, you may need to restart the web server.\n"
          . "Amp can do this automatically if you specify a command.\n"
          . "\n"
          . "NOTE: Commands based on `sudo` may require you to enter a password periodically.\n"
          . "</info>"
        );
        $this->askHttpdRestartCommand()->execute($input, $output, $dialog);
        break;

      default:

    }

    switch ($this->config->getParameter('httpd_type')) {
      case 'apache':
      case 'apache24':
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
        $output->writeln("  <comment>include {$configPath}/*.conf</comment>");
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

  protected function askDbDsn() {
    $db_type = $this->getContainer()->getParameter('db_type');
    return $this->createPrompt($db_type)
      ->setAsk(
        function ($default, InputInterface $input, OutputInterface $output, DialogHelper $dialog) {
          $value = $dialog->askAndValidate(
            $output,
            "Enter dsn> ",
            function ($dsn) {
              return ConfigCommand::validateDsn($dsn);
            },
            FALSE,
            $default
          );
          return (empty($value)) ? FALSE : $value;
        }
      );
  }


  protected function askDbType() {
    return $this->createPrompt('db_type')
      ->setAsk(
        function ($default, InputInterface $input, OutputInterface $output, DialogHelper $dialog) {
          return $dialog->select($output,
            "Enter db_type",
            array(
              'mysql_dsn' => 'MySQL: Specify administrative credentials (DSN)',
              'mysql_mycnf' => 'MySQL: Read user+password+host+port from $HOME/.my.cnf (experimental)',
              'mysql_ram_disk' => 'MySQL: Launch new DB in a ramdisk (Linux/OSX)',
              'pg_dsn' => 'PostgreSQL: Specify administrative credentials (DSN)',
            ),
            $default
          );
        }
      );
  }

  protected function askPermType() {
    return $this->createPrompt('perm_type')
      ->setAsk(
        function ($default, InputInterface $input, OutputInterface $output, DialogHelper $dialog) {
          $options = array(
            'none' => "\"none\": Do not set any special permissions for the web user",
            'linuxAcl' => "\"linuxAcl\": Set tight, inheritable permissions with Linux ACLs [setfacl] (recommended)\n"
              . "         In some distros+filesystems, this requires extra configuration.\n"
              . "         eg For Debian-based distros: https://help.ubuntu.com/community/FilePermissionsACLs",
            'osxAcl' => '"osxAcl": Set tight, inheritable permissions with OS X ACLs [chmod +a] (recommended)',
            'custom' => '"custom": Set permissions with a custom command',
            'worldWritable' => '"worldWritable": Set loose, generic permissions [chmod 1777] (discouraged)',
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
              $testDir = $this->getContainer()->getParameter('app_dir')
                . DIRECTORY_SEPARATOR . 'tmp'
                . DIRECTORY_SEPARATOR . \Amp\Util\StringUtil::createRandom(16);
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

  protected function askHostsType($file, $ip) {
    return $this->createPrompt('hosts_type')
      ->setAsk(
        function ($default, InputInterface $input, OutputInterface $output, DialogHelper $dialog) use ($file, $ip) {
          return $dialog->select($output,
            "Enter hosts_type",
            array(
              'file' => "File-based hosts. Automatically add records to \"$file\" using IP ($ip) and sudo.",
              'none' => 'None. Manually configure hostnames with your own tool.',
            ),
            $default
          );
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
              'apache' => 'Apache 2.3 or earlier',
              'apache24' => 'Apache 2.4 or later',
              'nginx' => 'nginx (WIP)',
              'none' => 'None (Note: You must configure any vhosts manually.)',
            ),
            $default
          );
        }
      );
  }

  protected function askHttpdRestartCommand() {
    return $this->createPrompt('httpd_restart_command')
      ->setAsk(
        function ($default, InputInterface $input, OutputInterface $output, DialogHelper $dialog) {
          $value = $dialog->askAndValidate(
            $output,
            "Enter httpd_restart_command> ",
            function ($command) use ($output) {
              return $command;
            },
            FALSE,
            $default
          );
          return (empty($value)) ? FALSE : $value;
        }
      );
  }


  /**
   * @param string $dsn
   * @return mixed $dsn (if valid)
   * @throws \RuntimeException (if $dsn is malformed or fails to connect)
   */
  public static function validateDsn($dsn) {
    if (empty($dsn)) {
      throw new \RuntimeException("Value is required");
    }
    $datasource = new \Amp\Database\Datasource(array(
      'civi_dsn' => $dsn,
    ));
    $dbh = $datasource->createPDO();
    foreach ($dbh->query('SELECT 99 as value') as $row) {
      if ($row['value'] == 99) {
        return $dsn;
      }
    }
    throw new \RuntimeException("Connection failed");
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
