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

</VirtualHost>
