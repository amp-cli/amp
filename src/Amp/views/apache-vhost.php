<?php
/* Apache Virtual Host Template
 *
 * @var string $root - the local path to the web root
 * @var string $host - the hostname to listen for
 * @var int $port - the port to listen for
 * @var string $include_vhost_file - the local path to a related config file
 */
?>

Listen <?php echo $port ?>

NameVirtualHost *:<?php echo $port ?>

<VirtualHost *:<?php echo $port ?>>
    ServerAdmin webmaster@<?php echo $host ?>

    DocumentRoot "<?php echo $root ?>"

    ServerName <?php echo $host ?>

    ErrorLog "logs/<?php echo $host ?>-<?php echo $port ?>.error_log"

    CustomLog "logs/<?php echo $host ?>-<?php echo $port ?>.access_log" common

    <Directory "<?php echo $root ?>">
        Options All
        AllowOverride All
        Order allow,deny
        Allow from all
    </Directory>

    <?php if (!empty($include_vhost_file)) { ?>
    Include <?php echo $include_vhost_file ?>
    <?php } ?>

</VirtualHost>
