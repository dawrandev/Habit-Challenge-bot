# Garov: yutuq va jazo — reja

> Duelga ikki yangi qoida qo'shiladi. Ikkalasi ham **battle boshlanishidan oldin**
> ikki tomon kelishuvi bilan belgilanadi.

## 1. Talablar

1. **🏆 Yutuq.** G'olib nima olishi oldindan kelishiladi. Har tomon bittadan
   variant aytadi, ikkalasidan **bittasi** tanlanadi.
2. **⚡ Jazo.** Battle davomida **rejadagi odatlarning 50% yoki undan kamini**
   bajargan o'yinchi jazoga tortiladi. Jazo ham yutuq kabi kelishiladi.
   - Misol: 6 odat × 10 kun = 60 ta. 30 ta yoki kamroq bajarsa — jazo. 31 tasi xavfsiz.
   - Jazo g'olib-mag'lubga bog'liq **emas**: ikkalasi ham, hech biri ham jazolanishi mumkin.

## 2. Oqim

```
Yaratuvchi: duel + o'z yutuq/jazo varianti
        │   status: pending
        ▼
Do'st taklifni ochadi → yaratuvchi variantlarini ko'radi → o'z variantlarini yozadi → qabul
        │   status: negotiating  (🤝 Kelishuv)
        ▼
Har tomon har bir garov uchun 2 variantdan birini tanlaydi (ovoz)
   • ovozlar mos → kelishildi ✅
   • ovozlar har xil → chatda kelishish YOKI 🎲 tanga tashlash (50/50)
        │   ikkala garov hal bo'ldi
        ▼
Battle boshlanadi — status: active
```

- **Variantni tahrirlash:** garov hal bo'lguncha o'z variantingni o'zgartirsa bo'ladi.
  Tahrir o'sha garov bo'yicha ovozlarni bekor qiladi (eski matnga berilgan ovoz yangisiga o'tmasin).
- **Kelishuv paytida isbot qabul qilinmaydi** — battle hali boshlanmagan. Chat ishlaydi.
- **Sanalar surilishi:** kelishuv rejadagi boshlanish sanasidan keyin tugasa, davr
  uzunligi saqlangan holda sanalar bugundan boshlab suriladi (10 kunlik battle 10 kunligicha
  qoladi, o'tib ketgan kunlar jarima bo'lmaydi).
- **Eski duellar** (garovsiz) avvalgidek ishlaydi: qabul qilinishi bilan `active`.

## 3. Jazo hisobi

- `rejada` = har (qabul qilingan) odat bo'yicha, uning boshlanishidan battle oxirigacha
  navbatdagi kunlar yig'indisi. Haftaning ma'lum kunlari (cadence) hisobga olinadi.
  Battle o'rtasida qo'shilgan odat o'z boshlanish kunidan hisoblanadi.
- `bajarildi` = tasdiqlangan (yoki avto-tasdiqlangan) isbotlar.
- **Jazo:** `bajarildi × 100 ≤ rejada × chegara`. Chegara battle'da saqlanadi
  (`penalty_threshold = 50`), keyin sozlama o'zgarsa ham eski battle o'zgarmaydi.
- Battle davomida UI har ikki o'yinchi uchun **jazo chizig'i**gacha bo'lgan yo'lni
  jonli ko'rsatadi ("xavfsizlikka yana 7 ta").
- Yakunda natija `battle_participants` ga muhrlanadi va bot xabarida aytiladi.

## 4. Backend (Laravel)

| Qism | O'zgarish |
|---|---|
| Migratsiya | `battle_stakes`, `battle_stake_votes`; `battles.penalty_threshold`; `battle_participants.habits_done/habits_expected/penalized` |
| Enum | `BattleStatus::Negotiating`, `StakeKind` (prize/penalty), `StakeDecision` (agreement/coin) |
| Domen (sof) | `Domain/Scoring/PenaltyRule` — chegara mantiqi |
| Servis | `BattleStakeService` — taklif, tahrir, ovoz, tanga, boshlash, xulosa |
| Mavjud | `BattleService` (yaratish/qabul/detal), `ScoringService::habitProgress`, `BattleClosingService` (jazoni muhrlash + e'lon) |
| API | `POST /battles` va `POST /battles/{token}/accept` endi `stakes` oladi; `PATCH /battles/{id}/stakes/{kind}`, `POST /battles/{id}/stakes/{stake}/vote`, `POST /battles/{id}/stakes/{kind}/coin` |
| Testlar | domen unit + to'liq oqim feature testlari |

## 5. Frontend (React)

- **Yangi duel** — "Garov" bo'limi: yutuq va jazo maydonlari + tezkor variant chiplari.
- **Taklif sahifasi** — yaratuvchi variantlari ko'rinadi, do'st o'zinikini yozadi.
- **Battle sahifasi (kelishuv)** — har garov uchun ikki variant kartasi, ovoz belgilari, tanga animatsiyasi.
- **Battle sahifasi (faol)** — garov kartasi: yutuq, jazo, ikkala o'yinchi uchun jazo chizig'i bilan progress.
- **Natija kartasi** — yutuq kimga, kim jazoga tortildi.
- **Asosiy sahifa** — "🤝 Kelishuv" belgisi.
- 4 til (uz/en/ru/tr).

## 6. Qadamlar

1. Backend: migratsiya, model, enum, domen + unit test
2. Backend: servislar, API, close/announce + feature testlar → hamma test yashil
3. Frontend: API tiplari, komponentlar, sahifalar, i18n → `npm run build` toza
4. SPEC/README yangilash, build'ni `laravel/public` ga ko'chirish, commit
