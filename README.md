# PopwerPs-Core
## _main core of power proxy seller_
# ربات فروشنده و وب اپلیکیشن برای فروش کانفیگ v2ray
## _قسمت هسته و ربات سرویس_

## Installation

powerps-core requires [powerps-webapp](https://github.com/rezahajrahimi/powerps-webapp) to run.

- upload files to your host or PaaS service like  [Chabokan](https://zaya.io/yojc2)  
- change Environment variable in .env file

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
DEV_ID=admin telegram id
```

## ZARINPAL PAYMENT VARIABLES

```sh
ZARINPAL_MERCHANT_ID=set zarinpal merchent
ZARINPAL_SANDBOX_ENABLED=false
```

## NOWPAYMENTS PAYMENT VARIABLES

```sh
NOWPAYMENTS_API_KEY=set nowpayments api key
NOWPAYMENTS_ENV=live
```

## SET TELEGRAM WEBHOOKS

```sh
https://api.telegram.org/botxxxxxxxxxxxxxx/setwebhook?url=https://POWERPS_CORE_URL/api/telegram/webhooks/inbound
```

You have to change xxxxxxxxxxxxxx with your telegram bot token and also change POWERPS_CORE_URL with url of powerps-core then copy text and run by your browser.

## LOGIN

USERNAME: value of TELEGRAM_ADMIN_ID
PASSWORD: admin123456

## YOUTUBE Toturial

watch full instalation on  [youtube](https://www.youtube.com/watch?v=ccpmv4H9mew).

## Support

در تلگرام به آیدی  [powerproxysellersupport](https://t.me/powerproxysellersupport) پیام بدهید.

همچنین می توانید عضو گروه تلگرامی [powerproxysellerchat](https://t.me/powerproxysellerchat) بشوید و درگروه سوالات خود را در میان بگذارید.
