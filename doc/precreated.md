# MySQL Precreated (Experimental)

In some environments, you may not have sufficient access to create new DBs
on the fly -- but you can ask for a set of DB's to be precreated by an
administrator.  You'll need to follow a simple naming convention for
databases and users (more below).

# Planning

Any precreated databases must follow a formula, and the formula is based on
the numeric variable ```db_seq```.  For example, suppose we define the DSN
formula as:

```
mysql://static_{{db_seq}}:topsecret@127.0.0.1:3307/static_{{db_seq}}'
```

If you call `amp create` three times, it will look for three DBs with the credentials:

 * Database: `static_1`; User: `static_1`; Password: `topsecret`; Host: `127.0.0.1`
 * Database: `static_2`; User: `static_2`; Password: `topsecret`; Host: `127.0.0.1`
 * Database: `static_3`; User: `static_3`; Password: `topsecret`; Host: `127.0.0.1`

Any field (datbase, user, password, host, port) can include the variable
`{{db_seq}}`.


# Setup: MySQL

The MySQL administrator should create the databases/users.  You'll need to
agree ahead of time how many to pre-create.  For example, suppose you need
three:

```
mysql> create database static_1;
Query OK, 1 row affected (0.00 sec)

mysql> create database static_2;
Query OK, 1 row affected (0.00 sec)

mysql> create database static_3;
Query OK, 1 row affected (0.00 sec)

mysql> grant all on static_1.* to 'static_1'@'localhost' identified by 'topsecret';
Query OK, 0 rows affected, 1 warning (0.00 sec)

mysql> grant all on static_2.* to 'static_2'@'localhost' identified by 'topsecret';
Query OK, 0 rows affected, 1 warning (0.00 sec)

mysql> grant all on static_3.* to 'static_3'@'localhost' identified by 'topsecret';
Query OK, 0 rows affected, 1 warning (0.00 sec)
```

# Setup: CLI

```
amp config:set --db_type=mysql_precreated
export PRECREATED_DSN_PATTERN='mysql://static_{{db_seq}}:topsecret@127.0.0.1:3307/static_{{db_seq}}'
```

> (Note: The order of the steps in not important -- as long as you do both
> of them before calling `amp create`.)

> (Note: Ensure that the environment variable `PRECREATED_DSN_PATTERN` is consistently
> set -- in your `.bashrc` or CI server. If you set the `db_type` to `mysql_precreated`
> but don't set `PRECREATED_DSN_PATTERN`, then it won't work...)

# Testing

To see if this configuration is working, you can create three empty folders
and assign each one a database:

```bash
for testdir in $PWD/test-1 $PWD/test-2 $PWD/test-3 ; do mkdir $testdir ; done
for testdir in $PWD/test-1 $PWD/test-2 $PWD/test-3 ; do pushd $testdir ; amp create ; popd ; done
```

If that appears to work, then cleanup your test folders (and release the precreated DBs for reuse):

```bash
rm -rf test-1 test-2 test-3
amp cleanup
```
