## About ##

"amp" is a tool to facilitate development of PHP web applications. The
general goal is that one may download the code for a PHP web application and
then call "amp" to provision a local database and local website.  For
example:

```
me@localhost:~/src$ git clone http://git.example.com/my-application
me@localhost:~/src$ cd my-application
me@localhost:~/src/my-application$ amp create --url=http://localhost:8003
AMP_URL='http://localhost:8003'
AMP_ROOT='/homes/me/src/my-application'
AMP_DB_DSN='mysql://myapplicat_vfs6h:iY93i1DQX2MgCux0@localhost:/myapplicat_vfs6h?new_link=true'
AMP_DB_USER='myapplicat_vfs6h'
AMP_DB_PASS='ib93i1DQXcMgCtx0'
AMP_DB_HOST='localhost'
AMP_DB_PORT=''
AMP_DB_NAME='myapplicat_vfs6h'
```

The "amp create" command will connect to your local mysqld and httpd (Apache
or nginx), setup a new database and virtual-host (named "localhost:8003"),
then output details.

Final thoughts:

 * "amp" IS NOT a complete stack with bundled binaries (for PHP, MySQL, etc).
 * "amp" IS NOT a cluster-management tool for remote servers.
 * "amp" IS NOT a one-click installer.
 * "amp" IS NOT a system-administration suite.
 * "amp" IS AN INTERFACE to the *AMP stack -- it aims to help application developers
   write their own install scripts.
 * "amp" aims to be PORTABLE -- to work with common PHP development environments
   such as Debian/Ubuntu, MAMP, XAMPP, or MacPorts.
 * "amp" is designed for DEVELOPMENT AND TESTING. If you need to automatically install
   copies of applications from source-code in a variety of environments (for
   integration-tests, demos, bug-fixing, training, collaboration, etc), then "amp"
   can help.
 
## Example ##

For example, the "my-application" (from above) may require a few setup steps:

 * Create a virtual host pointing to the "my-application/web" directory
 * Create a database
 * Fill the database with some default tables (using "sql/install.sql")
 * Create a config file ("conf/my-application.ini") so that PHP can
   connect to the database

As the author of "my-application", one might include a script "bin/install.sh"

```
#!/bin/bash
set -e
eval $(amp create --root="$PWD/web" "$@")
cat $PWD/sql/install.sql | mysql -u$AMP_DB_USER -p$AMP_DB_PASS $AMP_DB_NAME
cat > $PWD/conf/my-application.ini <<MYCONFIG
[mysql]
username=${AMP_DB_USER}
password=${AMP_DB_PASS}
database=${AMP_DB_NAME}
hostname=${AMP_DB_HOST}
MYCONFIG
```

## FAQ ##

Q: How do I configure "amp" to work on my system?

A1: Run "amp config:set --mysql_dsn=mysql://username:password@hostname"
(and optionally add a port)

A2: For Apache, add directive "Include /home/myuser/.amp/apache.d/*.conf".
For nginx, add directive "Include /home/myuser/.amp/nginx.d/*.conf"

Q: How do I know if "amp" is working?

A: Run "amp test"

Q: How does "amp" assign a virtual hostname and port?

A: You can specify one by passing the "--url" option. If omitted, it will
use "localhost" and assign an alternative port.

Q: How does "amp" name databases and database users?

A: The name is computed by taking the directory name (eg "my-application")
and appending some random characters.  The directory name may be truncated
to meet MySQL's size limits.  The name is the same for the DB and user.

Q: Where does "amp" store its configuration data?

A: ~/.amp

Q: I have five web apps installed. How does AMP distinguish them?

A: Each application should have its own directory (eg
"/home/me/src/my-application-1").  By default, "amp" assumes that each
directory corresponds to a single virtual-host and a single MySQL database.
If you need an additional virtual-host and DB for that application, call
"create" again with the "--name" argument.  If you want an additional
virtual-host XOR DB, specify "--no-db" or "--no-url".

Q: How do I build a stand-alone PHAR executable for amp?

A: Install [Box](http://box-project.org/). Then, in the amp source dir, run "php -d phar.readonly=0 `which box` build"

## Internal Architecture ##

"amp" uses components from Symfony 2 (eg Console, Config, and
Dependency-Injection).

There are three key services defined in the container:

 * mysql -- A service for creating and destroying MySQL DB's
   (based on DatabaseManagementInterface)
 * httpd -- A service for creating and destroying HTTP virtual-hosts
   (based on HttpdInterface)
 * instances -- A repository for CRUD'ing web-app instances (using the
   "mysql" and "httpd" services) which stores metadata in YAML
   (~/.app/instances.yml).

There may be competing implementations of "mysql" and "httpd" -- eg one
implementation might connect to a remote mysqld while another launches a
local mysqld on a ramdisk.  These can be chosen at runtime by calling "amp
config:set --mysql_type=XXX" or "amp config:set --httpd_type=XXX"

Parameters and services may be configured in amp's source-tree
("app/defaults/services.yml") or in the local home directory
("~/.amp/services.yml").

## Planned Features ##

 * Add DatabaseManagementInterface for launching mysqld (in ramdisk)
 * Add HttpdInterface for nginx
 * Set permissions for web-writable files (ACL and/or chmod)
 * Callback support

## Wishlist / Patch-Welcomes ##

 * Add DatabaseManagementInterface based on MySQL CLI
 * For "amp export" and "amp create", add option "--format=shell,json,yml"
 * Guided configuration and testing (eg "amp config -i")
 * Register new virtual-hosts in /etc/hosts
 * Automatically restart Apache/nginx after creating or changing virtual-hosts
 * Add support for launching PHP's built-in webserver
 * Add more heuristics/settings to work well in common dev environments
   (Debian/Ubuntu, MAMP, XAMPP, MacPorts, etc)
