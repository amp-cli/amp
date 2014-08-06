## File Permissions for Data Directories

Web applications often include data directories for storing content like:

 * Images and documents uploaded by users
 * Auto-generated media files
 * Caches of system data-structures
 * Application logs

For example:

 * In Symfony Standard Edition, "app/cache" and "app/logs" are data directories.
 * In Drupal, "sites/default/files" is a data directory.
 * In Joomla, "media" is a data directory.
 * In CiviCRM, "sites/default/files/civicrm/ConfigAndLog" is a data directory.

In many server configurations, there are two important user-accounts: a
login-user (such as "alice") who manages files and a web-user (such as
"www-data") who executes page-requests.  It's most desirable to set the file
permissions so that:

 * All the source code files are read-write for "alice"
 * All the source code files are read-only for everyone else
 * All the data dirs are read-write for "alice"
 * All the data dirs are read-write for "www-data"

Unfortunately, this arrangement requires some extra administration, and the
most convenient mechanisms for implementing it are not portable to all
Unix-like environments. Consequently, you must tell amp which mechanism to use.

## Configuring Amp

To specify how amp should manage data directories, set the amp option
"perm_type" (by running "amp config" or "amp config:set").  The values may
be used:

<table>
  <thead>
    <tr>
      <th>Value</th>
      <th>When to use</th>
      <th>How it works</th>
      <th>Considerations</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td>
        <code>none</code>
      </td>
      <td>
        Your login-user and web-user are the same. For example, this is common in MAMP.
      </td>
      <td>
        It does not set any special permissions for data-directories.
      </td>
      <td>
      </td>
    </tr>
    <tr>
      <td>
        <code>linuxAcl</code>
      </td>
      <td>
        The web-server runs Linux.
      </td>
      <td>
        For each data-directory, amp calls "<code>setfacl</code>".  Your login-user and
        web-user will both have full permission to read-write files in the
        data-directory.
      </td>
      <td>
        <p>
          ACL support is often disabled by default, so you may need to
          activate it.  The instructions for "Enabling ACLs in the Filesystem"
          in <a href="https://help.ubuntu.com/community/FilePermissionsACLs">https://help.ubuntu.com/community/FilePermissionsACLs</a>
          should apply to most Debian-based Linux distros.
        </p>
        <p>
          ACL's are supported by most local file-systems (ext2, ext3, ext4,
          xfs, jfs, et al) but may not work on network file-systems or
          ancient file-systems (fat).
        </p>
        <p>
          You must configure the related option, "<code>perm_user</code>", to identify
          the name of the web-user (e.g.  "www-data" or "www" or "httpd").
        </p>
      </td>
    </tr>
    <tr>
      <td>
        <code>osxAcl</code>
      </td>
      <td>
        The web-server runs Mac OS X
      </td>
      <td>
        For each data-directory, amp calls "<code>chmod +a</code>".  Your login-user and
        web-user will both have full permission to read-write files in the
        data-directory.
      </td>
      <td>
        <p>
          You must configure the related option, "<code>perm_user</code>", to identify the name
          of the web-user (e.g. "www-data" or "www" or "httpd").
        </p>
      </td>
    </tr>
    <tr>
      <td>
        <code>custom</code>
      </td>
      <td>
        Your OS supports sophisticated ACLs but amp does not have a pre-packaged command for it.
      </td>
      <td>
        For each data-directory, amp calls a command designated by the sysadmin.
      </td>
      <td>
        <p>
          You must configure the amp option "<code>perm_custom_command</code>" to specify the command.
          The command includes a variable "{DIR}", e.g. "<code>setfacl -m u:www-data:rwx -m d:u:www-data:rwx {DIR}</code>".
        </p>
      </td>
    </tr>
    <tr>
      <td>
        <code>worldWritable</code>
      </td>
      <td>
        ACL's are unsupported by your operating-system or file-system.
      </td>
      <td>
        For each data-directory, amp calls "<code>chmod 1777</code>".
      </td>
      <td>
        The login-user and web-user can both create files, but they cannot
        edit each other's files -- e.g.  if the login-user creates a log
        file, then the web-user cannot append to it.  You're on your own to
        resolve this.
      </td>
    </tr>
  </tbody>
</table>

## Creating a Data Directory

When installing an application, it is best to call "amp datadir" early on to
set suitable permissions on the data dirs.  For example, suppose we're
writing an install script for a Symfony Standard Edition application (which
has two datadirs, "app/cache" and "app/logs").  Here are some good and bad
examples of creating the data-directory:

```bash
## Good - If supported by the server, login-user and web-user have equal access
amp datadir app/cache app/logs

## OK - Same effect as above (but more verbose)
mkdir app/cache app/logs
amp datadir app/cache app/logs

## Bad - This approach will fail if login-user and web-user ever write to the same file
mkdir app/cache app/logs
chmod 777 app/cache app/logs

## Bad - This approach prevents login-user from writing to the data directory
mkdir app/cache app/logs
sudo chown www-data:www-data app/cache app/logs

## Bad - This may incorrectly guess that the login-user is "root"
sudo amp datadir app/cache app/logs

## Bad - This may incorrectly guess that the login-user is "www-data"
sudo -u www-data amp datadir app/cache app/logs
```

Note that permissions apply to the specified directory and to any *new*
files/subdirectories, but the permissions do *not* apply retroactively.
Consider a few ways to create "app/logs" and its subdir "app/logs/dev":

```bash
## Good - Both parent and child are datadirs
amp datadir app/logs app/logs/dev

## OK - Same effect as above (but more verbose)
mkdir app/logs app/logs/dev
amp datadir app/logs app/logs/dev

## OK - Same effect as above (but mixes styles)
amp datadir app/logs
mkdir app/logs/dev

## Bad - The subdirectory may not be flagged as a datadir
mkdir app/logs
mkdir app/logs/dev
amp datadir app/logs
```

## Issues

Some off-the-shelf PHP libraries are overzealous about setting file
permissions.  For example, if a library *explicitly* sets a file's
permission to 0640, then this may interfere with ACLs.  It's better to
configure the library to use the default file-system permissions (and to
manage the default permission via umask or ACL).
