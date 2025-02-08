# PopwerPs-Core

## _main core of power proxy seller_

# ربات فروشنده و وب اپلیکیشن برای فروش کانفیگ v2ray توسط پنل هیدیفای Hiddify

## _قسمت هسته و ربات سرویس_

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

## Support

 برای آموزش نصب در سرویس چابکان می توانید از این مقاله آموزش ببینید.[نصب در چابکان](https://powerps.ir/?p=2974)
 
 درصورت هر گونه مشکل در نصب یا نیاز به مشاوره در تلگرام به آیدی  [powerproxysellersupport](https://t.me/powerproxysellersupport) پیام بدهید.

همچنین می توانید عضو گروه تلگرامی [powerproxysellerchat](https://t.me/powerproxysellerchat) بشوید و درگروه سوالات خود را در میان بگذارید.
