# راهنمای تست Discord Activity (چندنفره)

Activity داخل کانال صوتی دیسکورد حالا همان جریان چندنفره سایت را اجرا می‌کند. هر بازیکن با حساب دیسکوردش، به‌صورت مهمان، وارد می‌شود و همه افراد یک کانال صوتی در یک لابی قرار می‌گیرند.

## جریان بازی

1. اولین نفر Activity را از کانال صوتی باز می‌کند. دیسکورد اجازه `identify` را می‌پرسد.
2. سرور کد OAuth را با `DISCORD_CLIENT_SECRET` عوض می‌کند، کاربر دیسکورد را می‌خواند، و با توکن ربات چک می‌کند که این کاربر واقعاً در همین instance هست.
3. یک کاربر مهمان (بدون username، با ایمیل `discord-<id>@pending.invalid`) ساخته یا پیدا می‌شود و وارد می‌شود.
4. اگر این کانال هنوز لابی ندارد، اولین نفر داستان و حالت (Arbiter یا Roulette) را انتخاب می‌کند و میزبان می‌شود.
5. بقیه که Activity را باز می‌کنند خودکار به همان لابی می‌روند (هر instance دیسکورد = یک لابی، ستون `multiplayer_lobbies.discord_instance_id`).
6. از اینجا صفحه‌های معمول سایت (`Multiplayer/Lobby` و `Arbiter`) اجرا می‌شوند: آماده شدن، شروع با حداقل ۳ نفر، و بازی.

اگر داستان کانال قبلاً شروع شده باشد، نفر تازه پیام «شروع شده» می‌بیند و وارد نمی‌شود.

## آدرس‌ها

| کاربرد | آدرس |
| --- | --- |
| صفحه Activity | `GET /discord/activity` (ریشه سایت با `frame_id` هم همین را نشان می‌دهد) |
| ورود با دیسکورد | `POST /discord/activity/session` |
| لابی کانال (polling) | `GET /discord/activity/lobby` |
| ساخت لابی کانال | `POST /discord/activity/lobby` |

## تنظیمات `.env`

```
DISCORD_APPLICATION_ID=...
DISCORD_CLIENT_SECRET=...        # از صفحه OAuth2 برنامه
DISCORD_BOT_TOKEN=...            # برای چک کردن حضور در instance
SESSION_SAME_SITE=none
SESSION_SECURE_COOKIE=true
SESSION_PARTITIONED_COOKIE=true
BROADCAST_CONNECTION=reverb
```

Activity داخل iframe شخص ثالث است. بدون تنظیمات کوکی بالا، نشست کاربر نگه داشته نمی‌شود.

## تنظیم Discord Developer Portal

1. **OAuth2**: Client Secret را بردارید. Redirect لازم نیست، اما اگر پورتال خواست، `https://127.0.0.1` کافی است.
2. **Activities → URL Mappings**:
   - `/` → هاست عمومی سایت (مثلاً تونل کلادفلر به `localhost:8088`)
   - `/reverb` → هاست عمومی Reverb (تونل جدا به `localhost:8080`)
   - پروتکل `https://` را در Target ننویسید.
3. **Activities → Settings**: Enable Activities را روشن کنید.

داخل دیسکورد، مرورگر فقط به `https://<application-id>.discordsays.com` دسترسی دارد. برای همین، بعد از ورود، میان‌افزار `UseDiscordActivityOrigin` همه آدرس‌ها (ریدایرکت‌ها و فایل‌های Vite) را به همین دامنه می‌برد، و Echo سوکت را از مسیر `/reverb` باز می‌کند.

## اجرا

1. `vendor/bin/sail up -d` (همراه سرویس `reverb`).
2. `vendor/bin/sail npm run build`. سرور dev ویت داخل دیسکورد کار نمی‌کند.
3. `vendor/bin/sail artisan migrate`.
4. سه حساب دیسکورد در یک کانال صوتی سرور تست، Activity را باز کنند.

## تست‌های خودکار

`tests/Feature/DiscordActivityTest.php` ورود مهمان، رد کاربر خارج از instance، ساخت یک لابی برای هر کانال، پیوستن بقیه، و دامنه discordsays را پوشش می‌دهد.

```
vendor/bin/sail artisan test --compact tests/Feature/DiscordActivityTest.php
```
