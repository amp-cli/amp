<?php
namespace Amp\Database\MySQLRAMServer;

use Amp\Util\PortChecker;
use ProcessHelper\ProcessHelper as PH;
use Symfony\Component\Process\Process;

/**
 * @group mysqld
 */
class AutolaunchTest extends \PHPUnit\Framework\TestCase {

  const MYSQLD_URL = 'mysql://127.0.0.1:3307';

  protected function setUp(): void {
    $this->assertMatchesRegularExpression(';tmp/amphome-phpunit;', getenv('AMPHOME'), 'This test must run in a test environment. Use tempamp.');
    parent::setUp();
  }

  public function tearDown(): void {
    PH::runOk('killall mysqld');
    static::removeDir(static::getAmpHome());
  }

  /**
   * Start mysqld, connect to it, and stop it.
   */
  public function testStartStop(): void {
    $debug = function () {
      if (getenv('DEBUG')) {
        call_user_func_array('printf', func_get_args());
        ob_flush();
      }
    };
    $debug("\n\nmysqld is [%s]\namp bin is [%s]\namp home is [%s]\n", trim(shell_exec('which mysqld')), trim(shell_exec('which amp')), self::getAmpHome());

    $this->assertFileExists(trim(shell_exec('which mysqld')));
    $this->assertNotRunning(self::MYSQLD_URL, "The TCP service (" . self::MYSQLD_URL . ") is already in use. Test cannot proceed.");

    // PART 1: Launch mysqld

    // FIXME: mysql:start should spawn in background. This seems to work in most contexts,
    // but when running in php70+phpunit, the child process stays around until mysqld terminates.
    // So we'll just let it stay in the background...
    $start = Process::fromShellCommandline('amp mysql:start');
    $start->setTimeout(NULL);
    $start->start(function ($type, $buffer) use ($debug) {
      $debug("MYSQLD($type): $buffer");
    });

    // PART 2: Verify mysqld is running

    $this->await(45, function () {
      printf("U");
      ob_flush();
      $pc = new PortChecker();
      return $pc->checkUrl(self::MYSQLD_URL);
    });
    $debug("mysqld is listening for TCP requests\n");
    $this->await(45, function () {
      printf("A");
      ob_flush();
      $a = PH::run(['echo @SQL | amp sql -a', 'SQL' => 'SELECT "abcd1234"']);
      // PH::dump($a);
      return $a->isSuccessful() && (bool) preg_match(';abcd1234;', $a->getOutput());
    });
    $debug("mysqld is online\n");

    // PART 3: Create a test db

    $debug("making a test database\n");
    PH::runOk(['amp create -Ne2e --skip-url']);
    PH::runOk(['echo @SQL | amp sql -Ne2e', 'SQL' => 'CREATE TABLE mytable (id int)']);
    PH::runOk(['echo @SQL | amp sql -Ne2e', 'SQL' => 'INSERT INTO mytable VALUES (1234)']);

    // PART 4: Cleanup

    $debug("mysqld is shutting down\n");
    $start->stop(5);
    PH::runOk('amp mysql:stop');
    $this->await(45, function () {
      printf("S");
      ob_flush();
      $pc = new PortChecker();
      return !$pc->checkUrl(self::MYSQLD_URL);
    });
  }

  public static function removeDir($dir) {
    if ($dir && file_exists($dir) && preg_match(';[^\./];', $dir)) {
      PH::runOk(['rm -rf @DIR', 'DIR' => $dir]);
    }
  }

  /**
   * @param string|NULL $suffix
   * @return string
   *   Full path to the `amp` source tree.
   */
  public static function getPrjDir($suffix = NULL): string {
    $base = dirname(dirname(dirname(dirname(__DIR__))));
    if ($suffix) {
      return "$base/$suffix";
    }
    else {
      return $base;
    }
  }

  public static function getAmpHome(): string {
    return self::getPrjDir('tmp/amphome-phpunit');
  }

  public function assertNotRunning($url, $message = ''): void {
    $pc = new PortChecker();
    $this->assertFalse($pc->checkUrl($url), $message);
  }

  public function assertRunning($url, $message = ''): void {
    $pc = new PortChecker();
    $this->assertTrue($pc->checkUrl($url), $message);
  }

  public function await($maxDuration, $callback) {
    $endTime = microtime(1) + $maxDuration;
    while (microtime(1) < $endTime) {
      if ($callback()) {
        return TRUE;
      }
      // usleep(1000 * rand(0, 500));
      usleep(1000 * 1000);
    }
    throw new \Exception("Failed waiting callback to complete successfully");
  }

}
