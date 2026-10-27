# راهنمای تست Discord Activity

این راهنما برای تست واقعی Activity داخل کانال صوتی دیسکورد است. Activity همان صحنه فانوس و در بسته از تست انفرادی `/play` است و فقط روی مسیر تست، بدون ورود به حساب، باز می‌شود.

## آدرس‌ها

| کاربرد | آدرس |
| --- | --- |
| صفحه Activity | `/discord/activity` |
| حالت تست | `/discord/activity?test=1` |
| شروع روایت در حالت تست | `POST /discord/activity/begin?test=1` |
| لینک عمومی که الان جواب می‌دهد | `https://biblical-immediate-vocal-albuquerque.trycloudflare.com/discord/activity?test=1` |
| لینک داخل دیسکورد، بعد از ذخیره URL Mapping | `https://1561830278056509511.discordsays.com/discord/activity?test=1` |
| پورتال برنامه LoreSpinner | `https://discord.com/developers/applications/1561830278056509511` |

صفحه سفید داخل کانال صوتی صفحه هشدار ngrok است. دامنه `ngrok-free.dev` برای درخواست مرورگر یک صفحه میانی می‌فرستد، و دیسکورد استایل و اسکریپت آن صفحه را مسدود می‌کند، پس پنل سفید می‌ماند. Target را از `patriot-theme-unheated.ngrok-free.dev` به هاست کلادفلر زیر عوض کنید.

تونل کلادفلر باید روشن بماند. اگر خاموش شود، آدرس عمومی عوض می‌شود و mapping قبلی می‌میرد.

دکمه Launch ریشه سایت را باز می‌کند. اگر آدرس پارامتر `frame_id` دیسکورد را داشته باشد، همان ریشه حالا خود Activity را در حالت تست نشان می‌دهد.

## حالت تست چه می‌کند

با `?test=1`:

- برچسب طلایی **Test only** بالای صفحه دیده می‌شود.
- متن صحنه این است: «Solo test. You are alone in a small room. A lantern hangs by one shut door.»
- دو دکمه هست: **Open the door** و **Take the lantern**.
- دکمه Begin narration به `POST /discord/activity/begin?test=1` می‌رود و همان صحنه در را، بدون ورود، برمی‌گرداند.
- پاسخ موفق JSON است: صحنه `gate`، همان متن، و همان دو انتخاب.
- بعد از پاسخ، صفحه می‌نویسد: «Test only. Scene returned without login.»

بدون `?test=1` این مسیر تست وجود ندارد. `POST /discord/activity/begin` پاسخ **404** می‌دهد.

مسیر عادی `POST /user/games/{game}/begin` عوض نشده است. هنوز بازیکن واردشده می‌خواهد که مالک بازی باشد، پروفایلش کامل باشد، و اشتراک فعال داشته باشد. مهمان به صفحه ورود `user.authentication.login.create` هدایت می‌شود.

## تست‌های خودکار

هشت تست Activity در `tests/Feature/DiscordActivityTest.php` این رفتار را پوشش می‌دهند:

1. صفحه Activity متن فانوس و در، و دکمه‌های Open the door و Take the lantern را نشان می‌دهد.
2. با `?game=` معتبر، Begin به `/user/games/{game}/begin` اشاره می‌کند.
3. با `?test=1` برچسب Test only دیده می‌شود و آدرس Begin همان مسیر تست است.
4. `POST /discord/activity/begin?test=1` بدون ورود، صحنه `gate` را برمی‌گرداند.
5. همان POST بدون `?test=1` پاسخ 404 می‌دهد.
6. `POST /user/games/{game}/begin` بدون ورود هنوز به صفحه لاگین می‌رود.
7. شناسه بازی نامعتبر نادیده گرفته می‌شود و در صفحه چاپ نمی‌شود.
8. باز شدن ریشه سایت با `frame_id` دیسکورد، همان Activity تست را نشان می‌دهد، نه صفحه اصلی.

## ۱. باز کردن لینک تست

