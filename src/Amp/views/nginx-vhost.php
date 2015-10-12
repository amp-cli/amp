<?php
/* nginx Virtual Host Template
 *
 * @var string $root - the local path to the web root
 * @var string $host - the hostname to listen for
 * @var int $port - the port to listen for
 * @var string $include_vhost_file - the local path to a related config file
 */
?>
server {
  server_name <?php echo $host; ?>;
  listen <?php echo $port ?>;
  root <?php echo $root; ?>;

  <?php if (!empty($include_vhost_file)) { ?>

  include <?php echo $include_vhost_file ?>;

  <?php } else { ?>

  location / {
    try_files $uri @rewrite;
  }

  location @rewrite {
    # Some modules enforce no slash (/) at the end of the URL
    # Else this rewrite block wouldn't be needed (GlobalRedirect)
    rewrite ^/(.*)$ /index.php?q=$1;
  }

  location ~ \..*/.*\.php$ {
    return 403;
  }

  location ~* (\.php~|\.php.bak|\.php.orig)$ {
    deny all;
  }

  location ~ \.php$ {
    fastcgi_split_path_info ^(.+\.php)(/.+)$;
    #NOTE: You should have "cgi.fix_pathinfo = 0;" in php.ini
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_pass unix:/var/run/php5-fpm.sock;
    # fastcgi_pass 127.0.0.1:9000;
    fastcgi_intercept_errors on;
    fastcgi_read_timeout 60;
  }

  location /sites/default/files/civicrm {
    deny all;
  }

  location /wp-content/plugins/files/civicrm/upload {
    deny all;
  }
  location /wp-content/plugins/files/civicrm/custom {
    deny all;
  }

  <?php } ?>

}
