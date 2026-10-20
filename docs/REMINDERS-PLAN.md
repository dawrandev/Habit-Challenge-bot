# Eslatmalar va kechagi hisobot — reja

> Foydalanuvchi muammosi: odatni bajardi, lekin isbot yuborishni unutdi — kun yopildi,
> bajarilmagan deb hisoblandi va ochko yo'qoldi. Uch yechim birga ishlaydi:
> **odat vaqtida eslatma**, **kechki eslatma** va **kechagi kunga hisobot yuborish**.

## 1. Odat vaqti (ixtiyoriy) → eslatma

- Har odatga **ixtiyoriy** vaqt qo'yiladi (`challenges.remind_at`, `HH:MM`, Toshkent).
  Masalan "🌅 Erta turish — 07:00".
- Shu vaqtda bot bajaruvchiga yozadi: *"⏰ 07:00 — Erta turish vaqti! Bajarib bo'lgach
  isbot yubor 📸"* va **ilovani o'sha duel/missiyada ochadigan tugma** bilan.
- Faqat **bugun navbatda** bo'lsa va bugun uchun isbot **hali yuborilmagan** bo'lsa.
- Duelda — ikkala o'yinchiga, missiyada — faqat egaga (guvoh bajarmaydi).
- Vaqt duel/missiya yaratishda, keyin odatni tahrirlashda qo'yiladi/olib tashlanadi.
  Bugungi ro'yxatda odat yonida `⏰ 07:00` ko'rinadi.

## 2. Kechki eslatma — 23:00

- Bugun navbatda bo'lgan, lekin isboti yuborilmagan (yoki rad etilib tuzatilmagan)
  odatlar **bitta xabarda** ro'yxat bo'lib keladi. Hammasi yuborilgan bo'lsa — xabar yo'q.
- Vaqt sozlanadi: `REMINDER_EVENING_AT=23:00`.

## 3. Kechagi kunga hisobot yuborish

- Bugun **kechagi kun** uchun ham isbot yuborish mumkin (faqat bir kun orqaga).
  Isbot oddiy tartibda raqib/guvoh tomonidan tekshiriladi.
- Kecha "o'tkazib yuborilgan" deb belgilangan kun yuborilib tasdiqlansa, jarima
  (−0.5) o'rniga bajarish (+1) bo'ladi.
- Battle/missiya sahifasida va Asosiy sahifada **"🕊 Kechagi hisobotlar"** bo'limi:
  yuborilmaganlar ro'yxati va "Yuborish" tugmasi. Isbot ekrani "kechagi kun uchun" deb aytadi.
- **Ertalabki eslatma** (`REMINDER_LATE_AT=10:00`): kecha yuborilmagan odat bo'lsa —
  *"Kecha bajargan bo'lsang, bugun kun oxirigacha yuborishing mumkin"*.
- Duel/missiya **oxirgi kuni** uchun ham imkon bo'lishi uchun natija bir kun keyin
  hisoblanadi (oxirgi kundan keyingi kun — kechagi hisobotlar uchun). Yopilishda
  tekshirilmay qolgan isbotlar avtomatik tasdiqlanadi (tekshirmaslik — o'yinchining aybi emas).

## 4. Texnik

| Qism | O'zgarish |
|---|---|
| Migratsiya | `challenges.remind_at`; `reminder_logs` (kim/qaysi eslatma/qaysi kun — takror yubormaslik) |
| Servis | `ReminderService` (odat, kechki, ertalabki eslatma), `LateProofs` (kechagi kunlar) |
| Buyruq | `battle:remind` — har daqiqa; o'tkazib yuborilgan daqiqani ham 2 soat ichida yetkazadi |
| Isbot | `POST /completions` ixtiyoriy `day` oladi (bugun yoki kecha) |
| API | `GET /late-tasks` — barcha duel/missiyalardagi kechagi yuborilmaganlar |
| Yopish | duel/missiya `end_date` dan keyingi kun o'tgach yopiladi |
| Telegram | eslatmalar Mini App tugmasi bilan |

## 5. Qadamlar

1. Backend: migratsiya, kechagi kun mantiqi, yopish kechikishi + testlar
2. Backend: eslatma servisi, buyruq, cron + testlar
3. Frontend: vaqt maydoni, ⏰ belgisi, kechagi hisobotlar bo'limi, isbot ekrani, 4 til
4. SPEC/README, build, commit/push
