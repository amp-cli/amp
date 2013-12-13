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

require_once "<?php echo $autoloader ?>";
$datasource = new \Amp\Database\Datasource(array(
  'civi_dsn' => $_POST['dsn']
));

try {
  $dbh = $datasource->createPDO();
  foreach ($dbh->query('SELECT 99 as value') as $row) {
    if ($row['value'] == 99) {
      echo "OK";
    } else {
      echo "Error: Bad query result";
    }
  }
  $dbh = NULL;
} catch (PDOException $e) {
  print "Error: " . $e->getMessage() . "<br/>";
  die();
}