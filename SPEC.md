# Battle Bot — Loyiha Spetsifikatsiyasi (SPEC)

> **Bir jumlada:** Odat har kuni jonli kamera bilan isbotlanadi va uni **boshqa odam** tekshiradi.
> Ikki rejim: **⚔️ Duel** (ikkalangiz bajarasiz, bir-biringizni tekshirasiz, g'olib chiqadi) va
> **🎯 Missiya** (sen bajarasan, do'sting faqat guvoh bo'ladi) — §16.
> **Falsafa:** odatlarni *bellashuv* tarzida shakllantirish; odat **uzilib qolmasligi** kerak
> (davomiylik — asosiy qadriyat).

Bu hujjat `/grill-me` interviyusi orqali qabul qilingan barcha qarorlarning yagona
manbasidir (source of truth). Kod va DB nomlari inglizcha, UI matni o'zbekcha.

---

## 1. Texnologiya to'plami (Stack)

| Qatlam | Tanlov | Izoh |
|--------|--------|------|
| Backend + Bot | **Python — FastAPI + aiogram (bitta xizmat)** | Umumiy model/logika, bir jarayon |
| Bot rejimi | **Long polling** | Webhook domaini shart emas |
| Frontend | **React + Tailwind + Telegram Web App SDK** | Telegram **Mini App** (ichida ochiladi) |
| DB | **PostgreSQL** | Relatsion model |
| Scheduler | **APScheduler** (FastAPI ichida) | Kunlik yopish + eslatmalar |
| Rasm ombori | **Telegram serveri** (`file_id`) | Backend proxy orqali ko'rsatiladi |

**Deploy eslatmasi:** Mini App uchun **public HTTPS URL shart** (Telegram talabi) — pullik
domain shart emas, Railway/Render bepul HTTPS subdomeni yetarli. Bot polling'da bo'lgani
uchun **webhook domaini kerak emas**.

---

## 2. Auth va onboarding

- **Auth:** Telegram Mini App `initData` imzo tekshiruvi (backend bot-token bilan verify qiladi).
  Login/parol yo'q.
- **Profil:** Telegram'dan **avtomatik** (ism, username, avatar). Ro'yxatdan o'tish yo'q.
- **Raqib topish:** **Deep link** asosiy — `t.me/<Bot>?start=battle_<token>`. Do'st havolani
  bossa botga ham qo'shiladi, taklifni ham ko'radi. *(Username orqali qidirish — v1'da qo'shimcha
  kirish yo'li.)*

---

## 3. Asosiy tushunchalar (Domain model)

### Battle
- **Konteyner** — ichida ko'p challenge; barcha challenge ballari **bitta umumiy hisobga** qo'shiladi.
- **1v1** (hozir), lekin model **`battle_participants` pivot** orqali quriladi (guruh battle — keyin).
- **Belgilangan davr** (fixed): `start_date` + `end_date`. Davr tugagach → g'olib → **arxiv**.
  - Davr variantlari: **1 hafta / 2 hafta / 1 oy / erkin sana**.
- **Timezone:** bitta sobit — **Asia/Tashkent (UTC+5)**. Kun 24:00 (Toshkent)da tugaydi.

### Challenge
- **Simmetrik:** yaratuvchi taklif qiladi, lekin qabul qilinsa **ikkala o'yinchiga** amal qiladi.
  Ikkalasi bajaradi, **bir-birini tekshiradi** (cross-verification).
- **Chastota (cadence):** ikki tur — **Har kuni** yoki **Muayyan hafta kunlari** (Du/Cho/Ju...).
- **Hisobot turi:** **binar** (bajardi / bajarmadi) + **rasm isboti**.
- **Nomi:** shablonlar kutubxonasi (📖 o'qish, 🏃 sport, 💧 suv...) **+ erkin matn**.
- **Isbot mezoni:** YO'Q — tekshiruvchi o'zi qaror qiladi (sub'ektiv, nizo mexanizmi qoplaydi).
- **`start_date` har challenge'da alohida:**
  - Battle boshidagilar = battle `start_date`.
  - O'rtada qo'shilganlar = **o'sha kuni** kuchga kiradi (default; "ertadan" ham mumkin).
    Faqat oldinga hisoblanadi, retroaktiv **emas**.
- **O'rtada qo'shish:** ikkala tomon rozi bo'lsa. Battle boshidagilar **locked**.

### Ishtirokchi qo'shilishi (Invitation)
- Yaratuvchi: davr + boshlang'ich challenge'lar + boshlanish sanasini tanlaydi.
- Do'st: **"hammasi yoki hech narsa"** — barchasini qabul qiladi yoki rad etadi (muzokara yo'q).
- **Ikki tomonlama qabul shart.** Rad yoki 24s javobsizlik → taklif bekor.

---

## 4. Scoring (ball tizimi) — ⚠️ ANIQ QOIDALAR

> Bu bo'lim eng muhim — noto'g'ri hisoblanmasin.

- **Bajarish (tasdiqlangan):** **+1** ochko.
- **O'tkazib yuborish** (navbatdagi kun, kun oxirида tasdiqlangan bajarish YO'Q): **−0.5** ochko.
  - "Tasdiqlangan bajarish yo'q" = umuman yubormadi **YOKI** yuborib rad etildi va tuzatmadi.
  - **Rad etilgani ham jarima** (kun oxirida tuzatilmasa).
- ~~**Minimal ball: 0**~~ — ⚠️ **BEKOR QILINDI** (commit `21f785b`): pol olib tashlandi, ball
  manfiy bo'lishi mumkin. Sabab: pol bajarilgan ballni yashirib qo'yardi. Kod = manba.
- **Kun oxiri = Toshkent 24:00.** O'sha kungача **qayta yuborish mumkin** (rad etilsa yaxshiroq rasm).
- **G'olib:** davr oxirida **barcha challenge'lar ballari yig'indisi eng ko'p** bo'lgan o'yinchi.
  - Har challenge alohida g'olibi **yo'q** — faqat umumiy yig'indi.
  - UI'да har challenge bo'yicha taqsimot **ko'rsatiladi** (ma'lumot uchun), lekin hisob umumiy.
- **Durang (tie):** ballar teng bo'lsa → **kim ko'proq kun bajargan** (jami tasdiqlangan bajarish)
  yutadi. U ham teng bo'lsa (deyarli imkonsiz) → **durang**.

**Konfiguratsiya:** scoring qiymatlari sozlanadigan (`points_per_completion=1`,
`penalty_per_miss=0.5`, `score_floor=0`) — kelajakda **streak bonusi** oson qo'shilsin.

---

## 5. Tekshiruv oqimi (Verification)

1. O'yinchi jonli kamera bilan rasm yuboradi → raqibga **darhol notification**.
2. Tekshiruvchining **24 soat** vaqti bor: **✅ tasdiq / ❌ rad**.
3. **Tekshirmasa (deadline o'tsa) → avtomatik TASDIQ** (foydaga shubha o'yinchiga —
   jarima faqat o'z harakatsizliging uchun, boshqaning e'tiborsizligi uchun emas).
4. Har bir tekshiruv qarori **to'liq log qilinadi** (audit trail) + "tekshirmaslik statistikasi".

### Nizo (Dispute)
- Rad etilsam → **"Nizo"** tugmasi → tekshiruvchiga qayta ko'rish uchun qaytadi (notification).
- Tekshiruvchi **fikrini o'zgartirsa** (tasdiqlasa) → jarima bekor, **+1**.
- **O'zgartirmasa** → jarima qoladi, lekin **"bahsli rad"** deb qayd etiladi (dalil to'planadi).
- **Nizoga ham javob bermasa → avtomatik tasdiq** (Savol 11 mantiqi).
- Nizo **kun tugagunicha** hal bo'lishi kerak (scoring cron shunga qaraydi).

