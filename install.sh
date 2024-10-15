#!/bin/bash
# Color codes
GREEN='\033[0;32m'
CYAN='\033[0;36m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Pretty title
echo -e "${CYAN}==============================${NC}"
echo -e "${YELLOW}  Setting up or Updating your core and WebApp PowerPs${NC}"
echo -e "${CYAN}==============================${NC}"

# Prompt user for the subdomains
read -p "Enter your Core subdomain (e.g., core.domain.com): " LARAVEL_SUBDOMAIN
read -p "Enter your WebApp subdomain (e.g., web.domain.com): " HTML5_SUBDOMAIN

# Update package lists and install necessary packages
echo -e "${GREEN}Updating package lists and installing necessary packages...${NC}"
sudo apt-get update
sudo apt-get install -y apache2 mysql-server php8.3 php8.3-mysql libapache2-mod-php8.3 php8.3-cli php8.3-zip php8.3-xml php8.3-mbstring php8.3-curl php8.3-gd php-imagick libmagickwand-dev composer unzip git expect

# Secure MySQL Installation using expect
echo -e "${GREEN}Securing MySQL Installation...${NC}"
SECURE_MYSQL=$(expect -c "
set timeout 10
spawn sudo mysql_secure_installation
expect \"VALIDATE PASSWORD COMPONENT can be used to test passwords\"
send \"n\r\"
expect \"New password:\"
send \"yourpassword\r\"
expect \"Re-enter new password:\"
send \"yourpassword\r\"
expect \"Do you wish to continue with the password provided?\"
send \"y\r\"
expect \"Remove anonymous users?\"
send \"y\r\"
expect \"Disallow root login remotely?\"
send \"y\r\"
expect \"Remove test database and access to it?\"
send \"y\r\"
expect \"Reload privilege tables now?\"
send \"y\r\"
expect eof
")
echo "$SECURE_MYSQL"

# Create MySQL database and user
DB_NAME='powerps_db'
DB_USER='powerps_user'
// create a random password for the user
DB_PASS=$(openssl rand -base64 12)

echo -e "${GREEN}Creating MySQL database and user...${NC}"
sudo mysql -e "CREATE DATABASE ${DB_NAME};"
sudo mysql -e "CREATE USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
sudo mysql -e "GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';"
sudo mysql -e "FLUSH PRIVILEGES;"

# Check if the Laravel project directory exists
if [ -d "/var/www/html/laravel-app" ]; then
    # If it exists, update the repository
    echo -e "${GREEN}Updating the Laravel project repository...${NC}"
    cd /var/www/html/laravel-app
    git pull origin main
else
    # Clone the Laravel project repository
    echo -e "${GREEN}Cloning Laravel project repository...${NC}"
    git clone https://github.com/rezahajrahimi/powerps-core /var/www/html/laravel-app
    cd /var/www/html/laravel-app
    echo -e "${GREEN}Add Extensions...${NC}"
sudo sed -i '1i extension=/var/www/html/laravel-app/bolt.so' /etc/php/8.3/apache2/php.ini
sudo sed -i '1i extension=/var/www/html/laravel-app/bolt.so' /etc/php/8.3/cli/php.ini


fi


# Restart Apache to apply changes
echo -e "${GREEN}Restarting Apache to apply changes...${NC}"
sudo systemctl restart apache2

# Install Composer dependencies
echo -e "${GREEN}Installing Composer dependencies...${NC}"
composer install

# Set permissions for Laravel storage and bootstrap/cache directories
echo -e "${GREEN}Setting permissions...${NC}"
sudo chown -R www-data:www-data /var/www/html/laravel-app/storage
sudo chown -R www-data:www-data /var/www/html/laravel-app/bootstrap/cache
sudo chmod -R 775 /var/www/html/laravel-app/storage
sudo chmod -R 775 /var/www/html/laravel-app/bootstrap/cache

# Set up environment variables if not already set
echo -e "${GREEN}Setting up environment variables...${NC}"
if [ ! -f "/var/www/html/laravel-app/.env" ]; then
    cp /var/www/html/laravel-app/.env.example /var/www/html/laravel-app/.env
    sed -i '/APP_NAME/d' /var/www/html/laravel-app/.env
    echo "APP_NAME=Laravel" >> /var/www/html/laravel-app/.env

    // check if APP_ENV is already set remove it and add it again
    sed -i '/APP_ENV/d' /var/www/html/laravel-app/.env
    echo "APP_ENV=production" >> /var/www/html/laravel-app/.env
    sed -i '/APP_KEY/d' /var/www/html/laravel-app/.env
    echo "APP_KEY=" >> /var/www/html/laravel-app/.env

    sed -i '/APP_DEBUG/d' /var/www/html/laravel-app/.env
    echo "APP_DEBUG=true" >> /var/www/html/laravel-app/.env

    sed -i '/APP_URL/d' /var/www/html/laravel-app/.env
    echo "APP_URL=https://${LARAVEL_SUBDOMAIN}" >> /var/www/html/laravel-app/.env
    echo "FRONT_URL=https://${HTML5_SUBDOMAIN}" >> /var/www/html/laravel-app/.env

    sed -i '/DB_CONNECTION/d' /var/www/html/laravel-app/.env
    echo "DB_CONNECTION=mysql" >> /var/www/html/laravel-app/.env
    sed -i '/DB_HOST/d' /var/www/html/laravel-app/.env
    echo "DB_HOST=127.0.0.1" >> /var/www/html/laravel-app/.env
    sed -i '/DB_PORT/d' /var/www/html/laravel-app/.env
    echo "DB_PORT=3306" >> /var/www/html/laravel-app/.env
    sed -i '/DB_DATABASE/d' /var/www/html/laravel-app/.env
    sed -i '/DB_USERNAME/d' /var/www/html/laravel-app/.env
    sed -i '/DB_PASSWORD/d' /var/www/html/laravel-app/.env
    echo "DB_DATABASE=${DB_NAME}" >> /var/www/html/laravel-app/.env
    echo "DB_USERNAME=${DB_USER}" >> /var/www/html/laravel-app/.env
    echo "DB_PASSWORD=${DB_PASS}" >> /var/www/html/laravel-app/.env
    # read & set telegram token
    read -p "Enter your Bot token (e.g., botxxxxxxxxxxxxxxx): " TELEGRAM_TOKEN
    echo "TELEGRAM_TOKEN=${TELEGRAM_TOKEN}" >> /var/www/html/laravel-app/.env
    read -p "Enter your Bot admin ID (e.g., 123456789): " TELEGRAM_ADMIN_ID
    echo "TELEGRAM_ADMIN_ID=${TELEGRAM_ADMIN_ID}" >> /var/www/html/laravel-app/.env
    echo "TELEGRAM_API_ENDPOINT=https://api.telegram.org" >> /var/www/html/laravel-app/.env

    # read & set ZARINPAL MERCHANT ID and NOWPAYMENTS API KEY tokens
    read -p "Enter your Zarinpal Merchant ID (e.g., xxxxxxxx-sssssss-xxxxxxxx): " ZARINPAL_MERCHANT_ID
    echo "ZARINPAL_MERCHANT_ID=${ZARINPAL_MERCHANT_ID}" >> /var/www/html/laravel-app/.env
    read -p "Enter your NOWPAYMENTS API KEY (e.g., xxxxxxxx-sssssss-xxxxxxxx): " NOWPAYMENTS_API_KEY
    echo "NOWPAYMENTS_API_KEY=${NOWPAYMENTS_API_KEY}" >> /var/www/html/laravel-app/.env

fi

# Generate app key
echo -e "${GREEN}Generating app key...${NC}"
php artisan key:generate

# Run migrations
echo -e "${GREEN}Running migrations...${NC}"
php artisan migrate
# Run Link Storage
php artisan storage:link
# Check if phpMyAdmin is installed
if [ -d "/var/www/html/phpmyadmin" ]; then
    echo -e "${GREEN}phpMyAdmin is already installed.${NC}"
else
    # Install PHPMyAdmin
    echo -e "${GREEN}Installing PHPMyAdmin...${NC}"
    cd /var/www/html
    wget https://www.phpmyadmin.net/downloads/phpMyAdmin-latest-all-languages.zip
    unzip phpMyAdmin-latest-all-languages.zip
    mv phpMyAdmin-*-all-languages phpmyadmin
    rm phpMyAdmin-latest-all-languages.zip
fi

# Set up Apache virtual host for Laravel
echo -e "${GREEN}Setting up Apache virtual host for Laravel...${NC}"
sudo bash -c "cat <<EOT > /etc/apache2/sites-available/powerps-core.conf
<VirtualHost *:80>
    ServerName ${LARAVEL_SUBDOMAIN}
    DocumentRoot /var/www/html/laravel-app/public
    <Directory /var/www/html/laravel-app>
        AllowOverride All
    </Directory>
    ErrorLog \${APACHE_LOG_DIR}/laravel-error.log
    CustomLog \${APACHE_LOG_DIR}/laravel-access.log combined
</VirtualHost>
EOT"

# Check if the HTML5 project directory exists
if [ -d "/var/www/html/powerps-webapp" ]; then
    # If it exists, update the repository
    echo -e "${GREEN}Updating the HTML5 project repository...${NC}"
    cd /var/www/html/powerps-webapp
    git pull origin main
else
    # Clone the HTML5 project repository
    echo -e "${GREEN}Cloning HTML5 project repository...${NC}"
    git clone https://github.com/rezahajrahimi/powerps-webapp /var/www/html/powerps-webapp
    cd /var/www/html/powerps-webapp
fi

// check if .env file exists in HTML5 project directory, remove ir and create a new one
if [ -f "/var/www/html/powerps-webapp/assets/.env" ]; then
    rm /var/www/html/powerps-webapp/assets/.env
fi

# Set Laravel url in HTML5 project .env file
echo "BASE_URL=https://${LARAVEL_SUBDOMAIN}" >> /var/www/html/powerps-webapp/assets/.env

# Set up Apache virtual host for HTML5 project
echo -e "${GREEN}Setting up Apache virtual host for HTML5 project...${NC}"
sudo bash -c "cat <<EOT > /etc/apache2/sites-available/powerps-webapp.conf
<VirtualHost *:80>
    ServerName ${HTML5_SUBDOMAIN}
    DocumentRoot /var/www/html/powerps-webapp
    <Directory /var/www/html/powerps-webapp>
        AllowOverride All
        Options Indexes FollowSymLinks
        Require all granted
    </Directory>
    ErrorLog \${APACHE_LOG_DIR}/html5-error.log
    CustomLog \${APACHE_LOG_DIR}/html5-access.log combined
</VirtualHost>
EOT"

# Enable Apache virtual hosts
sudo a2ensite powerps-core
sudo a2ensite powerps-webapp
sudo a2enmod rewrite
sudo systemctl restart apache2

# Add domain entries to /etc/hosts
echo "127.0.0.1 ${LARAVEL_SUBDOMAIN}" | sudo tee -a /etc/hosts
echo "127.0.0.1 ${HTML5_SUBDOMAIN}" | sudo tee -a /etc/hosts

# Add schedule to cron job
echo -e "${GREEN}Adding schedule to cron job...${NC}"
(crontab -l ; echo "* * * * * cd /var/www/html/laravel-app && php artisan schedule:run >> /dev/null 2>&1") | crontab -

# Ensure services start on reboot
echo -e "${GREEN}Ensuring services start on reboot...${NC}"
(crontab -l ; echo "@reboot systemctl restart apache2") | crontab -
(crontab -l ; echo "@reboot systemctl restart mysql") | crontab -
(crontab -l ; echo "@reboot /usr/bin/php /var/www/html/laravel-app/artisan serve &") | crontab -

# Start Laravel server
echo -e "${GREEN}Starting powerps core...${NC}"
cd /var/www/html/laravel-app
php artisan serve &

# Completion message
echo -e "${CYAN}==============================${NC}"
echo -e "${YELLOW}  Setup Complete!${NC}"
echo -e "${CYAN}==============================${NC}"
echo -e "${GREEN}PowerPs installation complete!${NC}"
