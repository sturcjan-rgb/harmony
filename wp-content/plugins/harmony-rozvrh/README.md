# Harmony – rozvrh lekcí (WordPress plugin)

Zobrazuje živý rozvrh skupinových lekcí Harmony Sport & Relax Club Písek
z rezervačního systému memberzone.cz. Plugin se přihlásí servisním účtem,
stáhne aktuální týden + 2 týdny dopředu, naparsuje HTML a výsledek drží v cache.

- shortcode `[harmony_rozvrh]`
- blok **Rozvrh Harmony** (`harmony/rozvrh`)
- REST `GET /wp-json/harmony/v1/rozvrh?tyden=0|1|2`

> ⚠️ **Známé riziko – čtěte před nasazením.** Viz [Rizika](#rizika).

---

## Instalace

1. Složku `harmony-rozvrh` nahrajte do `wp-content/plugins/` (složku `tests/` na produkci nahrávat nemusíte) a plugin aktivujte.
2. Do `wp-config.php` (nad řádek `/* That's all, stop editing! */`) doplňte údaje **vyhrazeného servisního účtu** (ne osobního):

```php
define( 'HARMONY_MZ_USER', 'web-rozvrh@harmonypisek.cz' );
define( 'HARMONY_MZ_PASS', '…' );
```

Místo konstant lze použít env proměnné se stejným názvem (`HARMONY_MZ_USER`, `HARMONY_MZ_PASS`).
Údaje nejsou v kódu ani v databázi.

3. Ověřte: `wp harmony-rozvrh refresh` → má vypsat počet lekcí po týdnech.
4. Vložte `[harmony_rozvrh]` nebo blok „Rozvrh Harmony“ na stránku.

Požadavky: WordPress 6.3+, PHP 7.4+ s rozšířením `dom` (a `sodium` pro šifrované uložení session – je v PHP standardně).

## Nastavení (volitelné konstanty)

| Konstanta | Výchozí | Význam |
|---|---|---|
| `HARMONY_ROZVRH_TTL` | `1200` | platnost cache v sekundách (doporučeno 900–1800) |
| `HARMONY_ROZVRH_WEEKS_AHEAD` | `2` | kolik týdnů dopředu stahovat (0–4) |
| `HARMONY_ROZVRH_TIMEOUT` | `15` | timeout jednoho HTTP požadavku (s) |
| `HARMONY_ROZVRH_REST` | `true` | `false` vypne REST endpoint |
| `HARMONY_MZ_BASE_URL` | `https://memberzone.cz/HARMONYpisek/` | |
| `HARMONY_MZ_SCHEDULE_PATH` | `StudioveHodiny.aspx` | |
| `HARMONY_MZ_RESERVATION_URL` | `…/Uvod.aspx` | kam vede klik na lekci |
| `HARMONY_MZ_LOGIN_USER_FIELD` / `_PASS_FIELD` / `_SUBMIT` / `_EVENTTARGET` | autodetekce | přepsání názvů polí login formuláře, kdyby autodetekce netrefila |

## Použití

### Shortcode

```
[harmony_rozvrh]
[harmony_rozvrh tydny="1" filtry="0"]
[harmony_rozvrh rezervace="https://harmonypisek.cz/online-rezervace/"]
```

- `tydny` – kolik týdnů zobrazit (1 až `WEEKS_AHEAD + 1`)
- `filtry` – filtr skupin lekcí (Kardio, Síla, Klid a mobilita, Jiu-jitsu, Děti)
- `rezervace` – odkaz z karty lekce

### Vzhled

Vychází ze sekce `#rozvrh` a varianty C (týdenní mřížka) z prototypu. Desktop: týden v 7 sloupcích. Pod 900 px: přepínač dnů a seznam lekcí jednoho dne (výchozí je dnešek). Proběhlé lekce se ztlumí, plné mají štítek „Plno“.

Barvy se berou z proměnných šablony `--forest`, `--lime`, `--ink`, pokud existují. Pozadí komponenty se dá vypnout, když ho řeší sekce šablony:

```css
.hr-rozvrh{--hr-bg:transparent;padding:0}
```

HTML se renderuje na serveru (rozvrh vidí Google i bez JS), JS jen přepíná a filtruje. Výstup nezávisí na čase, takže je bezpečný pro page cache.

### Skupiny lekcí

memberzone kategorie neposílá, přiřazují se podle názvu (`includes/class-categories.php`). Nová lekce bez záznamu má neutrální barvu. Úprava bez zásahu do pluginu:

```php
add_filter( 'harmony_rozvrh_kategorie_lekci', function ( $map ) {
	$map['zumba'] = array( 'cardio' );
	return $map;
} );
```

### REST

`GET /wp-json/harmony/v1/rozvrh?tyden=0` → pole lekcí:

```json
[
  {
    "id": 46687,
    "datum": "2026-11-20",
    "cas": "16:00",
    "konec": "16:55",
    "zacatek": "2026-11-20T16:00:00+01:00",
    "delka_min": 55,
    "nazev": "Pilates",
    "sal": "Sál 1",
    "instruktor": "Eliška",
    "kapacita": 18,
    "obsazeno": 14,
    "volno": 4,
    "cena": 150,
    "popis": "…",
    "skupiny": ["mind", "sila"]
  }
]
```

Metadata jsou v hlavičkách: `X-Harmony-Tyden-Od`, `X-Harmony-Tyden-Do`, `X-Harmony-Aktualizovano`, `X-Harmony-Zastarale` (`1` = vrací se poslední úspěšná data). Když nejsou žádná data, vrací 503.

## Cache a zátěž memberzone

- Výsledek je v transientu po dobu `HARMONY_ROZVRH_TTL`. Jedno stažení = 1–2 GET (+ login jen při neplatné session) + 1 POST na každý další týden.
- Po vypršení cache návštěvník hned dostane poslední data a obnova proběhne na pozadí přes WP-Cron. Návštěvník na scraping nikdy nečeká. Jedinou výjimkou je úplně první načtení po instalaci.
- Poslední úspěšná data se drží zvlášť (option bez autoloadu). Při výpadku memberzone se zobrazují ta. Chyba se jen zaloguje (s `WP_DEBUG`) a uloží pro `wp harmony-rozvrh status`. Další pokus je nejdřív za 5 minut.
- Zámek brání souběžnému stahování.
- Session cookies memberzone se drží 30 min v transientu, šifrované klíčem odvozeným z `AUTH_SALT`. Když vyprší, plugin se jednoduše přihlásí znovu.
- **Nízká návštěvnost / `DISABLE_WP_CRON`:** WP-Cron běží jen při návštěvě. Pro spolehlivou obnovu nastavte systémový cron: `*/15 * * * * wp --path=/cesta/k/webu cron event run --due-now`.

## Ladění a oprava po změně memberzone

Veškerá závislost na HTML memberzone je na dvou místech:

| Co se rozbilo | Kde opravit |
|---|---|
| Přihlášení (formulář, tlačítko, redirect) | `includes/class-memberzone-client.php` → `login()`, případně konstanty `HARMONY_MZ_LOGIN_*`; autodetekce polí v `class-html.php` → `login_fields()` |
| Data lekcí (inputy `hf…`, název, sál, lektor, obsazenost) | `includes/class-parser.php` → konstanta `SELECTORS` |
| Přepínání týdnů | `class-memberzone-client.php` → `NEXT_TARGET_REGEX` |

WP-CLI:

```
wp harmony-rozvrh status          # stav cache, poslední chyba
wp harmony-rozvrh refresh         # stáhnout hned
wp harmony-rozvrh debug           # uloží syrové HTML týdnů do /tmp a vypíše naparsovaná data
wp harmony-rozvrh parse soubor.html   # naparsuje uložené HTML – ladění selektorů offline
wp harmony-rozvrh purge           # smaže cache i session
```

**První nasazení:** parser je napsaný podle popisu struktury stránky a otestovaný na falešné kopii (`tests/`). Skutečné HTML memberzone jsem neviděl. Po nasazení spusťte `wp harmony-rozvrh debug` a zkontrolujte, že název, sál a lektor sedí. Pokud heuristika z textu karty něco zamění, doplňte do `SELECTORS` konkrétní XPath (`xpath_name`, `xpath_room`, `xpath_instructor`, `xpath_occupancy`) podle tříd/ID v uloženém HTML.

Pojistky: když aktuální týden nemá žádnou lekci nebo stránka nevypadá jako rozvrh, data se **neuloží** a dál se zobrazují poslední platná. Rozbitý parser tedy nevymaže rozvrh z webu.

### Testy bez WordPressu

```
php -S 127.0.0.1:8765 tests/mock/router.php &
php tests/run.php
```

Mock simuluje ASP.NET login (ViewState, EventValidation, auth cookie na 302), rozvrh se skrytými `hf…` inputy a `__doPostBack('btnNext','')`.

## Rizika

- **Podmínky memberzone.** Jde o automatizované stahování obsahu za přihlášením třetí strany. Může být v rozporu s podmínkami užití memberzone.cz. Jejich `robots.txt` stránku `StudioveHodiny.aspx` zakazuje pro roboty. Doporučení: klub (jako zákazník memberzone) by si měl u provozovatele vyžádat souhlas, nebo ideálně oficiální export/embed rozvrhu. Pak stačí vyměnit `Harmony_Rozvrh_Memberzone_Client` + `Harmony_Rozvrh_Parser`, zbytek pluginu (model, cache, výstup) zůstane.
- **Závislost na HTML a login flow.** Jakákoli změna šablony, názvů polí, přihlašování (např. captcha, 2FA, nové cookies) integraci rozbije. Web pak tiše ukazuje poslední stažená data s datem „Aktualizováno …“. Hlídejte `wp harmony-rozvrh status`.
- **Servisní účet.** Používejte vyhrazený účet s minimem oprávnění, ne osobní ani administrátorský. Při odchodu dodavatele změňte heslo.
- **Zátěž.** Při výchozím TTL je to cca 4 požadavky na memberzone za 20 minut, nezávisle na návštěvnosti.
