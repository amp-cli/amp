<?php
namespace Amp\Command;

use Amp\ConfigRepository;
use Amp\Util\User;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

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
    $helper = $this->getHelper('question');

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
    $output->writeln("<info>===========================[ Configure Database ]===========================</info>");
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


    $this->askDbType()->execute($input, $output, $helper);
    $db_type = $this->getContainer()->getParameter('db_type');
    if (in_array($db_type, array('mysql_dsn', 'pg_dsn'))) {
      $this->askDbDsn()->execute($input, $output, $helper);
    }

    $output->writeln("");
    $output->writeln("<info>=======================[ Configure File Permissions ]=======================</info>");
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
    $this->askPermType()->execute($input, $output, $helper);
    switch ($this->config->getParameter("perm_type")) {
      case 'linuxAcl':
      case 'osxAcl':
        $this->askPermUser()->execute($input, $output, $helper);
        break;

      case 'custom':
        $this->askPermCommand($output)->execute($input, $output, $helper);
        break;

      default:
        break;
    }

    $output->writeln("");
    $output->writeln("<info>==========================[ Configure Hostnames ]===========================</info>");
    $output->writeln("");
    $output->writeln("<info>"
      . "When defining a new vhost (e.g. \"http://example-1.localhost\"), the hostname must\n"
      . "be mapped to an IP address.\n"
      . "\n"
      . "amp can attempt to register hostnames automatically in the /etc/hosts file.\n"
      . "\n"
      . "However, if you use wildcard DNS, dnsmasq, or manually manage hostnames, then\n"
      . "this feature can be disabled.\n"
      . "</info>"
    );

    $this->askHostsType(
      $this->getContainer()->getParameter('hosts_file'),
      $this->getContainer()->getParameter('hosts_ip')
    )->execute($input, $output, $helper);

    $output->writeln("");
    $output->writeln("<info>=============================[ Configure HTTPD ]============================</info>");
    $this->askHttpdType()->execute($input, $output, $helper);
    $this->askHttpdVisibility()->execute($input, $output, $helper);

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
        $this->askHttpdRestartCommand()->execute($input, $output, $helper);
        break;

      default:

    }

    $output->writeln("");
    $output->writeln("<info>----------------------------------------------------------------------------</info>");
    $output->writeln("");
    $output->writeln("<info>For new virtual-hosts, amp will create vhost files, and the webserver\n" .
      "configuration will need to include those files.</info>");
    $output->writeln("");

    switch ($this->config->getParameter('httpd_type')) {
      case 'apache':
      case 'apache24':
        $include = ($this->config->getParameter('httpd_type') === 'apache24')
          ? 'IncludeOptional'
          : 'Include';
        $configPath = $this->getContainer()->getParameter('apache_dir');
        $configFiles = $this->findApacheConfigFiles();
        if ($configFiles) {
          $output->writeln("<info>The location of the webserver configuration varies. Based on\n" .
            "examining your system, it is probably ONE of these files:</info>");
          $output->writeln("");
          foreach ($configFiles as $configFile) {
            $output->writeln("  <comment>$configFile</comment>");
          }
        }
        else {
          $output->writeln("<info>Find the Apache configuration file (eg <comment>apache.conf</comment> or <comment>httpd.conf</comment>).</info>");
        }

        $output->writeln("");
        $output->writeln("<info>You will need to include this line in the Apache configuration file:</info>");
        $output->writeln("");
        $output->writeln("  <comment>$include {$configPath}/*.conf</comment>");
        $output->writeln("");
        break;

      case 'nginx':
        $configPath = $this->getContainer()->getParameter('nginx_dir');
        $configFiles = $this->findNginxConfigFiles();
        if ($configFiles) {
          $output->writeln("<info>The location of the HTTP configuration could not be determined automatically.</info>");
          $output->writeln("");
          foreach ($configFiles as $configFile) {
            $output->writeln("  <comment>$configFile</comment>");
          }
        }
        else {
          $output->writeln("<info>Find the nginx configuration file (eg <comment>nginx.conf</comment>).</info>");
          $output->writeln("");
        }
        $output->writeln("<info>You will need to include this line in the nginx configuration file:</info>");
        $output->writeln("");
        $output->writeln("  <comment>include {$configPath}/*.conf;</comment>");
        $output->writeln("");
        $output->writeln("You will need to restart nginx after adding the directive -- and again");
        $output->writeln("after creating any new sites.");
        $output->writeln("");
        break;

      default:
    }

    $output->writeln("<info>Press <comment>ENTER</comment> once you have added or verified the line.</info>");
    $output->writeln("");
    $helper->ask($input, $output, new Question(''));

    $output->writeln("<info>==================================[ Test ]==================================</info>");
    $output->writeln("");
    $output->writeln("To ensure that amp is correctly configured, you may create a test site by running:");
    $output->writeln("");
    $output->writeln("  <comment>amp test</comment>");
    // FIXME: auto-detect "amp" vs "./bin/amp" vs "./amp"

    $this->config->save();
  }

  protected function askDbDsn() {
    $db_type = $this->getContainer()->getParameter('db_type');
    $q = new Question('Enter dsn> ', $this->getContainer()->getParameter($db_type));
    $q->setValidator([__CLASS__, 'validateDsn']);
    return $this->createPrompt($db_type)->setAsk($q);
  }

  protected function askDbType() {
    return $this->createPrompt('db_type')
      ->setAsk(
        new ChoiceQuestion(
          'Select db_type> ',
          [
            'mysql_dsn' => 'MySQL: Specify administrative credentials (DSN)',
            'mysql_mycnf' => 'MySQL: Read user+password+host+port from $HOME/.my.cnf (experimental)',
            'mysql_ram_disk' => 'MySQL: Launch new DB in a ramdisk (Linux/OSX)',
            'pg_dsn' => 'PostgreSQL: Specify administrative credentials (DSN)',
          ],
          $this->getContainer()->getParameter('db_type')
        )
      );
  }

  protected function askPermType() {
    $q = new ChoiceQuestion('Select file permission mode> ', [
      'none' => "\"none\": Do not set any special permissions for the web user",
      'linuxAcl' => "\"linuxAcl\": Set tight, inheritable permissions with Linux ACLs [setfacl] (recommended)\n"
      . "         In some distros+filesystems, this requires extra configuration.\n"
      . "         eg For Debian-based distros: https://help.ubuntu.com/community/FilePermissionsACLs",
      'osxAcl' => '"osxAcl": Set tight, inheritable permissions with OS X ACLs [chmod +a] (recommended)',
      'custom' => '"custom": Set permissions with a custom command',
      'worldWritable' => '"worldWritable": Set loose, generic permissions [chmod 1777] (discouraged)',
    ], $this->getContainer()->getParameter('perm_type'));
    return $this->createPrompt('perm_type')->setAsk($q);
  }

  protected function askPermUser() {
    $q = new Question('Enter perm_user> ', $this->getContainer()->getParameter('perm_user'));
    $q->setValidator([User::class, 'validateUser']);
    return $this->createPrompt('perm_user')->setAsk($q);
  }

  protected function askPermCommand(OutputInterface $output) {
    $q = new Question('Enter perm_custom_command> ', $this->getContainer()->getParameter('perm_user'));
    $q->setValidator(function ($command) use ($output) {
      $testDir = $this->getContainer()->getParameter('app_dir')
        . DIRECTORY_SEPARATOR . 'tmp'
        . DIRECTORY_SEPARATOR . \Amp\Util\StringUtil::createRandom(16);
      $output->writeln("<info>Executing against test directory ($testDir)</info>");
      $result = \Amp\Permission\External::validateDirCommand($testDir, $command);
      $output->writeln("<info>OK (Executed without error)</info>");
      return $result;
    });
    return $this->createPrompt('perm_custom_command')->setAsk($q);
  }

  protected function askHostsType($file, $ip) {
    $q = new ChoiceQuestion('Select hosts_type> ', [
      'file' => "File-based hosts. Automatically add records to \"$file\" using IP ($ip) and sudo.",
      'none' => 'None. Manually configure hostnames with your own tool.',
    ], $this->getContainer()->getParameter('hosts_type'));
    return $this->createPrompt('hosts_type')->setAsk($q);
  }

  protected function askHttpdType() {
    $q = new ChoiceQuestion('Select httpd_type> ', [
      'apache' => 'Apache 2.3 or earlier',
      'apache24' => 'Apache 2.4 or later',
      'nginx' => 'nginx (WIP)',
      'none' => 'None (Note: You must configure any vhosts manually.)',
    ], $this->getContainer()->getParameter('httpd_type'));
    return $this->createPrompt('httpd_type')->setAsk($q);
  }

  protected function askHttpdVisibility() {
    $q = new ChoiceQuestion('Select httpd_visibility> ', [
      'local' => "Virtual hosts should bind to localhost.\n" .
        '         Recommended to avoid exposing local development instances.',
      'all' => "Virtual hosts should bind to all available IP addresses.\n" .
        '         <comment>Note</comment>: Instances will be publicly accessible over the network.',
    ], $this->getContainer()->getParameter('httpd_visibility'));
    return $this->createPrompt('httpd_visibility')->setAsk($q);
  }

  protected function askHttpdRestartCommand() {
    $q = new Question('Enter httpd_restart_command> ', $this->getContainer()->getParameter('httpd_restart_command'));
    return $this->createPrompt('httpd_restart_command')->setAsk($q);
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
    $candidates[] = '/Applications/*/apache2/conf/httpd.conf'; // Bitnami OSX
    $candidates[] = '/usr/local/etc/apache2x/httpd.conf'; // FreeBSD (Googled, untested)
    $candidates[] = '/usr/local/etc/apache22/httpd.conf'; // FreeBSD (Googled, untested)

    $matches = array();
    foreach ($candidates as $candidate) {
      $files = (array) glob($candidate);
      foreach ($files as $file) {
        $matches[] = $file;
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
