# PopwerPs-Core

# ربات Power Proxy Seller

ربات **Power Proxy Seller** یک ابزار پیشرفته و همه‌کاره برای مدیریت و فروش کانفیگ‌های پروکسی است که با آخرین نسخه‌های **هیدیفای** هماهنگ شده است. این ربات با ارائه امکانات گسترده و کاربردی، تجربه‌ای بی‌نظیر را برای مدیران و کاربران فراهم می‌کند.

---

## ویژگی‌های کلیدی

- **هماهنگی با آخرین نسخه‌های هیدیفای**
- **مدیریت آسان با وب اپلیکیشن**
- **اتصال به درگاه‌های پرداخت زرین‌پال و NowPayments و Cryptomus **
- **فروش و بازاریابی هوشمند با دستیار فروش**
- **مدیریت کیف‌پول‌های تومانی، دلاری و همکاری**
- **شخصی‌سازی کامل متن‌ها، پیام‌ها و منوها**
- **مدیریت کانفیگ‌ها (ارتقا، حذف، ویرایش)**
- **مدیریت کاربران و تراکنش‌ها**
- **امنیت بالا با پشتیبان‌گیری خودکار و دستی**
- **ارتباط هوشمند با کاربران (پیام‌های خودکار)**
- **مدیریت کانال‌های تلگرامی**
- **نمایش اطلاعات کاربران (تراکنش‌ها، زیرمجموعه‌ها)**
- **دسته‌بندی و نمایش کانفیگ‌ها**
- **مدیریت فروشندگان و کارت‌های هدیه (Gift Card)**

---

## مزایای استفاده

- **کاربری آسان** با رابط کاربری ساده و قابل شخصی‌سازی
- **امنیت بالا** با استفاده از درگاه‌های پرداخت معتبر
- **انعطاف‌پذیری** با قابلیت اجرا بر روی سرور و سرویس‌های ابری
- **پشتیبانی قوی** با قابلیت‌های پشتیبان‌گیری خودکار و دستی

---

## کاربردها

- **فروشندگان پروکسی**: مدیریت و فروش کانفیگ‌ها
- **مدیران شبکه**: مدیریت متمرکز کاربران و تراکنش‌ها
- **کاربران نهایی**: خرید آسان کانفیگ‌ها و مدیریت کیف‌پول

---

## راه‌های ارتباطی

وب سایت: [https://powerps.ir](https://powerps.ir)

تلگرام: [@powerproxysellersupport](https://t.me/powerproxysellersupport) 

## Fast Installation

Just run this command on your server

Ubuntu 24.04 friendly

```sh
sudo bash -c "$(curl -sL https://raw.githubusercontent.com/rezahajrahimi/powerps-core-scripts/refs/heads/main/install.sh)" @ install
```

Also for modify variables go to this path, remember you have to change permission of this file and after that return them to default.

```sh
/var/www/html/laravel-app/.env
```

## Manual Installation

powerps-core requires [powerps-webapp](https://github.com/rezahajrahimi/powerps-webapp) to run.

- upload files to your host or PaaS service like  [Chabokan](https://zaya.io/yojc2)  
- change Environment variable in .env file
- set php version to 8.3

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

## TELEGRAM BOT  VARIABLES

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

You have to change xxxxxxxxxxxxxx with your telegram bot token and also change POWERPS_CORE_URL with url of powerps-core then copy text and run by your browser.

## LOGIN

USERNAME: value of TELEGRAM_ADMIN_ID
PASSWORD: admin123456

## UPDATE

first of all take a back up from database after that change "APP_ENV" to "dev" in .env files, then insert migration command in console and run it:

```sh
php artisan migrate
```

Finaly, rechange "APP_ENV" to "production".

## YOUTUBE Toturial

watch full instalation on  [youtube](https://youtu.be/drZGXXxSNSE).

# Power Proxy Seller Bot

The **Power Proxy Seller Bot** is an advanced and versatile tool for managing and selling proxy configurations, fully compatible with the latest versions of **HideIP**. This bot offers a wide range of features, providing an unparalleled experience for both administrators and users.

---

## Key Features

- **Compatibility with the latest HideIP versions**
- **Easy management via Web App**
- **Integration with ZarinPal, Cryptomus And NowPayments payment gateways**
- **Smart sales and marketing with Sales Assistant**
- **Management of Toman, Dollar, and Partnership wallets**
- **Full customization of texts, messages, and menus**
- **Config management (upgrade, delete, edit)**
- **User and transaction management**
- **High security with automatic and manual backups**
- **Smart communication with users (automatic messages)**
- **Telegram channel management**
- **Display user information (transactions, referrals)**
- **Categorization and display of configs**
- **Seller management and Gift Card support**

---

## Benefits

- **User-friendly** with a simple and customizable interface
- **High security** using reliable payment gateways
- **Flexibility** with the ability to run on servers and cloud services
- **Strong support** with automatic and manual backup features

---

## Use Cases

- **Proxy sellers**: Manage and sell configs
- **Network administrators**: Centralized management of users and transactions
- **End users**: Easily purchase configs and manage wallets

---

## Contact Us

website: [https://powerps.ir](https://powerps.ir)

Telegram: [@powerproxysellersupport](https://t.me/powerproxysellersupport) 

