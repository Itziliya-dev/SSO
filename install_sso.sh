#!/bin/bash

# ==============================================================================
# SSO Panel Management Script (Final Web-Installable Version)
# ==============================================================================

# --- Initial Setup & Colors ---
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

# --- Helper Functions ---
function print_success { echo -e "${GREEN}✔ $1${NC}"; }
function print_error { echo -e "${RED}✖ $1${NC}"; exit 1; }
function print_warning { echo -e "${YELLOW}➜ $1${NC}"; }

# ==============================================================================
#                             UFW SETUP PORTS
# ==============================================================================
function configure_firewall {
    print_warning "Configuring firewall and opening required ports..."
    PORTS=(465 587 80 443 1030)

    if command -v ufw &>/dev/null; then
        for PORT in "${PORTS[@]}"; do
            ufw allow "$PORT" &>/dev/null
        done
        ufw reload &>/dev/null
        print_success "Firewall configured using UFW."
    elif command -v firewall-cmd &>/dev/null; then
        for PORT in "${PORTS[@]}"; do
            firewall-cmd --permanent --add-port="$PORT"/tcp &>/dev/null
        done
        firewall-cmd --reload &>/dev/null
        print_success "Firewall configured using firewalld."
    else
        print_warning "No supported firewall tool (ufw/firewalld) detected. Skipping firewall configuration."
    fi
}

# ==============================================================================
#                             INSTALLATION LOGIC
# ==============================================================================
function install_panel {

    print_warning "Checking and installing dependencies..."
    PACKAGES="nginx mariadb-server software-properties-common git openssl curl"
    for pkg in $PACKAGES; do
        if ! dpkg -s "$pkg" &>/dev/null; then apt-get update -qq && apt-get install -y "$pkg"; else print_success "$pkg is already installed."; fi
    done
    if ! dpkg -s "php8.3-fpm" &>/dev/null; then
        add-apt-repository ppa:ondrej/php -y >/dev/null 2>&1
        apt-get update -qq
        apt-get install -y php8.3-fpm php8.3-mysql php8.3-mysqli php8.3-gd php8.3-mbstring php8.3-xml php8.3-curl
    fi
    print_success "PHP 8.3 and extensions are ready."
    if ! command -v composer &> /dev/null; then
        print_warning "Installing Composer..."; curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
    fi
    print_success "Composer is ready."

    configure_firewall
    
    print_warning "Please provide installation details (press Enter for defaults):"
    read -p "Database Name (default: sso_db): " DB_NAME; DB_NAME=${DB_NAME:-sso_db}
    read -p "Database User (default: sso_user): " DB_USER; DB_USER=${DB_USER:-sso_user}
    read -sp "Database Password: " DB_PASS; echo; if [ -z "$DB_PASS" ]; then print_error "DB password cannot be empty."; fi
    read -p "Domain Name (e.g., sso.yourdomain.com): " DOMAIN; if [ -z "$DOMAIN" ]; then print_error "Domain name cannot be empty."; fi
    DEFAULT_PANEL_URL="https://$DOMAIN/Dashboard"
    read -p "User Panel URL (default: $DEFAULT_PANEL_URL): " PANEL_URL; PANEL_URL=${PANEL_URL:-$DEFAULT_PANEL_URL}
    read -p "Admin Username (required): " ADMIN_USER; if [ -z "$ADMIN_USER" ]; then print_error "Admin username cannot be empty."; fi
    read -sp "Admin Password (required): " ADMIN_PASS; echo; if [ -z "$ADMIN_PASS" ]; then print_error "Admin password cannot be empty."; fi

    print_warning "Downloading project source code..."
    if [ -d "/var/www/sso-system" ]; then rm -rf "/var/www/sso-system"; fi
    git clone https://github.com/ItzEliya234/SSO.git /var/www/sso-system
    if [ $? -ne 0 ]; then print_error "Failed to clone project from GitHub."; fi
    
    print_warning "Installing Composer dependencies..."
    (cd /var/www/sso-system && composer install --no-dev --optimize-autoloader)
    if [ $? -ne 0 ]; then print_error "Composer failed to install dependencies."; fi
    
    chown -R www-data:www-data /var/www/sso-system
    print_success "Project files deployed."

    print_warning "Setting up database and user..."
    mysql -u root -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS'; CREATE USER IF NOT EXISTS '$DB_USER'@'%' IDENTIFIED BY '$DB_PASS'; GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost'; GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'%'; FLUSH PRIVILEGES;"
    if [ $? -ne 0 ]; then print_error "Failed to create database or user."; fi
    print_success "Database and user created."

    print_warning "Downloading and importing database schema..."
    SCHEMA_URL="https://raw.githubusercontent.com/ItzEliya234/SSO/main/schema.sql"
    curl -sL -o /tmp/schema.sql "$SCHEMA_URL"
    if [ ! -f /tmp/schema.sql ]; then print_error "Failed to download schema.sql from GitHub."; fi
    mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < /tmp/schema.sql
    rm /tmp/schema.sql
    print_success "Database schema imported successfully."
    
    ADMIN_PASS_HASH=$(php -r "echo password_hash('$ADMIN_PASS', PASSWORD_DEFAULT);")
    print_warning "Seeding initial data..."
    mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "INSERT INTO users (username, password, created_at, is_owner, has_user_panel, is_staff, status, created_by) VALUES ('$ADMIN_USER', '$ADMIN_PASS_HASH', NOW(), 1, 1, 1, 'active', 'Installer');"
    mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "UPDATE settings SET setting_value = 'https://$DOMAIN' WHERE setting_key = 'app_base_url'; UPDATE settings SET setting_value = '$PANEL_URL' WHERE setting_key = 'app_panel_url'; UPDATE settings SET setting_value = 'https://$DOMAIN/admin' WHERE setting_key = 'app_admin_panel_url';"
    
    print_warning "Creating configuration files..."
    cat <<EOF > /var/www/sso-system/.env
DB_HOST=localhost
DB_USERNAME=$DB_USER
DB_PASSWORD="$DB_PASS"
DB_DATABASE=$DB_NAME
DB_PORT=3306
EOF
    cat <<EOF > /var/www/sso-system/.sso_install_info
DOMAIN="$DOMAIN"
DB_NAME="$DB_NAME"
DB_USER="$DB_USER"
EOF
    print_success "Configuration files created."

    configure_nginx "$DOMAIN"
    systemctl restart nginx mariadb php8.3-fpm
    print_success "🎉 SSO Panel installation completed successfully! 🎉"
    if [ ! -d "/etc/letsencrypt/live/$DOMAIN" ]; then print_warning "IMPORTANT: A temporary SSL is used. Use option '3' to get a real certificate."; fi
}

