<?php
namespace App\Http\Controllers;

use App\Models\CustomText;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CustomTextController extends Controller
{
    private $customText;

    public function __construct()
    {
        $this->customText = new CustomText();
    }
    public function getAllTexts()
    {
        try {
            $this->syncMissingSeedKeys();
            $data = CustomText::all();
            return response()->json($data);
        } catch (\Throwable $th) {
            $this->seed();
            $data = CustomText::all();
            if (count($data) == 0) {
                return response()->json(['error' => 'خطایی رخ داده است'], 500);
            }
            return response()->json($data);
        }
    }
    private function getSeedData()
    {
        return [
            [
                'key' => 'action.welcome.message',
                'default_text' => json_encode([
                    ['type' => 'bold', 'text' => "سلام {name} {lastName}! به ربات ما خوش آمدید. 👋"],
                    ['type' => 'newline'],
                    ['type' => 'newline'],
                    ['type' => 'text', 'text' => "برای شروع می‌توانید از گزینه های زیر استفاده کنید:"],
                ]),
                'custom_text' => null,
                'description' => 'متن خوش آمدگویی برای کاربر - پارامترها: {name} {lastName} {website}'
            ],

            [
                'key' => 'action.start',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'سلام {name}! به ربات آموزشی خوش آمدید'],
                ]),
                'custom_text' => null,
                'description' => 'متن خوش آمدگویی برای کاربر - پارامترها: {name} {lastName}'
            ],
            [
                'key' => 'action.chanel_lock_text',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'برای شروع، لطفا در کانالهای زیر عضو بشوید.'],
                ]),
                'custom_text' => null,
                'description' => 'متن قفل ربات'
            ],

            [
                'key' => 'action.help',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'راهنما'],
                ]),
                'custom_text' => null,
                'description' => 'متن راهنمای دستورات'
            ],
            [
                'key' => 'action.back',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'بازگشت'],
                ]),
                'custom_text' => null,
                'description' => 'متن بازگشت به منوی قبلی'
            ],
            [
                'key' => 'action.process.reply.cancel',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'لغو'],
                ]),
                'custom_text' => null,
                'description' => 'متن لغو در پرداخت'
            ],
            [
                'key' => 'action.process.reply.cancel_done',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'عملیات لغو شد.'],
                ]),
                'custom_text' => null,
                'description' => 'متن تایید لغو عملیات در انتظار مبلغ'
            ],
            [
                'key' => 'action.send_location',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'ارسال موقعیت مکانی'],
                ]),
                'custom_text' => null,
                'description' => 'متن ارسال موقعیت مکانی'
            ],
            [
                'key' => 'action.send_contact',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'ارسال شماره تماس'],
                ]),
                'custom_text' => null,
                'description' => 'متن ارسال شماره تماس'
            ],
            [
                'key' => 'action.mobile_verification.prompt',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'برای خرید، ابتدا باید شماره موبایل خود را تایید کنید.'],
                    ['type' => 'newline'],
                    ['type' => 'text', 'text' => 'لطفاً با دکمه زیر شماره تماس خود را ارسال کنید (فقط شماره متعلق به خودتان پذیرفته می‌شود).'],
                ]),
                'custom_text' => null,
                'description' => 'درخواست تایید موبایل قبل از خرید'
            ],
            [
                'key' => 'action.mobile_verification.prompt_iran_only',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'برای خرید، ابتدا باید شماره موبایل ایران خود را تایید کنید.'],
                    ['type' => 'newline'],
                    ['type' => 'text', 'text' => 'لطفاً با دکمه زیر شماره تماس خود را ارسال کنید. فقط شماره‌های با پیش‌شماره ایران (+98) پذیرفته می‌شوند.'],
                ]),
                'custom_text' => null,
                'description' => 'درخواست تایید موبایل (فقط ایران)'
            ],
            [
                'key' => 'action.mobile_verification.button',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'ارسال شماره تماس برای تایید'],
                ]),
                'custom_text' => null,
                'description' => 'متن دکمه ارسال شماره برای تایید موبایل'
            ],
            [
                'key' => 'action.mobile_verification.success',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'شماره موبایل شما با موفقیت تایید شد. اکنون می‌توانید خرید کنید.'],
                ]),
                'custom_text' => null,
                'description' => 'پیام موفقیت تایید موبایل'
            ],
            [
                'key' => 'action.mobile_verification.already_verified',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'شماره موبایل شما قبلاً تایید شده است.'],
                ]),
                'custom_text' => null,
                'description' => 'پیام تایید قبلی موبایل'
            ],
            [
                'key' => 'error.mobile_verification.required',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'برای خرید باید ابتدا شماره موبایل خود را در ربات تلگرام تایید کنید.'],
                ]),
                'custom_text' => null,
                'description' => 'خطای الزام تایید موبایل'
            ],
            [
                'key' => 'error.mobile_verification.required_iran_only',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'برای خرید باید ابتدا شماره موبایل ایران خود را در ربات تلگرام تایید کنید.'],
                ]),
                'custom_text' => null,
                'description' => 'خطای الزام تایید موبایل ایران'
            ],
            [
                'key' => 'error.mobile_verification.iran_only',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'فقط شماره موبایل با پیش‌شماره ایران (+98) برای تایید پذیرفته می‌شود.'],
                ]),
                'custom_text' => null,
                'description' => 'رد شماره غیرایرانی در تایید موبایل'
            ],
            [
                'key' => 'error.mobile_verification.invalid_contact',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'شماره ارسالی معتبر نیست. لطفاً شماره متعلق به خودتان را از دکمه «ارسال شماره تماس» بفرستید.'],
                ]),
                'custom_text' => null,
                'description' => 'خطای شماره تماس نامعتبر'
            ],
            [
                'key' => 'error.mobile_verification.disabled',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'تایید موبایل در حال حاضر فعال نیست.'],
                ]),
                'custom_text' => null,
                'description' => 'تایید موبایل غیرفعال'
            ],
            [
                'key' => 'error.mobile_verification.not_applicable',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'تایید موبایل برای این حساب کاربری لازم نیست.'],
                ]),
                'custom_text' => null,
                'description' => 'تایید موبایل برای نقش غیرکاربر'
            ],
            [
                'key' => 'action.upload_file',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'آپلود فایل'],
                ]),
                'custom_text' => null,
                'description' => 'متن آپلود فایل'
            ],
            [
                'key' => 'action.send_photo',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'ارسال عکس'],
                ]),
                'custom_text' => null,
                'description' => 'متن ارسال عکس'
            ],
            [
                'key' => 'action.send_photo.success',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => '{name} عزیز عکس شما دریافت شد، منتظر بررسی توسط مدیر ربات باشید.'],
                ]),
                'custom_text' => null,
                'description' => 'متن {name} عزیز عکس شما دریافت شد، منتظر بررسی توسط مدیر ربات باشید. پارامترها: {name}'
            ],
            [
                'key' => 'action.send_photo.success.admin',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'کاربر {account_id} برای شما عکسی ارسال کرده است.'],
                ]),
                'custom_text' => null,
                'description' => 'متن کاربر {account_id} برای شما عکسی ارسال کرده است. پارامترها: {account_id}'
            ],
            [
                'key' => 'action.welcome_back',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'خوش برگشتی {name}! آخرین بازدید شما: {last_visit}'],
                ]),
                'custom_text' => null,
                'description' => 'متن خوش برگشتی برای کاربر - پارامترها: {name} {last_visit}'
            ],
            [
                'key' => 'action.process.on_progress',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'در حال پردازش...'],
                ]),
                'custom_text' => null,
                'description' => 'متن در حال پردازش'
            ],
            [
                'key' => 'action.process.success',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'عملیات با موفقیت انجام شد'],
                ]),
                'custom_text' => null,
                'description' => 'متن عملیات موفق'
            ],
            [
                'key' => 'action.process.failed',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'عملیات با شکست مواجه شد'],
                ]),
                'custom_text' => null,
                'description' => 'متن عملیات شکست خورده'
            ],
            [
                'key' => 'action.process.insufficient_balance',
                'default_text' => json_encode([
                    ['type' => 'bold', 'text' => "موجودی شما کم تر از قیمت بسته انتخابی می باشد. لطفا حساب خود را شارژ بفرمایید. "],
                    ['type' => 'newline'],
                    ['type' => 'text', 'text' => "موجودی شما:"],
                    ['type' => 'text', 'text' => "{user_balance_in_toman}"],
                    ['type' => 'newline'],
                    ['type' => 'text', 'text' => "قیمت بسته:"],
                    ['type' => 'text', 'text' => "{product_price_in_toman}"],
                    ['type' => 'newline'],
                    ['type' => 'text', 'text' => "میزان مبلغ مورد نیاز برای شارژ حساب:"],
                    ['type' => 'text', 'text' => "{difference_in_toman}"],

                ]),
                'custom_text' => null,
                'description' => 'متن عملیات شکست خورده - پارامترها: {user_balance_in_toman} {product_price_in_toman} {difference_in_toman}'
            ],
            [
                'key' => 'action.process.insufficient_balance_with_dollar',
                'default_text' => json_encode([
                    ['type' => 'bold', 'text' => "موجودی شما کم تر از قیمت بسته انتخابی می باشد. لطفا حساب خود را شارژ بفرمایید. "],
                    ['type' => 'newline'],
                    ['type' => 'bold', 'text' => "موجودی شما:"],
                    ['type' => 'text', 'text' => "{user_balance_in_toman} - {user_balance_in_dollar}"],
                    ['type' => 'newline'],
                    ['type' => 'bold', 'text' => "قیمت بسته: {product_price_in_toman}"],
                    ['type' => 'newline'],
                    ['type' => 'text', 'text' => "میزان مبلغ مورد نیاز برای شارژ حساب:"],
                    ['type' => 'text', 'text' => "{difference_in_toman} - {difference_in_dollar}"],

                ]),
                'custom_text' => null,
                'description' => 'متن عملیات شکست خورده - پارامترها: {user_balance_in_toman} {user_balance_in_dollar} {product_price_in_toman} {difference_in_toman} {difference_in_dollar}'
            ],
            [
                'key' => 'action.process.add_online_balance',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'افزایش موجودی کیف پول خود را با انتخاب یکی از گزینه های زیر انجام دهید.'],
                ]),
                'custom_text' => null,
                'description' => 'متن افزایش موجودی کیف پول - پارامترها: {website}'
            ],
            [
                'key' => 'action.process.add_online_balance.zarinpal',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'پرداخت آنلاین با زرین پال'],
                ]),
                'custom_text' => null,
                'description' => 'متن پرداخت آنلاین با زرین پال'
            ],
            [
                'key' => 'action.process.add_online_balance.zarinpal.reply',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'مقدار واریزی خود را وارد کنید. (حداقل 10 هزار تومان)'],
                ]),
                'custom_text' => null,
                'description' => 'متن مقدار واریزی خود را وارد کنید. (حداقل 10 هزار تومان)'
            ],
            [
                'key' => 'action.process.add_online_balance.zarinpal.reply.invalid_amount',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'لطفا مبلغ را به صورت عددی وارد کنید'],
                ]),
                'custom_text' => null,
                'description' => 'متن لطفا مبلغ را به صورت عددی وارد کنید'
            ],
            [
                'key' => 'action.process.add_online_balance.shetab_verify',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'کارت به کارت (تایید خودکار)'],
                ]),
                'custom_text' => null,
                'description' => 'متن کارت به کارت (تایید خودکار)'
            ],
            [
                'key' => 'action.process.add_online_balance.zarinpal.reply.invoice',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'صورت حساب جدید برای پرداخت ایجاد شد، بر روی لینک زیر کلیک کنید. (مهلت اعتبار لینک تنها 10 دقیقه می باشد.)'],
                ]),
                'custom_text' => null,
                'description' => 'متن صورت حساب جدید برای پرداخت ایجاد شد، بر روی لینک زیر کلیک کنید. (مهلت اعتبار لینک تنها 10 دقیقه می باشد.)'
            ],
            [
                'key' => 'action.process.add_online_balance.shetab_verify.reply',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'مبلغ واریزی خود را وارد کنید. (حداقل 10 هزار تومان)'],
                ]),
                'custom_text' => null,
                'description' => 'متن مبلغ واریزی خود را وارد کنید. (حداقل 10 هزار تومان)'
            ],

            [
                'key' => 'action.process.add_online_balance.dollarpay',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'پرداخت با رمز ارز'],
                ]),
                'custom_text' => null,
                'description' => 'متن پرداخت با رمز ارز'
            ],
            [
                'key' => 'action.process.add_online_balance.nowpayments.reply',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'مقدار واریزی خود را وارد کنید. (حداقل 5 دلار)'],
                ]),
                'custom_text' => null,
                'description' => 'متن مقدار واریزی خود را وارد کنید. (حداقل 5 دلار)'
            ],

            [
                'key' => 'action.process.add_online_balance.nowpayments.reply.invoice',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'صورت حساب جدید برای پرداخت ایجاد شد، بر روی لینک زیر کلیک کنید. (مهلت اعتبار لینک تنها 10 دقیقه می باشد.)'],
                ]),
                'custom_text' => null,
                'description' => 'متن صورت حساب جدید برای پرداخت ایجاد شد، بر روی لینک زیر کلیک کنید. (مهلت اعتبار لینک تنها 10 دقیقه می باشد.)'
            ],
            [
                'key' => 'action.process.add_online_balance.cryptomus.reply',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'مقدار واریزی خود را وارد کنید. (حداقل 5 دلار)'],
                ]),
                'custom_text' => null,
                'description' => 'متن مقدار واریزی خود را وارد کنید. (حداقل 5 دلار)'
            ],
            [
                'key' => 'action.process.add_online_balance.cryptomus.reply.invoice',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'صورت حساب جدید برای پرداخت ایجاد شد، بر روی لینک زیر کلیک کنید. (مهلت اعتبار لینک تنها 10 دقیقه می باشد.)'],
                ]),
                'custom_text' => null,
                'description' => 'متن صورت حساب جدید برای پرداخت ایجاد شد، بر روی لینک زیر کلیک کنید. (مهلت اعتبار لینک تنها 10 دقیقه می باشد.)'
            ],



            [
                'key' => 'action.process.add_online_balance.dollarpay.zarinpal',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'پرداخت آنلاین با زرین پال'],
                ]),
                'custom_text' => null,
                'description' => 'متن پرداخت آنلاین با زرین پال'
            ],

            [
                'key' => 'action.process.add_online_balance.dollarpay.nowpayment',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'پرداخت آنلاین با رمز ارز (NowPayments)'],
                ]),
                'custom_text' => null,
                'description' => 'متن پرداخت آنلاین با رمز ارز (NowPayments)'
            ],

            [
                'key' => 'action.process.add_online_balance.dollarpay.cryptomus',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'پرداخت آنلاین بارمز ارز (Cryptomus)'],
                ]),
                'custom_text' => null,
                'description' => 'متن پرداخت آنلاین بارمز ارز (Cryptomus)'
            ],
            [
                'key' => 'action.process.add_online_balance.dollarpay.swappay',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'پرداخت آنلاین با SwapPay (سواپ‌ولت)'],
                ]),
                'custom_text' => null,
                'description' => 'متن دکمه پرداخت آنلاین با SwapPay'
            ],
            [
                'key' => 'action.process.add_online_balance.swappay.reply',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'مقدار دلاری مورد نظر برای پرداخت با SwapPay را وارد کنید:'],
                ]),
                'custom_text' => null,
                'description' => 'متن درخواست مبلغ برای پرداخت SwapPay'
            ],
            [
                'key' => 'action.process.add_online_balance.swappay.reply.invoice',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'صورت حساب جدید برای پرداخت ایجاد شد، بر روی لینک زیر کلیک کنید. (مهلت اعتبار لینک تنها 10 دقیقه می باشد.)'],
                ]),
                'custom_text' => null,
                'description' => 'متن لینک پرداخت SwapPay'
            ],

            [
                'key' => 'action.process.add_offline_balance_option_and_online_balance',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'همچنین می توانید با انتخاب یکی از گزینه های زیر نسبت به پرداخت اقدام نمایید.'],
                ]),
                'custom_text' => null,
                'description' => 'متن همچنین می توانید با انتخاب یکی از گزینه های زیر نسبت به پرداخت اقدام نمایید.'
            ],

            [
                'key' => 'action.process.add_offline_balance_option',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'پرداخت آفلاین'],
                ]),
                'custom_text' => null,
                'description' => 'متن پرداخت آفلاین'
            ],
            [
                'key' => 'action.process.add_offline_balance_option.image',
                'default_text' => json_encode([
                    ['type' => 'bold', 'text' => "لطفا مبلغ را به این شماره کارت واریز کنید و رسید پرداختی را ارسال کنید. "],
                    ['type' => 'newline'],
                    ['type' => 'bold', 'text' => "شماره کارت:"],
                    ['type' => 'text', 'text' => "{merchant_id}"],
                ]),
                'custom_text' => null,
                'description' => 'متن درخواست واریز به شماره کارت - پارامترها: {merchant_id}'
            ],

            [
                'key' => 'action.process.shetab_verify.new_invoice',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'لطفا مبلغ را بدون کم یا زیاد کردن به این شماره کارت واریز کنید و منتظر تایید خودکار سیستم باشید.'],
                    ['type' => 'newline'],
                    ['type' => 'text', 'text' => "شماره کارت: "],
                    ['type' => 'code', 'text' => "{merchant_id}"],
                    ['type' => 'newline'],
                    ['type' => 'text', 'text' => "مبلغ: "],
                    ['type' => 'code', 'text' => "{amount} "],
                    ['type' => 'text', 'text' => " تومان برابر با "],
                    ['type' => 'code', 'text' => "{amount}0 "],
                    ['type' => 'text', 'text' => "ریال"],

                ]),
                'custom_text' => null,
                'description' => 'متن درخواست واریز به شماره کارت - پارامترها: {merchant_id} {amount}'
            ],

            [
                'key' => 'action.account.balance_added',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'کیف پول شما به مبلغ {amount} تومان شارژ شد.'],
                ]),
                'custom_text' => null,
                'description' => 'متن کیف پول شما به مبلغ {amount} تومان شارژ شد.'
            ],
            [
                'key' => 'action.process.success_buy',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'اشتراک با موفقیت خریداری شد'],
                ]),
                'custom_text' => null,
                'description' => 'متن اشتراک با موفقیت خریداری شد'
            ],

            [
                'key' => 'action.process.failed_buy',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'خرید اشتراک با شکست مواجه شد'],
                ]),
                'custom_text' => null,
                'description' => 'متن خرید اشتراک با شکست مواجه شد'
            ],

            [
                'key' => 'action.buy_subscription',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'خرید اشتراک'],
                ]),
                'custom_text' => null,
                'description' => 'متن خرید اشتراک'
            ],

            [
                'key' => 'action.buy_subscription_by_location',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'خرید اشتراک بر اساس مکان'],
                ]),
                'custom_text' => null,
                'description' => 'متن خرید اشتراک بر اساس مکان'
            ],

            [
                'key' => 'action.buy_subscription_by_location.location',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'مکان سرور را انتخاب کنید.'],
                ]),
                'custom_text' => null,
                'description' => 'متن مکان سرور را انتخاب کنید.'
            ],

            [
                'key' => 'action.buy_subscription.select_package',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'بسته خود را انتخاب کنید.'],
                ]),
                'custom_text' => null,
                'description' => 'متن بسته خود را انتخاب کنید.'
            ],

            [
                'key' => 'action.help.add_ballance',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'لطفا کیف پول خود را با انتخاب یکی از گزینه های زیر شارژ کنید.'],
                ]),
                'custom_text' => null,
                'description' => 'متن لطفا کیف پول خود را با انتخاب یکی از گزینه های زیر شارژ کنید.'
            ],
            [
                'key' => 'action.help.message',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'لطفا یکی از گزینه های زیر را انتخاب کنید.'],
                ]),
                'custom_text' => null,
                'description' => 'متن لطفا یکی از گزینه های زیر را انتخاب کنید.'
            ],
            [
                'key' => 'action.help.using_subscription',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'به کمک نیاز داری؟ یک گزینه را انتخاب بکن'],
                ]),
                'custom_text' => null,
                'description' => 'متن به کمک نیاز داری؟ یک گزینه را انتخاب بکن'
            ],
            [
                'key' => 'action.help.appDownload',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'دانلود برنامه'],
                ]),
                'custom_text' => null,
                'description' => 'متن دانلود برنامه.'
            ],

            [
                'key' => 'action.help.appDownload.os',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'سیستم عامل خود را انتخاب کنید.'],
                ]),
                'custom_text' => null,
                'description' => 'متن سیستم عامل خود را انتخاب کنید.'
            ],
            [
                'key' => 'action.help.appDownload.app',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'برنامه خود را انتخاب کنید.'],
                ]),
                'custom_text' => null,
                'description' => 'متن برنامه خود را انتخاب کنید.'
            ],

            [
                'key' => 'action.help.appDownload.app.name_description',
                'default_text' => json_encode([
                    ['type' => 'bold', 'text' => "نام برنامه: {name}"],
                    ['type' => 'newline'],
                    ['type' => 'bold', 'text' => "توضیحات برنامه: {description}"],
                    ['type' => 'newline'],
                    ['type' => 'bold', 'text' => "لینک دانلود:"],
                    ['type' => 'newline'],
                    ['type' => 'text', 'text' => "{download_link}"],
                    ['type' => 'newline'],
                    ['type' => 'bold', 'text' => "لینک آموزش استفاده:"],
                    ['type' => 'newline'],
                    ['type' => 'text', 'text' => "{how_to_use}"],
                    ['type' => 'newline'],
                    ['type' => 'bold', 'text' => "لینک آموزش یوتیوب:"],
                    ['type' => 'newline'],
                    ['type' => 'text', 'text' => "{youtube_link}"],
                ]),
                'custom_text' => null,
                'description' => 'متن نام برنامه: {name} توضیحات برنامه: {description} لینک دانلود: {download_link} لینک آموزش استفاده: {how_to_use} لینک آموزش یوتیوب: {youtube_link}'
            ],
            [
                'key' => 'action.help.support',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'پشتیبانی'],
                ]),
                'custom_text' => null,
                'description' => 'متن پشتیبانی'
            ],
            [
                'key' => 'action.help.support.title',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'یکی از گزینه های پشتیبانی زیر را انتخاب کنید.'],
                ]),
                'custom_text' => null,
                'description' => 'متن یکی از گزینه های پشتیبانی زیر را انتخاب کنید.'
            ],

            [
                'key' => 'action.subscription.hiddify',
                'default_text' => json_encode([
                    ['type' => 'bold', 'text' => "خرید شما با موفقیت انجام شد"],
                    ['type' => 'newline'],
                    ['type' => 'bold', 'text' => "لینک پنل شما برای مشاهده اطلاعات بسته خریداری شده:"],
                    ['type' => 'link', 'text' => "لینک پنل", 'url' => "{panel_link}"],
                    ['type' => 'newline'],
                    ['type' => 'bold', 'text' => "لینک سابسکریپشن:"],
                    ['type' => 'newline'],
                    ['type' => 'code', 'text' => "{subscription_link}"],
                    ['type' => 'newline'],
                    ['type' => 'text', 'text' => "همچینین شما می توانید QRCode ارسال شده را اسکن نمایید. در صورت نیاز به راهنمایی بر روی آموزش استفاده از لینک سابسکریپشن کلیک کنید."],
                ]),
                'custom_text' => null,
                'description' => 'متن خرید شما با موفقیت انجام شد - پارامترها: {panel_link} {subscription_link}'
            ],
            [
                'key' => 'action.subscription.sanaei_without_subscription',
                'default_text' => json_encode([
                    ['type' => 'bold', 'text' => "خرید شما با موفقیت انجام شد"],
                    ['type' => 'newline'],
                    ['type' => 'bold', 'text' => "کانفیگ شما:"],
                    ['type' => 'newline'],
                    ['type' => 'code', 'text' => "{uuid}"],
                    ['type' => 'newline'],
                    ['type' => 'text', 'text' => "همچینین شما می توانید QRCode ارسال شده را اسکن نمایید. در صورت نیاز به راهنمایی بر روی آموزش استفاده از لینک سابسکریپشن کلیک کنید."],
                ]),
                'custom_text' => null,
                'description' => 'متن خرید شما با موفقیت انجام شد - پارامترها: {uuid}'
            ],
            [
                'key' => 'action.subscription.sanaei',
                'default_text' => json_encode([
                    ['type' => 'bold', 'text' => "خرید شما با موفقیت انجام شد"],
                    ['type' => 'newline'],
                    ['type' => 'bold', 'text' => "شناسه کانفیگ شما:"],
                    ['type' => 'newline'],
                    ['type' => 'code', 'text' => "{uuid}"],
                    ['type' => 'newline'],
                    ['type' => 'text', 'text' => "همچینین شما می توانید QRCode ارسال شده را اسکن نمایید. در صورت نیاز به راهنمایی بر روی آموزش استفاده از لینک سابسکریپشن کلیک کنید."],
                ]),
                'custom_text' => null,
                'description' => 'متن خرید سنایی - پارامترها: {uuid}'
            ],
            [
                'key' => 'action.subscription.marzban',
                'default_text' => json_encode([
                    ['type' => 'bold', 'text' => "خرید شما با موفقیت انجام شد"],
                    ['type' => 'newline'],
                    ['type' => 'bold', 'text' => "لینک سابسکریپشن:"],
                    ['type' => 'newline'],
                    ['type' => 'link', 'text' => "لینک ساب", 'url' => "{subscription_link}"],
                    ['type' => 'newline'],
                    ['type' => 'code', 'text' => "{subscription_link}"],
                    ['type' => 'newline'],
                    ['type' => 'text', 'text' => "همچنین می‌توانید QRCode ارسال شده را اسکن نمایید. در صورت نیاز به راهنمایی بر روی آموزش استفاده از لینک سابسکریپشن کلیک کنید."],
                ]),
                'custom_text' => null,
                'description' => 'متن خرید مرزبان - پارامترها: {panel_link} {subscription_link}'
            ],
            [
                'key' => 'action.subscription.marzban.link',
                'default_text' => json_encode([
                    ['type' => 'bold', 'text' => "کانفیگ:"],
                    ['type' => 'newline'],
                    ['type' => 'code', 'text' => "{link}"],
                ]),
                'custom_text' => null,
                'description' => 'متن ارسال لینک کانفیگ مرزبان - پارامترها: {link}'
            ],
            [
                'key' => 'action.subscription.marzban.help',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'جهت نیاز به راهنمایی بر روی یکی از گزینه‌های زیر کلیک کنید.'],
                ]),
                'custom_text' => null,
                'description' => 'متن راهنمای بعد از ارسال کانفیگ مرزبان'
            ],
            [
                'key' => 'action.test_account.marzban',
                'default_text' => json_encode([
                    ['type' => 'bold', 'text' => "اکانت آزمایشی شما فعال شد"],
                    ['type' => 'newline'],
                    ['type' => 'bold', 'text' => "لینک سابسکریپشن:"],
                    ['type' => 'newline'],
                    ['type' => 'link', 'text' => "لینک ساب", 'url' => "{subscription_link}"],
                    ['type' => 'newline'],
                    ['type' => 'code', 'text' => "{subscription_link}"],
                    ['type' => 'newline'],
                    ['type' => 'text', 'text' => "می‌توانید QRCode ارسال شده را اسکن نمایید."],
                ]),
                'custom_text' => null,
                'description' => 'متن اکانت آزمایشی مرزبان - پارامترها: {subscription_link}'
            ],
            [
                'key' => 'action.subscription.pasarguard',
                'default_text' => json_encode([
                    ['type' => 'bold', 'text' => "خرید شما با موفقیت انجام شد"],
                    ['type' => 'newline'],
                    ['type' => 'bold', 'text' => "لینک سابسکریپشن:"],
                    ['type' => 'newline'],
                    ['type' => 'link', 'text' => "لینک ساب", 'url' => "{subscription_link}"],
                    ['type' => 'newline'],
                    ['type' => 'code', 'text' => "{subscription_link}"],
                    ['type' => 'newline'],
                    ['type' => 'text', 'text' => "همچنین می‌توانید QRCode ارسال شده را اسکن نمایید. در صورت نیاز به راهنمایی بر روی آموزش استفاده از لینک سابسکریپشن کلیک کنید."],
                ]),
                'custom_text' => null,
                'description' => 'متن خرید پاسارگارد - پارامترها: {panel_link} {subscription_link}'
            ],
            [
                'key' => 'action.subscription.pasarguard.link',
                'default_text' => json_encode([
                    ['type' => 'bold', 'text' => "کانفیگ:"],
                    ['type' => 'newline'],
                    ['type' => 'code', 'text' => "{link}"],
                ]),
                'custom_text' => null,
                'description' => 'متن ارسال لینک کانفیگ پاسارگارد - پارامترها: {link}'
            ],
            [
                'key' => 'action.subscription.pasarguard.help',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'جهت نیاز به راهنمایی بر روی یکی از گزینه‌های زیر کلیک کنید.'],
                ]),
                'custom_text' => null,
                'description' => 'متن راهنمای بعد از ارسال کانفیگ پاسارگارد'
            ],
            [
                'key' => 'action.test_account.pasarguard',
                'default_text' => json_encode([
                    ['type' => 'bold', 'text' => "اکانت آزمایشی شما فعال شد"],
                    ['type' => 'newline'],
                    ['type' => 'bold', 'text' => "لینک سابسکریپشن:"],
                    ['type' => 'newline'],
                    ['type' => 'link', 'text' => "لینک ساب", 'url' => "{subscription_link}"],
                    ['type' => 'newline'],
                    ['type' => 'code', 'text' => "{subscription_link}"],
                    ['type' => 'newline'],
                    ['type' => 'text', 'text' => "می‌توانید QRCode ارسال شده را اسکن نمایید."],
                ]),
                'custom_text' => null,
                'description' => 'متن اکانت آزمایشی پاسارگارد - پارامترها: {subscription_link}'
            ],
            [
                'key' => 'action.buy_history.title',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'سابقه خرید'],
                ]),
                'custom_text' => null,
                'description' => 'متن سابقه خرید'
            ],
            [
                'key' => 'action.buy_history.no_history',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'شما هیچ سابقه خریدی ندارید'],
                ]),
                'custom_text' => null,
                'description' => 'متن شما هیچ سابقه خریدی ندارید'
            ],
            [
                'key' => 'action.buy_history.history',
                'default_text' => json_encode([
                    ['type' => 'bold', 'text' => 'سابقه خرید شما:'],
                    ['type' => 'newline'],
                    ['type' => 'text', 'text' => 'نام: {name}'],
                    ['type' => 'newline'],
                    ['type' => 'text', 'text' => 'بسته: {category_name}'],
                    ['type' => 'newline'],
                    ['type' => 'text', 'text' => 'تاریخ شروع: {start_date}'],
                    ['type' => 'newline'],
                    ['type' => 'text', 'text' => 'تاریخ انقضا: {expire_date}'],
                    ['type' => 'newline'],
                    ['type' => 'text', 'text' => 'میزان حجم بسته: {usage_limit_GB} GB'],
                    ['type' => 'newline'],
                    ['type' => 'text', 'text' => 'میزان حجم مصرف شده: {usage_GB} GB'],
                    ['type' => 'newline'],
                    ['type' => 'text', 'text' => 'وضعیت بسته: {enable}'],
                    ['type' => 'newline'],

                    ['type' => 'bold', 'text' => "لینک پنل شما برای مشاهده اطلاعات بسته خریداری شده:"],
                    ['type' => 'link', 'text' => "لینک پنل", 'url' => "{panel_link}"],
                    ['type' => 'newline'],
                    ['type' => 'bold', 'text' => "لینک سابسکریپشن:"],
                    ['type' => 'newline'],
                    ['type' => 'code', 'text' => "{subscription_link}"],
                    ['type' => 'newline'],
                    ['type' => 'text', 'text' => "همچینین شما می توانید QRCode ارسال شده را اسکن نمایید. در صورت نیاز به راهنمایی بر روی آموزش استفاده از لینک سابسکریپشن کلیک کنید."],
                ]),
                'custom_text' => null,
                'description' => 'متن سابقه خرید شما: - پارامترها: {name} {category_name} {panel_link} {subscription_link} {start_date} {expire_date} {usage_limit_GB} {usage_GB} {enable}'
            ],
            [
                'key' => 'action.history.buttun.recharge',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'تمدید بسته'],
                ]),
                'custom_text' => null,
                'description' => 'متن تمدید بسته'
            ],
            [
                'key' => 'action.history.buttun.remark',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'تغییر نام بسته'],
                ]),
                'custom_text' => null,
                'description' => 'متن تغییر نام بسته'
            ],
            [
                'key' => 'action.history.buttun.delete',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'حذف بسته'],
                ]),
                'custom_text' => null,
                'description' => 'متن دکمه حذف بسته در سابقه خرید'
            ],
            [
                'key' => 'action.delete_history.confirm',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'آیا از حذف بسته «{name}» اطمینان دارید؟'],
                    ['type' => 'newline'],
                    ['type' => 'text', 'text' => 'این عمل غیرقابل بازگشت است و دسترسی شما قطع خواهد شد.'],
                ]),
                'custom_text' => null,
                'description' => 'متن تایید حذف بسته - پارامترها: {name}'
            ],
            [
                'key' => 'action.delete_history.confirm_button',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'تایید و حذف'],
                ]),
                'custom_text' => null,
                'description' => 'متن دکمه تایید حذف بسته'
            ],
            [
                'key' => 'action.delete_history.cancel_button',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'انصراف'],
                ]),
                'custom_text' => null,
                'description' => 'متن دکمه انصراف از حذف بسته'
            ],
            [
                'key' => 'action.delete_history.success',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'بسته «{name}» با موفقیت حذف شد.'],
                ]),
                'custom_text' => null,
                'description' => 'متن موفقیت حذف بسته - پارامترها: {name}'
            ],
            [
                'key' => 'action.delete_history.failed',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'خطا در حذف بسته. لطفاً دوباره تلاش کنید یا با پشتیبانی تماس بگیرید.'],
                ]),
                'custom_text' => null,
                'description' => 'متن خطا در حذف بسته'
            ],
            [
                'key' => 'action.recharge.success',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'شارژ مجدد با موفقیت انجام شد'],
                ]),
                'custom_text' => null,
                'description' => 'متن شارژ مجدد با موفقیت انجام شد'
            ],
            [
                'key' => 'action.recharge.confirm',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'آیا از تمدید بسته {package} به مبلغ {price} تومان مطمئن هستید؟'],
                ]),
                'custom_text' => null,
                'description' => 'تایید تمدید بسته - پارامترها: {package} {price}'
            ],
            [
                'key' => 'action.recharge.button_confirm',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'تایید تمدید'],
                ]),
                'custom_text' => null,
                'description' => 'دکمه تایید تمدید'
            ],
            [
                'key' => 'action.account.details',
                'default_text' => json_encode([
                    ['type' => 'bold', 'text' => 'اطلاعات حساب شما:'],
                    ['type' => 'newline'],
                    ['type' => 'text', 'text' => 'نام کاربری: {username}'],
                    ['type' => 'newline'],
                    ['type' => 'text', 'text' => 'نام: {name} {last_name}'],
                    ['type' => 'newline'],
                    ['type' => 'text', 'text' => 'آیدی عددی: {account_id}'],
                    ['type' => 'newline'],
                    ['type' => 'text', 'text' => 'موجودی کیف پول: {balance}'],
                    ['type' => 'newline'],
                    ['type' => 'text', 'text' => 'موجودی دلاری: {balance_in_dollar}'],
                    ['type' => 'newline'],
                    ['type' => 'text', 'text' => 'موجودی کیف همکاری: {referral_balance}'],
                    ['type' => 'newline'],
                    ['type' => 'text', 'text' => 'امتیاز باشگاه مشتریان: {loyalty_balance}'],

                ]),
                'custom_text' => null,
                'description' => 'متن اطلاعات حساب شما: - پارامترها: {username} {name} {last_name} {account_id} {balance} {balance_in_dollar} {referral_balance} {loyalty_balance}'
            ],
            [
                'key' => 'action.account.additional_options.loyalty_history',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'تاریخچه امتیاز ⭐'],
                ]),
                'custom_text' => null,
                'description' => 'دکمه تاریخچه امتیاز باشگاه مشتریان'
            ],
            [
                'key' => 'action.account.loyalty_history.title',
                'default_text' => json_encode([
                    ['type' => 'bold', 'text' => 'باشگاه مشتریان'],
                    ['type' => 'newline'],
                    ['type' => 'text', 'text' => 'موجودی امتیاز شما: {balance}'],
                    ['type' => 'newline'],
                    ['type' => 'text', 'text' => 'ارزش هر امتیاز: {toman_per_point} تومان'],
                    ['type' => 'newline'],
                    ['type' => 'text', 'text' => 'آخرین فعالیت‌ها:'],
                ]),
                'custom_text' => null,
                'description' => 'عنوان تاریخچه امتیاز — پارامترها: {balance} {toman_per_point}'
            ],
            [
                'key' => 'action.account.loyalty_history.no_records',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'هنوز هیچ امتیازی برای شما ثبت نشده است.'],
                ]),
                'custom_text' => null,
                'description' => 'متن خالی بودن تاریخچه امتیاز'
            ],
            [
                'key' => 'action.account.additional_options',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'گزینه های اضافه'],
                ]),
                'custom_text' => null,
                'description' => 'متن گزینه های اضافه'
            ],
            [
                'key' => 'action.account.additional_options.transactions',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'تراکنش ها'],
                ]),
                'custom_text' => null,
                'description' => 'متن تراکنش ها'
            ],
            [
                'key' => 'action.account.additional_options.sub_accounts',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'زیر مجموعه ها'],
                ]),
                'custom_text' => null,
                'description' => 'متن زیر مجموعه ها'
            ],
            [
                'key' => 'action.account.additional_options.add_balance',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'افزایش موجودی'],
                ]),
                'custom_text' => null,
                'description' => 'متن افزایش موجودی'
            ],
            [
                'key' => 'action.account.transactions.title',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'سابقه تراکنش ها'],
                ]),
                'custom_text' => null,
                'description' => 'متن سابقه تراکنش ها'
            ],
            [
                'key' => 'action.account.transactions.no_transactions',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'شما هیچ تراکنشی ندارید'],
                ]),
                'custom_text' => null,
                'description' => 'متن شما هیچ تراکنشی ندارید'
            ],
            [
                'key' => 'action.account.sub_accounts.title',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'سابقه زیر مجموعه ها'],
                ]),
                'custom_text' => null,
                'description' => 'متن سابقه زیر مجموعه ها'
            ],
            [
                'key' => 'action.account.sub_accounts.no_sub_accounts',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'شما هیچ زیر مجموعه ای ندارید'],
                ]),
                'custom_text' => null,
                'description' => 'متن شما هیچ زیر مجموعه ای ندارید'
            ],
            [
                'key' => 'action.remark.title',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'لطفا نام جدید بسته خود را وارد کنید یا عبارت "لغو" را ارسال کنید:'],
                ]),
                'custom_text' => null,
                'description' => 'متن لطفا نام جدید بسته خود را وارد کنید یا عبارت "لغو" را ارسال کنید:'
            ],
            [
                'key' => 'action.remark.success',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'نام بسته با موفقیت تغییر کرد.'],
                ]),
                'custom_text' => null,
                'description' => 'متن نام بسته با موفقیت تغییر کرد.'
            ],
            [
                'key' => 'action.remark.cancel',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'تغییر نام بسته لغو شد.'],
                ]),
                'custom_text' => null,
                'description' => 'متن لغو تغییر نام بسته'
            ],
            [
                'key' => 'action.web.generate_auto_login_link',
                'default_text' => json_encode([
                    ['type' => 'bold', 'text' => "لینک ورود به پنل: "],
                    ['type' => 'link', 'text' => "لینک", 'url' => "{link}"],
                    ['type' => 'newline'],
                    ['type' => 'text', 'text' => "نام کاربری:"],
                    ['type' => 'newline'],
                    ['type' => 'code', 'text' => "{username}"],
                    ['type' => 'newline'],
                    ['type' => 'text', 'text' => "رمز عبور:"],
                    ['type' => 'newline'],
                    ['type' => 'code', 'text' => "{password}"],
                    ['type' => 'newline'],
                    ['type' => 'text', 'text' => "با این اطلاعات می توانید وارد پنل شوید."],
                ]),
                'custom_text' => null,
                'description' => 'متن لینک ورود به پنل: - پارامترها: {link} {username} {password}'
            ],
            [
                'key' => 'action.web.auto_login_link',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'لینک ورود سریع به پنل: '],
                ]),
                'custom_text' => null,
                'description' => 'متن لینک ورود سریع به پنل: '
            ],
            [
                'key' => 'action.help.faq',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'سوالات متداول'],
                ]),
                'custom_text' => null,
                'description' => 'متن سوالات متداول'
            ],
            [
                'key' => 'action.test_account.success',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'اکانت آزمایشی با موفقیت فعال شد.'],
                ]),
                'custom_text' => null,
                'description' => 'متن اکانت آزمایشی با موفقیت فعال شد.'
            ],
            [
                'key' => 'action.help.giftCard',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'کد گیفت کارت را وارد کنید.'],
                ]),
                'custom_text' => null,
                'description' => 'متن کد گیفت کارت را وارد کنید.'
            ],
            [
                'key' => 'action.help.giftCard.success',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'کد گیفت کارت با موفقیت اعمال شد.'],
                ]),
                'custom_text' => null,
                'description' => 'متن کد گیفت کارت با موفقیت اعمال شد.'
            ],
            [
                'key' => 'error.giftCard.not_found',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'کد گیفت کارت یافت نشد.'],
                ]),
                'custom_text' => null,
                'description' => 'متن کد گیفت کارت یافت نشد.'
            ],
            [
                'key' => 'error.giftCard.already_used',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'کد گیفت کارت قبلا استفاده شده است.'],
                ]),
                'custom_text' => null,
                'description' => 'متن کد گیفت کارت قبلا استفاده شده است.'
            ],
            [
                'key' => 'error.giftCard.too_many_attempts',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => "شما به دلیل تلاش‌های ناموفق زیاد به مدت {minutes} دقیقه نمی‌توانید گیفت کارت جدید وارد کنید."],
                ]),
                'custom_text' => null,
                'description' => 'متن جلوگیری برای حدس زدن گیفت کارت ها - پارامترها: {minutes}'
            ],
            [
                'key' => 'error.giftCard.blocked',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'شما به دلیل وارد کردن گیفت کارت نامعتبر بیش از ۳ بار، به مدت یک ساعت نمی‌توانید گیفت کارت جدید وارد کنید.'],
                ]),
                'custom_text' => null,
                'description' => 'متن شما به دلیل وارد کردن گیفت کارت نامعتبر بیش از ۳ بار، به مدت یک ساعت نمی‌توانید گیفت کارت جدید وارد کنید.'
            ],
            [
                'key' => 'action.referral.title',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'کسب درآمد از فروش بسته ها'],
                ]),
                'custom_text' => null,
                'description' => 'متن کسب درآمد از فروش بسته ها'
            ],
            [
                'key' => 'action.referral.text',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'شما می توانید از لینک زیر برای دعوت دوستان خود استفاده کنید: {link}'],
                    ['type' => 'newline'],
                    ['type' => 'text', 'text' => 'درصد کسب درآمد شما {percent}% است.'],
                ]),
                'custom_text' => null,
                'description' => 'متن شما می توانید از لینک زیر برای دعوت دوستان خود استفاده کنید: {link} - پارامترها: {link} {percent}'
            ],

            [
                'key' => 'error.test_account.exist',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'اکانت آزمایشی از قبل برای شما فعال شده است، می توانید از سابقه خرید به اطلاعات آن دسترسی داشته باشید.'],
                ]),
                'custom_text' => null,
                'description' => 'متن اکانت آزمایشی از قبل برای شما فعال شده است، می توانید از سابقه خرید به اطلاعات آن دسترسی داشته باشید.'
            ],
            [
                'key' => 'error.blocked_user',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'اکانت شما توسط مدیر مسدود شده است.'],
                ]),
                'custom_text' => null,
                'description' => 'متن اکانت شما توسط مدیر مسدود شده است.'
            ],
            [
                'key' => 'error.server_error',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'خطایی رخ داده است'],
                ]),
                'custom_text' => null,
                'description' => 'متن خطایی رخ داده است'
            ],
            [
                'key' => 'action.block_user.success',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'کاربر با موفقیت مسدود شد.'],
                ]),
                'custom_text' => null,
                'description' => 'متن کاربر با موفقیت مسدود شد.'
            ],
            [
                'key' => 'action.unblock_user.success',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'کاربر با موفقیت رفع مسدودیت شد.'],
                ]),
                'custom_text' => null,
                'description' => 'متن کاربر با موفقیت رفع مسدودیت شد.'
            ],

            [
                'key' => 'action.promo.enter_code',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'کد تخفیف خود را وارد کنید:'],
                ]),
                'custom_text' => null,
                'description' => 'درخواست ورود کد تخفیف'
            ],
            [
                'key' => 'action.promo.invalid',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'کد تخفیف نامعتبر است. {reason}'],
                ]),
                'custom_text' => null,
                'description' => 'کد تخفیف نامعتبر - پارامتر: {reason}'
            ],
            [
                'key' => 'action.promo.applied',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'کد {code} اعمال شد.'],
                    ['type' => 'newline'],
                    ['type' => 'text', 'text' => 'تخفیف: {discount} تومان'],
                    ['type' => 'newline'],
                    ['type' => 'text', 'text' => 'مبلغ نهایی: {final_price} تومان'],
                ]),
                'custom_text' => null,
                'description' => 'کد تخفیف اعمال شد - پارامترها: {code} {discount} {final_price}'
            ],
            [
                'key' => 'action.promo.button',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'کد تخفیف دارم'],
                ]),
                'custom_text' => null,
                'description' => 'متن دکمه کد تخفیف'
            ],
            [
                'key' => 'action.promo.confirm_buy',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'تایید خرید با تخفیف'],
                ]),
                'custom_text' => null,
                'description' => 'تایید خرید با کد تخفیف'
            ],
            [
                'key' => 'action.buy_subscription.button_confirm',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'تایید خرید'],
                ]),
                'custom_text' => null,
                'description' => 'دکمه تایید خرید بدون تخفیف'
            ],
            [
                'key' => 'action.promo.confirm_recharge',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'تایید تمدید با تخفیف'],
                ]),
                'custom_text' => null,
                'description' => 'تایید تمدید با کد تخفیف'
            ],
            [
                'key' => 'action.buy_subscription.confirm',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'آیا از خرید بسته {package} به مبلغ {price} تومان مطمئن هستید؟'],
                ]),
                'custom_text' => null,
                'description' => 'تایید خرید بسته - پارامترها: {package} {price}'
            ],
            [
                'key' => 'action.upsell.offer',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'پیشنهاد ویژه!'],
                    ['type' => 'newline'],
                    ['type' => 'text', 'text' => 'بسته {current_package} ({current_price} تومان)'],
                    ['type' => 'newline'],
                    ['type' => 'text', 'text' => 'یا بسته {upsell_package} ({upsell_price} تومان)'],
                ]),
                'custom_text' => null,
                'description' => 'پیشنهاد upsell - پارامترها: {current_package} {upsell_package} {current_price} {upsell_price}'
            ],
            [
                'key' => 'action.upsell.buy_upsell',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'خرید {package}'],
                ]),
                'custom_text' => null,
                'description' => 'دکمه خرید بسته پیشنهادی - پارامتر: {package}'
            ],
            [
                'key' => 'action.upsell.continue_current',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'ادامه با {package}'],
                ]),
                'custom_text' => null,
                'description' => 'دکمه ادامه با بسته فعلی - پارامتر: {package}'
            ],
            [
                'key' => 'recovery.package_selected.message',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'بسته {package_name} را انتخاب کردید ولی خرید را تکمیل نکردید. برای ادامه خرید دکمه زیر را بزنید.'],
                ]),
                'custom_text' => null,
                'description' => 'یادآوری خرید ناتمام - پارامتر: {package_name}'
            ],
            [
                'key' => 'recovery.insufficient_balance.message',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'موجودی شما برای خرید بسته {package_name} کافی نیست. کیف پول خود را شارژ کنید.'],
                ]),
                'custom_text' => null,
                'description' => 'یادآوری موجودی ناکافی - پارامتر: {package_name}'
            ],
            [
                'key' => 'recovery.recharge.message',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'تمدید بسته {package_name} را فراموش نکنید!'],
                ]),
                'custom_text' => null,
                'description' => 'یادآوری تمدید ناتمام - پارامتر: {package_name}'
            ],
            [
                'key' => 'recovery.button.buy',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'ادامه خرید'],
                ]),
                'custom_text' => null,
                'description' => 'دکمه ادامه خرید در یادآوری'
            ],
            [
                'key' => 'recovery.button.add_balance',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'شارژ کیف پول'],
                ]),
                'custom_text' => null,
                'description' => 'دکمه شارژ کیف پول در یادآوری'
            ],
            [
                'key' => 'cron.expired.message',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'کاربر گرامی بسته {product_text} منقضی شده است. لطفا برای تمدید بسته مجددا اقدام کنید.'],
                ]),
                'custom_text' => null,
                'description' => 'پیام خودکار انقضای بسته - پارامترها: {product_name} {category_name} {product_text}'
            ],
            [
                'key' => 'cron.expiring_soon.message',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'کاربر گرامی تنها {days_left} روز دیگر از بسته {product_text} باقی مانده است.'],
                ]),
                'custom_text' => null,
                'description' => 'پیام خودکار نزدیک انقضا - پارامترها: {product_name} {category_name} {product_text} {days_left}'
            ],
            [
                'key' => 'cron.usage_high.message',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'کاربر گرامی شما بیشتر از {usage_percent} درصد از بسته {product_text} را مصرف کرده‌اید.'],
                ]),
                'custom_text' => null,
                'description' => 'پیام خودکار مصرف بالا - پارامترها: {product_name} {category_name} {product_text} {usage_percent}'
            ],
            [
                'key' => 'cron.button.renew',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'تمدید بسته'],
                ]),
                'custom_text' => null,
                'description' => 'متن دکمه تمدید در پیام‌های خودکار'
            ],
            [
                'key' => 'error.menu.not_found',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'گزینه ای یافت نشد'],
                ]),
                'custom_text' => null,
                'description' => 'متن گزینه ای یافت نشد'
            ],
            [
                'key' => 'error.user_not_found',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'کاربر یافت نشد'],
                ]),
                'custom_text' => null,
                'description' => 'متن کاربر یافت نشد'
            ],

            [
                'key' => 'error.action.not_found',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'عملیات نامعتبر است'],
                ]),
                'custom_text' => null,
                'description' => 'متن عملیات نامعتبر است'
            ],
            [
                'key' => 'error.command.not_found',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'دستور نامعتبر است. برای مشاهده لیست دستورات از /help استفاده کنید.'],
                ]),
                'custom_text' => null,
                'description' => 'متن دستور نامعتبر'
            ],
            [
                'key' => 'error.product_not_rechargeable',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'این بسته قابلیت شارژ ندارد'],
                ]),
                'custom_text' => null,
                'description' => 'متن این بسته قابلیت شارژ ندارد'
            ],


        ];
    }
    public function seed()
    {
        try {
            \Log::info('Seeding CustomText table');
            // check if we are on local
            if (env('APP_ENV') == 'local') {
                // delete all the data
                CustomText::truncate();
            }
            // check if the table is empty
            if (CustomText::count() == 0) {
                $data = $this->getSeedData();

                CustomText::insert($data);

                \Log::info('CustomText table seeded successfully');
                return true;
            }

            return $this->syncMissingSeedKeys();
        } catch (\Throwable $th) {
            \Log::info("sdaaa: $th");
            return;
        }

    }

    public function syncMissingSeedKeys(): bool
    {
        $inserted = false;

        foreach ($this->getSeedData() as $data) {
            if (! CustomText::where('key', $data['key'])->exists()) {
                CustomText::create($data);
                $inserted = true;
                \Log::info('CustomText missing key added: ' . $data['key']);
            }
        }

        return $inserted;
    }
    public function getText($key, $variables = [])
    {
        try {
            $text = $this->customText->getText($key, $variables);
            if (is_string($text) && json_validate($text)) {
                return json_decode($text, true);
            }
            return $text;
        } catch (\Throwable $th) {
            \Log::info("getText: $key");
            $this->syncMissingSeedKeys();
            if ($this->seedSingleKey($key)) {
                try {
                    $text = $this->customText->getText($key, $variables);
                    if (is_string($text) && json_validate($text)) {
                        return json_decode($text, true);
                    }
                    return $text;
                } catch (\Throwable $seeded) {
                    \Log::warning('getText after seed still failed: ' . $key . ' ' . $seeded->getMessage());
                }
            }

            return '';
        }
    }

    public function setText($key, $text)
    {
        try {
            // check if the key is in the database
            if ($this->customText->where('key', $key)->exists()) {
                return $this->customText->setText($key, $text);
            }
            return false;
        } catch (\Throwable $th) {
            \Log::info("setText: $key => $text");
            return false;
        }
    }
    // set test by request
    public function setTest(Request $request)
    {
        try {
            // validate request
            $validator = Validator::make($request->all(), [
                'key' => 'required',
                'text' => 'required',
            ]);
            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 422);
            }
            $key = $request['key'];
            $text = $request['text'];
            $this->setText($key, $text);
            return response()->json(['message' => 'Text set successfully'], 200);
        } catch (\Throwable $th) {
            \Log::info("setTest: $th");
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }

    public function seedSingleKey($key)
    {
        try {
            \Log::info("Seeding single key: $key");

            // بررسی اینکه آیا کلید در جدول وجود دارد
            $exists = CustomText::where('key', $key)->exists();

            if (!$exists) {
                // پیدا کردن داده مربوط به کلید از آرایه اصلی
                $keyData = null;
                $allData = $this->getSeedData();

                foreach ($allData as $data) {
                    if ($data['key'] === $key) {
                        $keyData = $data;
                        break;
                    }
                }

                if ($keyData) {
                    CustomText::create($keyData);
                    \Log::info("Key $key added to CustomText table");
                    return true;
                } else {
                    \Log::info("Key $key not found in seed data");
                    return false;
                }
            }

            \Log::info("Key $key already exists in CustomText table");
            return true;
        } catch (\Throwable $th) {
            \Log::error("Error seeding single key $key: " . $th->getMessage());
            return false;
        }
    }

}