---

## 6. Battle hayot sikli (Lifecycle)

- **O'rtada challenge qo'shish:** o'zaro rozilik bilan, faqat oldinga (§3).
- **Tashlab ketish (quit):**
  - **O'zaro rozilik bilan bekor** → jazosiz, hisobga ta'sirsiz.
  - **Bir tomonlama chiqish → forfeit** (chiquvchi yutqazadi, raqib avtomatik g'olib).
- **G'olib e'loni:** natija ekrani ("G'olib: Ali 24:19") + bot xabari + **arxiv**.
- **Rematch:** natija ekranida "Yana bir bor?" — xuddi shu shartlar bilan yangi battle taklifi.

---

## 7. Notifikatsiyalar

**Transaksion (hodisaga bog'liq):** taklif keldi · qabul/rad · raqib hisobot yubordi (tekshir) ·
hisoboting tasdiq/rad · nizo · battle tugadi · rematch · chat xabari.

**Eslatmalar (reminder):**
- **Kunlik hisobot eslatmasi:** aqlli — **faqat hali bajarmaganlarga**, kun oxiriga yaqin
  (**20:00 va 22:00**, Toshkent). *(Shaxsiy eslatma vaqti — v1'da qo'shiladi.)*
- **Tekshiruv eslatmasi:** deadline yaqinlashganda ("tekshir, N soat qoldi").
- **Tinch soatlar:** **22:00–08:00** push yo'q (oxirgi eslatma 22:00).

---

## 8. Mini App sahifalari (IA) — 5 tab

| Tab | Vazifa |
|-----|--------|
| 🏠 **Asosiy** | Faol battle'lar ro'yxati (kartochka: raqib, hisob, qolgan vaqt, 🔴/🟡/🟢 holat). "＋ Yangi battle". |
| ⚔️ **Battle detali** | Leaderboard, challenge'lar, **tracker grid** (kunlar × challenge'lar ✅/❌/⚪), hisobot/tekshirish, chatga o'tish. *(kartochkadan ochiladi)* |
| 🔔 **Faoliyat** | "Hozir nima qilishim kerak" — tekshiruvlar + hisobotlar + so'nggi voqealar. |
| 💬 **Chat** | Har battle uchun alohida chat. |
| 👤 **Profil** | Statistika + tugallangan battle arxivi + sozlamalar. |

- **Tracker:** battle ichida (global emas).
- **Chat:** matn + **voqealar tasmasi** birga ("Ali o'qishni bajardi ✅", "Vali rad etdi ❌", nizo).
  Real-time emas — **polling** (v1). *(Rasm/stiker — v1'da qo'shiladi.)*

---

## 9. Isbot rasmi (anti-cheat)

- **Faqat jonli kamera** — `navigator.mediaDevices.getUserMedia` bilan Mini App ichida jonli
  kamera oqimi → 📸 tugma → kadr olinadi. **Fayl-picker YO'Q → galereya imkonsiz.**
- Rasm bilan **server timestamp** (yuborilgan vaqt) ko'rsatiladi.
- Oxirgi qalqon — **inson tekshiruvchi** (ekrandan olingan soxta rasmni ko'radi).
- Cheklov: kamera ruxsati kerak; nodir eski qurilmada `getUserMedia` bo'lmasa — xabar.

---

## 10. Rasm oqimi (texnik)

1. Mini App jonli kameradan kadr oladi → backend'ga yuklaydi.
2. Backend rasmni **maxsus "saqlash" kanaliga** `sendPhoto` qiladi → **`file_id`** oladi.
3. Backend faqat **`file_id`**ni DB'ga yozadi (rasm bayti diskda saqlanmaydi).
4. Ko'rsatishda: backend **proxy** qiladi (`getFile` → Telegram'dan olib uzatadi) —
   bot token oshkor bo'lmaydi, `getFile` URL 1 soatlik muammosi chetlanadi.

---

## 11. Versiya rejasi

### v1 (birinchi to'liq versiya)
Deep link 1v1 battle · davr tanlash · shablonli+erkin challenge (har kuni/muayyan kunlar) ·
jonli kamera hisobot · inson tekshiruvi (24s auto-tasdiq) · nizo bayrog'i · scoring (+1/−0.5/0 pol) ·
o'rtada challenge qo'shish · quit=forfeit · tiebreaker · aqlli eslatmalar · 5 tab ·
natija+rematch+arxiv · **+ username qidirish · + shaxsiy eslatma vaqti · + chatda rasm**.

### v1.1 (darhol keyin)
Badge/trophy/global reyting · to'liq nizo tizimi (adolatlilik reytingi).

### Keyingi versiyalar
Guruh battle (3+) · streak bonusi · chatda stiker/real-time · "haftada N marta" chastota.

---

## 12. Ochiq/kelgusi qarorlar
- Streak formulasi (konfiguratsiya tayyor).
- Badge berish qoidalari (v1.1).
- Push infratuzilma detallari (bot `sendMessage`).

---

## 13. Dizayn tizimi — "ARENA"

Yo'nalish: **1v1 odat-dueli** ruhi (sport afishasi / arena tablosi), AI-default'lardan (krem+serif,
qora+neon, gazeta-hairline) qochib. Vizual asos — **har o'yinchining o'z rangi** va **tug-of-war** tablosi.

### Rang tokenlari
| Token | Hex | Rol |
|-------|-----|-----|
| `--bg` | `#17131C` | Chuqur ko'mir-sliva fon |
| `--surface` | `#211A28` | Kartochka |
| `--surface-2` | `#2C2233` | Ko'tarilgan yuza |
| `--line` | `#3A2F44` | Hairline chegara |
| `--you` | `#F6B01E` | **SEN** — issiq oltin (doim shu rang) |
| `--rival` | `#7C5CFF` | **RAQIB** — elektr-siyohrang (doim shu rang) |
| `--approve` | `#3FD07A` | Tasdiq |
| `--reject` | `#FF5A5F` | Rad |
| `--text` | `#F3EEF8` | Asosiy matn |
| `--muted` | `#A79BB4` | Ikkinchi darajali matn |

### Tipografika (ko'p tilli — Lotin + Kirill + Turk belgilar shart)
- **Display / tablo raqamlari:** **Archivo** (Expanded, 800–900) — futbolka/afisha ruhi.
- **Body:** **Inter**.
- **Raqam / data:** **JetBrains Mono** (tabular).

### Signature elementlar
- **Tug-of-war momentum bari:** oltin ↔ siyohrang; kim oldinda — bar o'sha tomonга suriladi.
- **Streak zanjiri** (keyin): ketma-ket kunlar zanjir/ip sifatida.
- **O'yinchi ident:** SEN doim oltin, RAQIB doim siyohrang — butun app bo'ylab izchil.

### Sifat pol (quality floor)
Mobil-birinchi (Telegram webview), klaviatura fokusi ko'rinadi, `prefers-reduced-motion` hurmat,
Telegram light/dark bilan uyg'un (lekin app o'z Arena identligini saqlaydi).

