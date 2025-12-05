# Back Me Up - WordPress Sync Plugin

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
4. Configure your settings under "Back Me Up" > "Settings"

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

1. Go to "Back Me Up" in WordPress admin
2. Click "Pull from Live"
3. The plugin will:
   - Create a local backup
   - Sync files from live to local
   - Export and import the live database
   - Replace live URLs with local URLs

### Push to Live (Local → Live)

1. Go to "Back Me Up" in WordPress admin
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
