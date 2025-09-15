# Eyes

███████╗██╗ ██╗███████╗███████╗
██╔════╝██║ ██║██╔════╝██╔════╝
███████╗██║ ██║█████╗ ███████╗
╚════██║██║ ██║██╔══╝ ╚════██║
███████║╚██████╔╝██║ ███████║
╚══════╝ ╚═════╝ ╚═╝ ╚══════╝
E Y E S

---

## Overview
**Eyes** is a secure, server-hosted system for controlled investigative use.  
It collects visitor data with fallback handling and stores structured logs for analysis.  

This repository contains the web application code. Logs and sensitive data are **not** tracked in Git.

---

## Features
- Collects visitor connection and device metadata  
- Attempts fine-grained geolocation, with fallback to IP-based methods  
- Logs stored securely outside the web root (`/opt/eyes/logs`)  
- Supports log rotation, secure archival, and encryption  
- Designed for controlled, authorized use in investigative workflows  

---

## Installation

### 1. Clone repo
```bash```
git clone https://github.com/Promisecharles/eyes.git /opt/eyes
cd /opt/eyes
2. Install dependencies
On Debian/Ubuntu:


sudo apt update
sudo apt install -y nginx php-fpm
3. Deploy files
Point your web server’s document root to /opt/eyes/www.

Create and secure the log directory:

bash
Copy code
sudo mkdir -p /opt/eyes/logs
sudo chown root:www-data /opt/eyes/logs
sudo chmod 750 /opt/eyes/logs
Restart services:

bash
Copy code
sudo systemctl restart php*-fpm nginx
Usage
Open the site in a browser (http://<your-server>/index.html).

The system will attempt to collect client geolocation; if unavailable, it falls back to IP-based lookup.

All events are logged under /opt/eyes/logs/:

data.txt — full structured log entries

ip.txt — IP-specific entries

View logs in real time:


tail -f /opt/eyes/logs/data.txt

Security Recommendations
HTTPS: always serve over TLS (self-signed or Let’s Encrypt).
Access control: restrict log viewing to authorized personnel only.
Log rotation: configure logrotate to rotate and compress logs daily.
Archival: encrypt rotated archives and store under /opt/eyes/secure_archives.
Audit trail: maintain hashes (e.g., sha256sum) for evidence integrity.

Git Notes
The www/ folder is version-controlled.
The logs/ folder is excluded from Git with .gitignore.
Keep repo private — do not publish logs or sensitive files.

Disclaimer
Eyes is designed for authorized investigative use only.
Unauthorized use is prohibited. Logs and data may contain sensitive information — handle with care.