1. مطمئن شوید Sail و تونل کلادفلر روشن هستند. مقصد تونل `localhost:8088` است و هاست عمومی `biblical-immediate-vocal-albuquerque.trycloudflare.com`.
2. برای دیدن صفحه بیرون از دیسکورد این آدرس را باز کنید:

   `https://biblical-immediate-vocal-albuquerque.trycloudflare.com/discord/activity?test=1`

3. این آدرس را در کروم باز نکنید اگر می‌خواهید تست داخل دیسکورد باشد. بعد از عوض کردن URL Mapping، Activity را از کانال صوتی باز کنید.

## ۲. چیزی که باید ببینید

1. برچسب **Test only**.
2. متن: یک اتاق کوچک، یک فانوس، و یک در بسته.
3. دکمه‌های **Open the door** و **Take the lantern**.
4. با زدن Begin narration، ورود خواسته نمی‌شود و نتیجه «Test only. Scene returned without login.» می‌آید.
5. Open the door به راهرو و زنگ می‌رود. Take the lantern فانوس و یادداشت روی دسته را نشان می‌دهد. Read the note یا Ring the bell تست را تمام می‌کند.

اگر برچسب Test only نیست، `?test=1` در آدرس نیست و Begin مسیر تست را صدا نمی‌زند.

## ۳. تنظیم Discord Developer Portal

ذخیره این تنظیم از اینجا انجام نشد. درخواست با توکن ربات رد شد: **403** با پیام «Bots cannot use this endpoint». این endpoint توکن کاربر مالک برنامه را می‌خواهد، نه توکن ربات. در همان لحظه Activities هم خاموش بود.

این کار را خودتان در پورتال انجام دهید:

1. [صفحه برنامه LoreSpinner](https://discord.com/developers/applications/1561830278056509511) را با حساب مالک برنامه باز کنید.
2. از منوی چپ، **Activities** و بعد **URL Mappings** را باز کنید.
3. یک mapping اضافه کنید:
   - Prefix: `/`
   - Target: `biblical-immediate-vocal-albuquerque.trycloudflare.com`
   - پروتکل `https://` را در Target ننویسید.
4. ذخیره کنید.
5. **Activities** و بعد **Settings** را باز کنید و **Enable Activities** را روشن کنید. با این کار فرمان پیش‌فرض **Launch** ساخته می‌شود.

تا این mapping ذخیره نشود، لینک `1561830278056509511.discordsays.com` به صفحه Activity نمی‌رسد.

## ۴. اجرا از کانال صوتی

1. دیسکورد را باز کنید و وارد سرور تست شوید. تا برنامه توزیع نشده، فقط مالک و تیم توسعه می‌توانند Activity را ببینند.
2. یک کانال صوتی را باز کنید و به آن بپیوندید.
3. App Launcher را باز کنید و **LoreSpinner** را پیدا کنید. اگر در لیست نبود، نامش را جستجو کنید.
4. Launch را بزنید. اگر Target هنوز هاست ngrok باشد، پنل سفید می‌ماند. Target باید `biblical-immediate-vocal-albuquerque.trycloudflare.com` باشد.
5. بعد از ذخیره، Activity را یک بار ببندید و دوباره از همان کانال صوتی باز کنید.
6. برچسب Test only، متن اتاق، و دکمه‌های Open the door و Take the lantern باید دیده شوند. Begin نباید به صفحه ورود برود.

اگر پنل هنوز سفید است، URL Mapping را دوباره چک کنید. هاست `ngrok-free.dev` را برندارید.

## ۵. مطمئن شوید مسیر عادی هنوز ورود می‌خواهد

1. بدون `?test=1` این آدرس را باز کنید: `/discord/activity`
   برچسب Test only نباید باشد.
2. `POST /discord/activity/begin` را بدون `?test=1` بزنید. پاسخ باید **404** باشد.
3. `POST /user/games/{game}/begin` را بدون نشست کاربر بزنید. باید به صفحه ورود برود.
4. این مسیر هنوز سه شرط دارد: کاربر وارد شده باشد، مالک همان بازی باشد، پروفایلش کامل باشد، و اشتراک فعال داشته باشد.

حالت تست فقط Begin مخصوص Activity را دور می‌زند. `/play` و شروع داستان در سایت را باز نمی‌کند.
