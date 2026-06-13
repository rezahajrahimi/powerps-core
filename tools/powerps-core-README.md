# PowerPs-Core

# ربات Power Proxy Seller

ربات **Power Proxy Seller** یک ابزار پیشرفته و همه‌کاره برای مدیریت و فروش کانفیگ‌های پروکسی است که با آخرین نسخه‌های **هیدیفای** هماهنگ شده است.

---

## نیازمندی‌ها

- **PHP 8.4** (با اکستنشن phpBolt)
- MySQL
- Apache
- [powerps-webapp](https://github.com/rezahajrahimi/powerps-webapp)

---

## Fast Installation

روی سرور Ubuntu 24.04:

```sh
sudo bash -c "$(curl -sL https://raw.githubusercontent.com/rezahajrahimi/powerps-core-scripts/refs/heads/main/install.sh)" @ install
```

تنظیم `.env` بعد از نصب:

```sh
/var/www/html/laravel-app/.env
```

---

## Manual Installation

- فایل‌ها را روی هاست یا PaaS مثل [Chabokan](https://zaya.io/yojc2) آپلود کنید
- PHP را روی **8.4** تنظیم کنید
- اکستنشن **phpBolt** را فعال کنید (فایل‌های `bolt.so` داخل همین ریپو هستند)
- متغیرهای `.env` را تنظیم کنید

### phpBolt روی سرور از کجا می‌آید؟

`bolt.so` از اینترنت دانلود **نمی‌شود**. این فایل‌ها داخل همین ریپو commit شده‌اند:

| فایل | کاربرد |
|------|--------|
| `bolt.so` | نسخه پیش‌فرض (x86_64) |
| `bolt-x86_64.so` | سرورهای Intel/AMD |
| `bolt-aarch64.so` | سرورهای ARM |

اسکریپت نصب هنگام `git clone` یا `git pull` این فایل‌ها را می‌گیرد و در مسیر اکستنشن PHP کپی می‌کند. نسخه‌ها در `.powerps-php-version` و `.powerps-bolt-version` مشخص است.

برای هاست‌های اشتراکی مثل Chabokan از `chabok-php.ini` استفاده کنید:

```ini
extension = /var/www/html/app/bolt.so
```

---

## APP VARIABLES

```sh
APP_ENV=production
APP_DEBUG=false
FRONT_URL= set powerps webapp url
```

## DATABASE VARIABLES

```sh
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=database_name
DB_USERNAME=database_username
DB_PASSWORD=database_password
```

## TELEGRAM BOT VARIABLES

```sh
TELEGRAM_BOT_TOKEN=botTELEGRAMBOTTOKEN
TELEGRAM_API_ENDPOINT=https://api.telegram.org
TELEGRAM_ADMIN_ID=admin telegram id
```

## ZARINPAL PAYMENT VARIABLES

```sh
ZARINPAL_MERCHANT_ID=set zarinpal merchent
ZARINPAL_SANDBOX_ENABLED=false
```

## NOWPAYMENTS PAYMENT VARIABLES

```sh
NOWPAYMENTS_API_KEY=set nowpayments api key
```

## SET TELEGRAM WEBHOOKS

```sh
https://api.telegram.org/botxxxxxxxxxxxxxx/setwebhook?url=https://POWERPS_CORE_URL/api/telegram/webhooks/inbound
```

## LOGIN

USERNAME: value of `TELEGRAM_ADMIN_ID`  
PASSWORD: `admin123456`

## UPDATE

1. از دیتابیس بکاپ بگیرید
2. `APP_ENV` را در `.env` موقتاً `dev` کنید
3. مایگریشن را اجرا کنید:

```sh
php artisan migrate
```

4. `APP_ENV` را دوباره `production` کنید

---

## راه‌های ارتباطی

وب‌سایت: [https://powerps.ir](https://powerps.ir)  
تلگرام: [@powerproxysellersupport](https://t.me/powerproxysellersupport)

---

# Power Proxy Seller Bot (English)

The **Power Proxy Seller Bot** is an advanced tool for managing and selling proxy configurations, compatible with the latest **Hiddify** versions.

## Requirements

- PHP 8.4 with phpBolt extension
- MySQL, Apache
- [powerps-webapp](https://github.com/rezahajrahimi/powerps-webapp)

## Fast Installation

```sh
sudo bash -c "$(curl -sL https://raw.githubusercontent.com/rezahajrahimi/powerps-core-scripts/refs/heads/main/install.sh)" @ install
```

## phpBolt on server

`bolt.so` is **bundled in this repository** (not downloaded at install time). The install script copies the correct architecture file from `/var/www/html/laravel-app/` into PHP's extension directory.

## Contact

Website: [https://powerps.ir](https://powerps.ir)  
Telegram: [@powerproxysellersupport](https://t.me/powerproxysellersupport)