# ==============================================================================
#                             UNINSTALLATION LOGIC
# ==============================================================================
function uninstall_panel {
    print_warning "Starting SSO Panel Uninstallation..."
    INFO_FILE="/var/www/sso-system/.sso_install_info"
    if [ -f "$INFO_FILE" ]; then source "$INFO_FILE"; else
        print_warning "Could not find info file. Please provide details manually."
        read -p "Enter the domain to uninstall: " DOMAIN
        read -p "Enter the database name to remove: " DB_NAME
        read -p "Enter the database user to remove: " DB_USER
    fi
    if [ -z "$DOMAIN" ]; then print_error "Required info is missing."; fi
    
    echo
    print_warning "The following will be PERMANENTLY removed:"
    echo -e " - Nginx config for: ${YELLOW}$DOMAIN${NC}"
    echo -e " - Project files in: ${YELLOW}/var/www/sso-system${NC}"
    echo -e " - Database: ${YELLOW}$DB_NAME${NC}, User: ${YELLOW}$DB_USER${NC}"
    read -p "$(echo -e "${YELLOW}ARE YOU SURE? This cannot be undone. [y/N]: ${NC}")" CONFIRM
    echo
    if [[ "$CONFIRM" != "y" && "$CONFIRM" != "Y" ]]; then print_error "Uninstallation cancelled."; fi

    systemctl stop nginx
    if [ -f "/etc/nginx/sites-enabled/$DOMAIN" ]; then rm "/etc/nginx/sites-enabled/$DOMAIN"; fi
    if [ -f "/etc/nginx/sites-available/$DOMAIN" ]; then rm "/etc/nginx/sites-available/$DOMAIN"; fi
    if [ -f "/etc/nginx/ssl/nginx-selfsigned.key" ]; then rm -f /etc/nginx/ssl/nginx-selfsigned.*; fi
    if [ -d "/var/www/sso-system" ]; then rm -rf "/var/www/sso-system"; fi
    mysql -u root -e "DROP DATABASE IF EXISTS \`$DB_NAME\`; DROP USER IF EXISTS '$DB_USER'@'localhost'; DROP USER IF EXISTS '$DB_USER'@'%'; FLUSH PRIVILEGES;"
    nginx -t && systemctl start nginx
    print_success "✅ Uninstallation completed."
}

