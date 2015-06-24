## About "amp": Vision ##

"amp" is a tool to facilitate development of PHP web applications. The goal is
to complement "composer" (and similar tools) by adding a (mostly) automated
step to setup the database and webserver for newly downloaded code. For example,
a developer checking out a project might say:

```
me@localhost:~/src$ composer create-project example/my-application --dev
me@localhost:~/src$ cd my-application
me@localhost:~/src/my-application$ ./bin/amp create --url=http://localhost:8003
URL: http://localhost:8003
Admin User: admin
Admin Password zFWx9D22
```

The "my-application" package depends on the "amp" package (using "require-dev" or
"suggest").  The "amp create" step creates a new database in the local mysqld and
a new virutal-host in the local httpd; then it writes out necessary credentials
(eg the mysql username and password) to a config file.

Additional thoughts:

 * "amp" IS NOT a complete stack with bundled binaries (for PHP, MySQL, etc).
 * "amp" IS NOT a cluster-management tool for remote servers.
 * "amp" IS NOT a one-click installer.
 * "amp" IS NOT a system-administration suite.
 * "amp" is primarily AN INTERFACE to the local *AMP stack -- it aims to help
   application developers write their own install scripts.
 * "amp" aims to be PORTABLE -- to work with common PHP development environments
   such as Debian/Ubuntu, MAMP, XAMPP, or MacPorts.
 * "amp" is designed for DEVELOPMENT AND TESTING. If you need to automatically install
   copies of applications from source-code in a variety of environments (for
   integration-tests, test-fixtures, demos, bug-fixing, training, collaboration, etc),
   then "amp" can help.

## About "amp": Pre-Alpha Example ##

At time of writing, "amp" is in-development and doesn't fully meet its vision.
In the third line, the developer shouldn't call "amp create" directly; rather,
the author of "my-application" should include an "install.sh" script, and
the downstream developer can run it:

```
me@localhost:~/src$ composer create-project example/my-application --dev
me@localhost:~/src$ cd my-application
me@localhost:~/src/my-application$ ./bin/amp config
me@localhost:~/src/my-application$ ./bin/install.sh
Login to the application:
 * URL: ${AMP_URL}
 * Username: admin
 * Password: default
```

The "amp config" command determines how to connect to MySQL and httpd.
It may scan the local system for common configurations (Ubuntu vs
MAMP vs MacPorts; Apache vs nginx), prompt the user for information, and
retain the info (in ~/.amp) for future use.

The "install.sh" is mostly specific to the application, but it builds
on "amp" to address the tedious bit about setting up mysqld and httpd.
For example, one might say:

```
#!/bin/bash
set -e
APPDIR=`pwd`

## Create a new database and virtual-host
eval $(amp create --root="$APPDIR/web")
amp datadir "$APPDIR/log" "$APPDIR/cache"

## Load DB
cat $APPDIR/sql/install.sql | mysql -u$AMP_DB_USER -p$AMP_DB_PASS $AMP_DB_NAME

## Create config file
cat > $APPDIR/conf/my-application.ini <<MYCONFIG
[mysql]
username=${AMP_DB_USER}
password=${AMP_DB_PASS}
database=${AMP_DB_NAME}
hostname=${AMP_DB_HOST}
MYCONFIG

echo "Login to the application:"
echo " * URL: ${AMP_URL}"
echo " * Username: admin"
echo " * Password: default"
```

## FAQ ##

Q: Is "amp" stable? Should I rely on it right now?

A: Probably not. "amp" is pre-alpha. Interfaces and workflows are likely to change.

Q: How do I configure "amp" to work on my system?

A: Run "amp config"

Q: How do I know if "amp" is working?

A: Run "amp test"

Q: How does "amp" assign a virtual hostname and port?

A: You can specify one by passing the "--url" option to "create". If omitted,
it will use "localhost" and assign an alternative port.

Q: How does "amp" name databases and database users?

A: The name is computed by taking the directory name (eg "my-application")
and appending some random characters.  The directory name may be truncated
to meet MySQL's size limits.  The name is the same for the DB and user.

Q: Where does "amp" store its configuration data?

A: ~/.amp by default or if you define the environment variable AMPHOME it will store in the directory thus defined.

Q: I have five web apps installed. How does AMP distinguish them?

A: Each application should have its own directory (eg
"/home/me/src/my-application-1").  By default, "amp" assumes that each
directory corresponds to a single virtual-host and a single MySQL database.
If you need an additional virtual-host and DB for that application, call
"create" again with the "--name" argument.  If you want an additional
virtual-host XOR DB, specify "--skip-db" or "--skip-url".

Q: How do I build a stand-alone PHAR executable for amp?

A: Install [Box](http://box-project.org/). Then, in the amp source dir, run "php -d phar.readonly=0 `which box` build"

## Internal Architecture ##

"amp" uses components from Symfony 2 (eg Console, Config, and
Dependency-Injection).

There are a few key services defined in the container:

 * mysql -- A service for creating and destroying MySQL DB's
   (based on DatabaseManagementInterface)
 * httpd -- A service for creating and destroying HTTP virtual-hosts
   (based on HttpdInterface)
 * perm -- A service for setting file permissions on data directories
   (based on PermissionInterface)
 * instances -- A repository for CRUD'ing web-app instances (using the
   "mysql" and "httpd" services) which stores metadata in YAML
   (~/.app/instances.yml).

There may be competing implementations of "mysql", "httpd", and "perm" -- eg
one implementation might connect to a remote mysqld while another launches a
local mysqld on a ramdisk.  These can be chosen at runtime by calling
commands like:

```
## Set options interactively
amp config

## Set options individually
amp config:set --httpd_type=XXX
amp config:set --mysql_type=XXX
amp config:set --perm_type=XXX

## Set options en masse
amp config:set --httpd_type=XXX --mysql_type=XXX --perm_type=XXX
```

Parameters and services may be configured in amp's source-tree
("app/defaults/services.yml") or in the local home directory
("~/.amp/services.yml"). Parameters entered through the CLI
("amp config", "amp config:set", etc) are stored in the local
home directory ("~/.amp/services.yml").

## Planned Features ##

 * Add DatabaseManagementInterface for launching mysqld (in ramdisk)
 * Add HttpdInterface for nginx
 * Add HttpdInterface for PHP's built-in web-server
 * Callback support (eg "amp create" calls a script bundled with my-application)
 * Load per-application config values (my-application/.amp.yml); eg:
   * Specify any callback(s)
   * Specify the Apache vhost template
   * Specify the nginx vhost template
   * Specify the PHP router script (for php 5.4's built-in web-server)

## Wishlist / Patch-Welcomes ##

 * Add DatabaseManagementInterface based on MySQL CLI
 * For "amp export" and "amp create", add option "--format=shell,json,yml"
 * Guided configuration and testing (eg "amp config -i")
 * Register new virtual-hosts in /etc/hosts
 * Automatically restart Apache/nginx after creating or changing virtual-hosts
 * Add more heuristics/settings to work well in common dev environments
   (Debian/Ubuntu, MAMP, XAMPP, MacPorts, etc)
