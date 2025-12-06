# BackMeUp - WordPress Sync Plugin

A one-click solution for syncing WordPress installations between local and live environments.

## Features

- **One-Click Sync**: Easily pull from live to local or push from local to live
- **File Synchronization**: Uses rsync for efficient file transfers over SFTP
- **Database Sync**: Exports, transfers, and imports databases with automatic URL replacement
- **Automatic Backups**: Creates backups before each sync operation
- **Sync Logs**: Track all sync operations with detailed logging
- **Exclude Paths**: Configure paths to exclude from file sync
- **SFTP/SSH Support**: Secure connections using SSH key or password authentication

## Requirements

- PHP 7.4 or higher
- WordPress 5.0 or higher
- SFTP/SSH access to remote server
- rsync installed on local machine
- sshpass installed (for password authentication)
- MySQL/MariaDB command-line tools

## Installation

1. Copy the plugin to your WordPress plugins directory
2. Create a symbolic link to your Local Sites installation (if using Local):
   ```powershell
   New-Item -ItemType SymbolicLink -Path "C:\Users\WaqasHaneef\Local Sites\shique\app\public\wp-content\plugins\back-me-up" -Target "d:\P\A2ZSystems\Stylena\BackMeUpPlugin"
   ```
3. Activate the plugin in WordPress admin
4. Configure your settings under "BackMeUp" > "Settings"

## Configuration

### Remote Server Settings

- **Remote Site URL**: Full URL of your live website (e.g., https://example.com)
- **Remote WordPress Path**: Absolute path to WordPress on remote server (e.g., /var/www/html)

### SFTP/SSH Connection

- **SSH Host**: Your server hostname or IP address (e.g., access-5018470946.webspace-host.com)
- **SSH Username**: SSH username for authentication (e.g., a282748)
- **SSH Port**: SSH port (default: 22)
- **SSH Key Path**: Path to your private SSH key (recommended, leave empty for password auth)
- **SSH Password**: Password for authentication (only used if SSH key is not provided)

### Database Settings

- **Database Host**: Remote database host
- **Database Name**: Remote database name
- **Database User**: Remote database username
- **Database Password**: Remote database password

### Sync Options

- **Default Sync Direction**: Choose pull (live → local) or push (local → live)
- **Exclude Paths**: Add paths to exclude from file sync (e.g., wp-content/cache)

## Usage

### Pull from Live (Live → Local)

1. Go to "BackMeUp" in WordPress admin
2. Click "Pull from Live"
3. The plugin will:
   - Create a local backup
   - Sync files from live to local
   - Export and import the live database
   - Replace live URLs with local URLs

### Push to Live (Local → Live)

1. Go to "BackMeUp" in WordPress admin
2. Click "Push to Live"
3. Confirm the action
4. The plugin will:
   - Create a local backup
   - Export local database
   - Sync files from local to live
   - Import database on live server

## Authentication Setup

### Password Authentication (Easiest)

Simply enter your SSH password in the settings. The plugin will use `sshpass` to authenticate.

**Windows users**: Install sshpass via WSL or use Cygwin.

### SSH Key Setup (More Secure)

For password-less authentication (recommended):

1. Generate SSH key pair (if you don't have one):

   ```bash
   ssh-keygen -t rsa -b 4096 -C "your_email@example.com"
   ```

2. Copy public key to remote server:

   ```bash
   ssh-copy-id username@remote-host
   ```

3. Test connection:
   ```bash
   ssh username@remote-host
   ```

## Troubleshooting

### Error: "sshpass: Failed to run command: No such file or directory"

This error occurs when using password authentication without `sshpass` installed. **Recommended solution: Use SSH key authentication instead!**

#### Solution 1: Use SSH Key Authentication (RECOMMENDED)

This is the easiest and most secure solution - no `sshpass` required!

1. **Generate an SSH key pair** (skip if you already have one):

   ```powershell
   # On Windows PowerShell
   ssh-keygen -t rsa -b 4096
   # Press Enter to accept default location (~/.ssh/id_rsa)
   # Press Enter twice to skip passphrase
   ```

2. **Copy your public key to the remote server**:

   ```powershell
   # Method 1: Using ssh-copy-id (if available)
   ssh-copy-id -p 22 username@remote-host

   # Method 2: Manual copy
   # Get your public key
   Get-Content ~/.ssh/id_rsa.pub
   # Then add it to remote server's ~/.ssh/authorized_keys file
   ```

3. **Test the connection**:

   ```powershell
   ssh -p 22 username@remote-host
   # You should connect without a password prompt
   ```

4. **Update BackMeUp settings**:
   - SSH Key Path: Enter path to your private key (e.g., `C:\Users\YourName\.ssh\id_rsa`)
   - SSH Password: **Leave empty**
   - Click "Test SSH Configuration" to verify
   - Save settings

#### Solution 2: Install sshpass (if you must use passwords)

**Windows:**

- Install Cygwin from https://cygwin.com
- During installation, select these packages:
  - `rsync`
  - `sshpass`
  - `openssh`
- Add Cygwin's bin directory to your system PATH

**macOS:**

```bash
brew install hudochenkov/sshpass/sshpass
```

**Linux:**

```bash
# Ubuntu/Debian
sudo apt-get install sshpass

# CentOS/RHEL
sudo yum install sshpass
```

### sshpass not found (Password authentication)

Install sshpass on your system:

- **Windows**: Install via WSL, Cygwin, or Git Bash with sshpass
- **macOS**: `brew install hudochenkov/sshpass/sshpass`
- **Linux**: `sudo apt-get install sshpass` or `sudo yum install sshpass`

### rsync not found

Install rsync on your system:

- **Windows**: Install via WSL, Cygwin, or Git Bash (included with Git for Windows)
- **macOS**: Already included
- **Linux**: `sudo apt-get install rsync` or `sudo yum install rsync`

### Database tools not found

Ensure MySQL/MariaDB command-line tools are installed and in your PATH.

### Permission denied errors

Check that:

- SSH key has correct permissions (chmod 600)
- Remote user has write access to WordPress directory
- Database user has necessary privileges

## Security Notes

- Always backup before syncing
- Use SSH key authentication instead of passwords when possible
- SSH passwords are stored in WordPress database - use strong passwords
- Keep database credentials secure
- Test on staging environment first
- Review exclude paths to avoid syncing sensitive data
- For production use, consider using SSH keys instead of passwords

## Support

For issues or questions, contact A2Z Systems or visit the plugin repository.

## License

GPL v2 or later

## Changelog

### Version 1.0.0

- Initial release
- One-click sync functionality
- File and database synchronization
- Automatic backups
- Sync logging
