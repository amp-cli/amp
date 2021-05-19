<?php
namespace Amp\Database;

/**
 * @group unit
 */
class MySQLCnfFactoryTest extends \PHPUnit\Framework\TestCase {

  public function testCreateCandidateDatasources(): void {
    $factory = new MySQLCnfFactory(
      array(
        __DIR__ . '/MySQLCnfFactoryTest.ex1.cnf',
        __DIR__ . '/MySQLCnfFactoryTest.ex-invalid1.cnf',
        __DIR__ . '/MySQLCnfFactoryTest.ex2.cnf',
        __DIR__ . '/MySQLCnfFactoryTest.ex-invalid2.cnf',
      ),
      array('default-host-1', 'default-host-2'),
      array(123, 456)
    );
    $actualDatasources = $factory->createCandidateDatasources();
    $actualDSNs = array();
    foreach ($actualDatasources as $datasource) {
      /** @var Datasource $datasource */
      $actualDSNs[] = $datasource->toCiviDSN();
    }
    $expectedDSNs = array(
      'mysql://admin1:secret1@hostname1.com:12345/?new_link=true',
      'mysql://admin2a:secret2a@default-host-1:123/?new_link=true',
      'mysql://admin2a:secret2a@default-host-1:456/?new_link=true',
      'mysql://admin2a:secret2a@default-host-2:123/?new_link=true',
      'mysql://admin2a:secret2a@default-host-2:456/?new_link=true',
      'mysql://admin2b:secret2b@default-host-1:123/?new_link=true',
      'mysql://admin2b:secret2b@default-host-1:456/?new_link=true',
      'mysql://admin2b:secret2b@default-host-2:123/?new_link=true',
      'mysql://admin2b:secret2b@default-host-2:456/?new_link=true',
    );
    $this->assertEquals($expectedDSNs, $actualDSNs);
  }

  public function testCreateCandidateDatasources_none(): void {
    $factory = new MySQLCnfFactory(
      array(
        __DIR__ . '/MySQLCnfFactoryTest.ex-invalid1.cnf',
        __DIR__ . '/MySQLCnfFactoryTest.ex-invalid2.cnf',
      ),
      array('default-host-1', 'default-host-2'),
      array(123, 456)
    );
    $actualDatasources = $factory->createCandidateDatasources();
    $actualDSNs = array();
    foreach ($actualDatasources as $datasource) {
      /** @var Datasource $datasource */
      $actualDSNs[] = $datasource->toCiviDSN();
    }
    $expectedDSNs = array();
    $this->assertEquals($expectedDSNs, $actualDSNs);
  }

  /*
  public function dataPollPort(): array {
    $cases = array();
    $cases[] = array('echo -e "Variable_name\tValue\nport\t8889\n"', 8889);
    $cases[] = array('echo -e "Variable_name\tValue\nport\t3306\n"', 3306);
    return $cases;
  }

  public function dataPollPortErrors(): array {
    $cases = array();
    $cases[] = array('echo -e "Variable_name\tValue\ngarbage\t3306\n"');
    $cases[] = array('echo -e "Variable_name\tValue\n"');
    $cases[] = array('echo gunk"');
    $cases[] = array('read foo"');
    return $cases;
  }

  /**
   * @param string $command
   * @param int $expectedPort
   * @dataProvider dataPollPort
   *
  public function testpollPortViaCLI($command, $expectedPort): void {
    $actualPort = MySQLCnfFactory::pollPortViaCLI($command);
    $this->assertEquals($expectedPort, $actualPort);
  }

  /**
   * @param string $command
   * @param int $expectedPort
   * @dataProvider dataPollPortErrors
   * @expectedException \Amp\Exception\ProcessException
   *
  public function testpollPortViaCLIErrors($command): void {
    MySQLCnfFactory::pollPortViaCLI($command);
  }
  */
}
