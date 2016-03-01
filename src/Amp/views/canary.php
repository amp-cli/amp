<?php echo "<?php"; ?>

// This is a simple script which outputs "OK" if it
// executes correctly and connects properly to a database.

if (isset($_SERVER['HTTP_CLIENT_IP'])
  || isset($_SERVER['HTTP_X_FORWARDED_FOR'])
  || !in_array(@$_SERVER['REMOTE_ADDR'], array(
    '127.0.0.1',
    '::1',
  ))
) {
  header('HTTP/1.0 403 Forbidden');
  exit('Connection must originate on localhost');
}

$config = require 'config.php';

// ---------- Test HTTP inputs ----------

if ($_REQUEST['exampleData'] !== 'foozball') {
  echo "Error: Expected GET or POST value 'exampleData=foozball'";
}

// ---------- Test database ----------

try {
  $dbh = new \PDO($config['dsn'], $config['user'], $config['pass']);
  $dbh->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
  foreach ($dbh->query('SELECT 99 as value') as $row) {
    if ($row['value'] == 99) {
      // ok
    } else {
      echo "Error: Bad query result <br/>";
      die();
    }
  }
  $dbh = NULL;
} catch (PDOException $e) {
  echo "Error: " . $e->getMessage() . "<br/>";
  die();
}

// ---------- Test file permissions ----------

$dataFile = $config['dataDir'] . '/example.txt';
if (FALSE === file_put_contents($dataFile, "data")) {
  echo "Error: Failed to write $dataFile";
  die();
}
if (FALSE === unlink($dataFile)) {
  echo "Error: Failed to remove $dataFile";
  die();
}

// ---------- OK ----------

echo "<?= $expectedResponse ?>";