---

## 14. Ko'p tillilik (i18n)

- **Tillar:** 🇬🇧 EN · 🇷🇺 RU · 🇹🇷 TR · 🇺🇿 UZ. Default: Telegram `initData.language_code` dan aniqlanadi,
  qo'llab-quvvatlanmasa → EN. Profilда qo'lda o'zgartirish mumkin.
- **Frontend:** `react-i18next` + JSON locale fayllar (`/locales/{en,ru,tr,uz}.json`).
- **Bot:** aiogram i18n (Fluent/gettext) — bot xabarlari ham 4 tilда.
- **Tarjima doirasi:**
  - UI matni (tugma, sahifa, xabar) → to'liq tarjima qilinadi.
  - **Challenge shablonlari** (📖 o'qish, 🏃 sport...) → 4 tilда tarjima qilinadi (kutubxona).
  - **Foydalanuvchi kiritган erkin matn** (challenge nomi, isbot talabi, chat) → tarjima QILINMAYDI,
    o'zi kiritган tilда saqlanadi.
- **Kirill (RU)** uchun barcha shriftlar Kirillni qo'llab-quvvatlaydi (Archivo, Inter, JetBrains Mono).

---

## 15. Kutubxonalar va templatelar (qo'lda kam yozamiz)

> Prinsip: sinovdan o'tган template/kutubxonalardan foydalanamiz, boshdan yozmaymiz.

**Frontend:**
- **Vite + React + TypeScript** (`npm create vite`).
- **Tailwind CSS** + **shadcn/ui** (Radix asosidagi tayyor komponentlar: Button, Dialog, Tabs, Avatar...).
- **Telegram Mini App:** `@telegram-apps/sdk-react` (rasmiy SDK) / `@telegram-apps/create-mini-app` template.
- **i18n:** `react-i18next`.
- **State/data:** `@tanstack/react-query` (server holati).

**Backend + Bot:**
- **FastAPI** + **SQLModel/SQLAlchemy** + **Alembic** (migratsiya) — `full-stack-fastapi-template` uslubi.
- **aiogram 3.x** (bot) — FastAPI bilan bitta jarayonда, **polling**.
- **initData verify:** tayyor kutubxona (`init-data-py` yoki aiogram web-app util).
- **APScheduler** (kunlik yopish + eslatmalar).

**Infra:**
- **PostgreSQL** (Docker yoki bepul cloud — Railway/Render/Neon).
- Deploy: Railway/Render (bepul HTTPS subdomen — Mini App uchun).

---

## 16. 🎯 MISSIYA rejimi (Quest) — asimmetrik javobgarlik

> **Muammo:** duel simmetrik — challenge ikkala o'yinchiga tegishli. Lekin bir odamga
> kerak bo'lgan odat ikkinchisiga kerak bo'lmasligi mumkin. Do'sting sen bilan birga
> yugurishni xohlamasligi mumkin, ammo **seni kuzatishga** rozi bo'ladi.
>
> **Yechim:** rollar ajratiladi — **ega bajaradi, guvoh tekshiradi.**

### 16.1 Farqlar jadvali

| | ⚔️ Duel (Battle) | 🎯 Missiya (Quest) |
|---|---|---|
| Ishtirokchi | 2 raqib | 1 ega + 1 guvoh |
| Simmetriya | simmetrik | **asimmetrik** |
| Odat kimniki | ikkalasiniki | **faqat eganiki** |
| Kim isbot yuboradi | ikkalasi | **faqat ega** |
| Kim tekshiradi | bir-birini (kesishgan) | **faqat guvoh** |
| Ball | +1 / −0.5, g'olib | ball yo'q — **bajarish foizi** |
| Yakun | g'olib e'lon qilinadi | **maqsadga yetildimi** (achieved / missed) |
| Challenge qo'shish | ikki tomon roziligi shart | **ega o'zi qo'shadi** (odat uniki) |

### 16.2 Hayot sikli

- **Yaratish:** ega nom, davr (7/14/30/60 kun), **maqsad foizi** (50/70/80/90/100) va
  odatlarni tanlaydi. Missiya **darhol boshlanadi** — guvohni kutmaydi.
- **Guvoh:** deep-link `t.me/<Bot>?start=quest_<token>` orqali qo'shiladi.
  - Guvoh **aynan bitta**. Ega o'ziga guvoh bo'la olmaydi. Joy band bo'lsa — rad.
  - **Guvoh yo'q bo'lsa ham missiya ishlaydi:** isbotlar 24 soatdan keyin
    avtomatik tasdiqlanadi (§5 mantiqi — o'z harakatsizliging uchun jazolanmaysan).
- **To'xtatish (abandon):** ega istalgan vaqtda to'xtatadi. **Jazo yo'q** — yutqazadigan
  raqib yo'q. Statistika saqlanadi.
- **Yakun:** `end_date` o'tgach cron missiyani yopadi va natijani qo'yadi:
  `bajarish foizi >= maqsad` → **achieved**, aks holda **missed**. Ikkala tomonga xabar.

### 16.3 Statistika (ball emas)

Atamalar — **slot** = (odat × navbatdagi kun) juftligi:

| Atama | Ta'rif |
|-------|--------|
| `done` | tasdiqlangan slot |
| `missed` | kun tugadi, tasdiq yo'q |
| `pending` | **bugungi**, hali hal bo'lmagan |
| `resolved` | `done + missed` — **foiz maxraji** |
| `planned` | butun davr bo'yicha barcha slotlar |

- **Bajarish foizi** = `done / resolved × 100`.
  **Bugungi tugallanmagan ish foizni pasaytirmaydi** (kun tugamagan — §4 falsafasi).
- **Shift (ceiling)** = `(planned − missed) / planned × 100` — bundan buyon hammasi
  bajarilsa erishiladigan maksimum. `ceiling < maqsad` bo'lsa maqsad **matematik
  yo'qolgan** — UI buni ochiq aytadi (lekin missiya to'xtamaydi).
- **Seriya (streak):** ketma-ket **mukammal kunlar** (o'sha kunning BARCHA navbatdagi
  odatlari bajarilgan). Dam kunlari (navbat yo'q) seriyani **uzmaydi va uzaytirmaydi**.
  Bugun hali ochiq bo'lsa — seriyani uzmaydi. Har odatning **o'z seriyasi** ham bor.

### 16.4 Chartlar (SPEC §13 dizayn tizimida)

| Chart | Forma | Nima uchun shu forma |
|-------|-------|----------------------|
| **Maqsad halqasi** | meter | Bitta qiymat — pie emas. Maqsad halqada nasechka bo'lib turadi |
| **Tracker to'ri** | heatmap (odat × kun) | SPEC §8 tracker'i. Har katak `✓ ◐ ✕ • ·` glif oladi |
| **Seriya zanjiri** | zanjir | SPEC §13 signature elementi; oxirgi bo'g'in "tirik" |
| **Dinamika** | chiziq + maqsad | Kümülativ foiz. **Bitta o'q** — ikkinchi o'q hech qachon |
| **Odatlar bo'yicha** | gorizontal barlar | Bitta o'lchov → **bitta rang** (qiymat-rampa emas) |

**A11y — majburiy:** `--approve` (#3FD07A) va `--reject` (#FF5A5F) deuteranopiyada
ΔE ≈ **6.2** — qizil/yashil ko'r odam ularni **ajrata olmaydi**. Shuning uchun:
- har status belgisi **glif** ham oladi (rang — ikkilamchi kanal),
- har chart yonida **matnli legenda** turadi,
- tracker'da **jadval ko'rinishi** bor — ma'lumot chart ortida qulflanmaydi.

### 16.5 Rang tokeni

| Token | Hex | Rol |
|-------|-----|-----|
| `--witness` | `#4CC9F0` | **GUVOH** — sovuq moviy. Ittifoqchi, raqib emas |

Nega siyohrang (`--rival`) emas: guvoh raqib emas. Validator: eng yomon qo'shni juftlik
ΔE **22.6** (deutan) / **27.0** (normal) — keng ajraladi.

### 16.6 Texnik: umumiy isbot quvuri

Duel va missiya **bitta** isbot quvurini bo'lishadi (kamera → `file_id` → tekshiruv →
nizo → auto-tasdiq). Buni `App\Contracts\ProofContext` interfeysi ta'minlaydi:

```php
interface ProofContext {
    public function canSubmit(int $userId): bool;                    // kim bajaradi
    public function canVerify(int $userId, int $submitterId): bool;  // kim tekshiradi
    public function hasMember(int $userId): bool;
    // ...
}
```

`Battle` va `Quest` shu interfeysni **turlicha** amalga oshiradi — asimmetriya aynan
shu ikki metodda yashaydi. `CompletionService`, `VerificationService`, `DisputeService`
va `ChatService` qaysi rejimda ishlayotganini **bilmaydi**.

DB'da `challenges` va `chat_messages` jadvallari `battle_id` **yoki** `quest_id` ga
tegishli (aynan bittasi). `completions`, `verifications`, `disputes` **o'zgarmagan** —
ular faqat `challenge_id` ga bog'langan.
