# MySQL RAM Disk (Experimental)

In OS X and Linux, `amp` can automount a RAM disk and start a new MySQL
daemon.  This is useful if you need to run a large suite of tests using a
standard storage engine (like InnoDB or MyISAM).

# Pre-requisites

Install `mysqld`. Ensure `mysqld` is in the `PATH`.

This has been used in Debian/Ubuntu (with the stock mysql-server package)
and with OSX/MAMP.

# Setup

```bash
## Option 1. Enable the MySQL RAM disk provider. Use defaults.
amp config:set --db_type=mysql_ram_disk

## Option 2. As above, but explicitly set the major options.
amp config:set --db_type=mysql_ram_disk --ram_disk_type=auto --ram_disk_dir=/home/myuser/.amp/ram_disk --ram_disk_size=500

## Option 3. In Linux, starting a ramdisk requires sudo privileges, but you may want to
## avoid sudo. If you've already configured /tmp as a ramdisk, then you can use that instead.
## Take care to check the disk size (via `mount` and `/etc/fstab`).
amp config:set --db_type=mysql_ram_disk --ram_disk_type=manual --ram_disk_dir=/tmp/amp_ram_disk

## Option 4. As with option 3, you may want to avoid sudo on Linux. The administrator can
## register a custom ramdisk in /etc/fstab and then configure amp to use that.
echo none /mnt/mysql tmpfs size=1400m,mode=1777,uid=0 0 0 | sudo tee -a /etc/fstab
mkdir /mnt/mysql
sudo mount -a
sudo -u thedeveloper -H amp config:set --db_type=mysql_ram_disk --ram_disk_type=manual --ram_disk_dir=/mnt/mysql
```

# How it works

When you run `amp create`, it checks to see if the ram-disk exists
and if the mysqld is running on it. As needed, it starts both.

The data will be wiped after rebooting or unmounting.

If you want to intentionally reset all the data:

```bash
## Kill mysqld
kill $( cat ~/.amp/ram_disk/tmp/mysqld.pid )

## (Linux) Unmount
sudo umount ~/.amp/ram_disk

## (OSX) Unmount using the graphical "Disk Utility"
```