# ==============================================================================
#                             UPDATE LOGIC (NEW)
# ==============================================================================
function update_panel {
    print_warning "Starting SSO Panel Update..."
    PANEL_DIR="/var/www/sso-system"
    if [ ! -d "$PANEL_DIR" ]; then
        print_error "SSO Panel directory not found. Cannot update."
    fi

    print_warning "Moving to project directory..."
    cd "$PANEL_DIR" || exit

    print_warning "Fetching latest versions from GitHub..."
    git fetch --all --tags --prune
    if [ $? -ne 0 ]; then print_error "Failed to fetch from GitHub."; fi

    LATEST_TAG=$(git describe --tags `git rev-list --tags --max-count=1`)
    if [ -z "$LATEST_TAG" ]; then
        print_error "Could not determine the latest version tag. Make sure you have created a release on GitHub."
    fi
    print_success "Latest official version found: $LATEST_TAG"

    read -p "$(echo -e "${YELLOW}This will update the panel to version $LATEST_TAG. Are you sure? [y/N]: ${NC}")" CONFIRM
    if [[ "$CONFIRM" != "y" && "$CONFIRM" != "Y" ]]; then print_error "Update cancelled."; fi

    print_warning "Updating panel to version $LATEST_TAG..."
    git checkout "$LATEST_TAG"
    if [ $? -ne 0 ]; then print_error "Failed to checkout the new version."; fi

    print_warning "Updating dependencies with Composer..."
    composer install --no-dev --optimize-autoloader
    if [ $? -ne 0 ]; then print_error "Composer failed to update dependencies."; fi

    print_warning "Setting correct file permissions..."
    chown -R www-data:www-data "$PANEL_DIR"

    print_warning "Restarting PHP-FPM service..."
    systemctl restart php8.3-fpm
    if [ $? -ne 0 ]; then print_warning "Could not restart PHP-FPM, you may need to do it manually."; fi

    echo
    print_success "🎉 Panel successfully updated to version $LATEST_TAG! 🎉"
    print_warning "Note: If this update requires database changes, you must apply them manually."
}



# ==============================================================================
#                             SSL CERTIFICATE LOGIC
# ==============================================================================
function get_real_ssl {
    INFO_FILE="/var/www/sso-system/.sso_install_info"
    if [ ! -f "$INFO_FILE" ]; then print_error "Installation info file not found."; fi; source "$INFO_FILE"
    if ! command -v certbot &> /dev/null; then apt-get update -qq && apt-get install -y certbot python3-certbot-nginx; fi
    read -p "Enter your email for renewal notices: " LETSENCRYPT_EMAIL
    certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos -m "$LETSENCRYPT_EMAIL" --redirect
    if [ $? -eq 0 ]; then print_success "SSL certificate obtained successfully!"; systemctl restart nginx; else print_error "Certbot failed."; fi
}

# ==============================================================================
#                       DATA IMPORT LOGIC
# ==============================================================================
function import_data_only {
    print_warning "Importing data from an SQL file..."
    INFO_FILE="/var/www/sso-system/.sso_install_info"
    if [ ! -f "$INFO_FILE" ]; then print_error "Installation info file not found."; fi; source "$INFO_FILE"
    
    read -sp "Enter the password for database user '$DB_USER': " DB_PASS
    echo; if [ -z "$DB_PASS" ]; then print_error "Password cannot be empty."; fi
    
    read -e -p "Enter the full path to your .sql backup file: " SQL_FILE_PATH
    if [ ! -f "$SQL_FILE_PATH" ]; then print_error "File not found: $SQL_FILE_PATH"; fi
    
    read -p "$(echo -e "${YELLOW}This will ERASE all current data. Are you sure? [y/N]: ${NC}")" CONFIRM
    if [[ "$CONFIRM" != "y" && "$CONFIRM" != "Y" ]]; then print_error "Import cancelled."; fi

    print_warning "Emptying existing tables..."
    TABLES=$(mysql -u "$DB_USER" -p"$DB_PASS" -D "$DB_NAME" -e 'SHOW TABLES;' 2>/dev/null | tail -n +2)
    if [ -z "$TABLES" ]; then print_error "Could not connect to database or it's empty."; fi
    
    for t in $TABLES; do
        mysql -u "$DB_USER" -p"$DB_PASS" -D "$DB_NAME" -e "SET FOREIGN_KEY_CHECKS = 0; TRUNCATE TABLE \`$t\`; SET FOREIGN_KEY_CHECKS = 1;"
    done
    
    print_warning "Importing new data..."
    mysql -u "$DB_USER" -p"$DB_PASS" --force "$DB_NAME" < "$SQL_FILE_PATH"
    if [ $? -eq 0 ]; then print_success "Data imported successfully."; else print_error "An error occurred during data import."; fi
}

