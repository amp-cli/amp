
======================================================================

"amp" is a tool to facilitate development of LAMP-style applications;
it is used to register new applications with *AMP stack by creating
databases, creating example Apache config files, creating example
Nginx config files, etc. The precise configuration steps will depend
on the unique configuration of your machine.

"amp test" creates a web application for test purposes and requests
access to it. If you encounter errors while running the test or
otherwise creating new websites, consider these steps:

 * Configure administrative access to your MySQL server:

     amp config:set --db_type=mysql_dsn --mysql_dsn=mysql://user:pass@host/

 * If you use Apache, add this directive to your configuration:

     Include <?php echo $apache_dir ?>/*.conf

 * If you use Apache, restart Apache.

 * If you use nginx, add this directive to your configuration:

     Include <?php echo $nginx_dir ?>/*.conf

* If you use nginx, restart nginx.

======================================================================