# ==============================================================================
#                             DATA EXPORT LOGIC
# ==============================================================================
function export_data_only {
    print_warning "Exporting data only to an SQL file..."
    INFO_FILE="/var/www/sso-system/.sso_install_info"
    if [ ! -f "$INFO_FILE" ]; then print_error "Installation info file not found."; fi; source "$INFO_FILE"
    
    read -sp "Enter the password for database user '$DB_USER': " DB_PASS
    echo; if [ -z "$DB_PASS" ]; then print_error "Password cannot be empty."; fi

    DEFAULT_EXPORT_PATH="/root/sso_data_backup_$(date +%F).sql"
    read -e -p "Enter path to save the backup file (default: $DEFAULT_EXPORT_PATH): " EXPORT_PATH
    EXPORT_PATH=${EXPORT_PATH:-$DEFAULT_EXPORT_PATH}
    
    print_warning "Exporting data from '$DB_NAME' to '$EXPORT_PATH'..."
    mysqldump --no-create-info --skip-triggers -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$EXPORT_PATH"
    if [ $? -eq 0 ]; then print_success "Data exported to: $EXPORT_PATH"; else print_error "Data export failed."; fi
}


# ==============================================================================
#                                 HELPER & MAIN MENU
# ==============================================================================
function configure_nginx {
    local DOMAIN=$1
    if [ -d "/etc/letsencrypt/live/$DOMAIN" ]; then
        SSL_CERT_PATH="/etc/letsencrypt/live/$DOMAIN/fullchain.pem"; SSL_KEY_PATH="/etc/letsencrypt/live/$DOMAIN/privkey.pem"
    else
        mkdir -p /etc/nginx/ssl
        openssl req -x509 -nodes -days 365 -newkey rsa:2048 -keyout /etc/nginx/ssl/nginx-selfsigned.key -out /etc/nginx/ssl/nginx-selfsigned.crt -subj "/CN=$DOMAIN"
        SSL_CERT_PATH="/etc/nginx/ssl/nginx-selfsigned.crt"; SSL_KEY_PATH="/etc/nginx/ssl/nginx-selfsigned.key"
    fi
    cat <<EOF > /etc/nginx/sites-available/$DOMAIN
server { listen 80; server_name $DOMAIN; return 301 https://\$host\$request_uri; }
server {
    listen 443 ssl http2; server_name $DOMAIN;
    root /var/www/sso-system; index index.php;
    ssl_certificate $SSL_CERT_PATH; ssl_certificate_key $SSL_KEY_PATH;
    location / { try_files \$uri \$uri/ /index.php?\$query_string; }
    location ~ \.php$ { include snippets/fastcgi-php.conf; fastcgi_pass unix:/run/php/php8.3-fpm.sock; }
}
EOF
    ln -sf /etc/nginx/sites-available/$DOMAIN /etc/nginx/sites-enabled/
    nginx -t; if [ $? -ne 0 ]; then print_error "Nginx config test failed."; fi
}

clear
if [ "$(id -u)" -ne 0 ]; then print_error "This script must be run as root."; fi
echo "SSO Panel Management Script"
echo "---------------------------"
echo "  1) Install SSO Panel"
echo "  2) Uninstall SSO Panel"
echo "  3) Update Panel to Latest Version"
echo "  4) Obtain/Renew SSL Certificate"
echo "  5) Import Data Only from SQL Backup"
echo "  6) Export Database Data Only"
echo
read -p "Enter your choice [1-6]: " choice
case $choice in
    1) install_panel;;
    2) uninstall_panel;;
    3) update_panel;;
    4) get_real_ssl;;
    5) import_data_only;;
    6) export_data_only;;
    *) print_error "Invalid choice. Exiting.";;
esac

exit 0
