# Revize webu harmonypisek.cz

Revize současného webu Harmony Sport & Relax Club Písek (stav k 8. 11. 2026) a návrh nové homepage. Prototyp je v souboru [`index.html`](index.html).

## Shrnutí

Web je zastaralý vizuálně i informačně. Hlavně je ale **zmatený a neintuitivní**: návštěvník na něm těžko najde tři věci, kvůli kterým přichází (kolik to stojí, kdy co je a jak se zarezervovat). Stejné informace jsou na několika místech a pokaždé jinak. Navigace neodpovídá tomu, jak lidé o klubu přemýšlejí. Homepage nemá žádnou hierarchii ani hlavní akci.

Kromě toho je web **napadený** (vložený SEO spam), což je potřeba řešit jako první.

---

## 1. Bezpečnost – web je napadený

Na stránkách služeb je do textu vložený farmaceutický SEO spam:

- **/class/masaze/** – věta o historii masáží končí textem „buy capecitabine“
- **/class/solarium/** – věta o pokožce končí textem „brand viagra“

Jde o typický průnik do WordPressu přes zastaralou šablonu a pluginy (LayerSlider 7.9.5, WPBakery / Visual Composer). Google takové weby penalizuje ve vyhledávání. **Doporučení:** vyčistit web, aktualizovat jádro, šablonu a pluginy, zkontrolovat administrátorské účty a změnit hesla. Teprve potom řešit redesign.

## 2. Web je zmatený a neintuitivní

Hlavní problém webu není jen vzhled, ale to, že se v něm člověk neorientuje.

### Navigace neodpovídá tomu, co návštěvník hledá

- **Stálé služby jsou schované v Novinkách.** Brazilské jiu-jitsu, jiu-jitsu pro děti i dětský kroužek a příměstský tábor jsou v menu pod „Novinky“, ne pod „Služby“. Kdo hledá bojové sporty, mezi službami je nenajde.
- **Lidé jsou na třech různých místech.** Osobní trenéři jsou pod „Služby“, lektoři jsou jen na homepage a „Lidé / Personál klubu“ je pod „O nás“. Není jasné, kde hledat, kdo co vede.
- **Duplicitní položky v menu.** „Novinky“ obsahuje podpoložku „Novinky“, „Ceník“ podpoložku „Ceník vstupů“ se stejnou adresou, „O nás“ podpoložku „O klubu“ se stejnou adresou, „Služby“ a „Skupinové lekce“ vedou na tutéž stránku a „Lidé“ i „Personál klubu“ jsou totéž.
- **Nejdůležitější akce nevypadá jako akce.** Rezervace je schovaná v položce „ONLINE PROFIL / REZERVACE“, která v menu vypadá stejně jako ostatní odkazy. Na celém webu chybí výrazné tlačítko „Rezervovat“.
- **Interní názvy místo srozumitelných.** Stránka s rozvrhem se jmenuje „Rozvrh barevný“, což je pracovní název, ne něco, co by návštěvník hledal.

### Stejná informace na více místech a pokaždé jinak

- **Rozvrh existuje ve třech verzích:** widget na homepage, obrázek (JPG) na stránce rozvrhu a „aktuální“ verze v rezervačním systému. Neshodují se: například Fitbox je na homepage v úterý 17:00 a ve čtvrtek 18:00, na obrázku jen ve čtvrtek 17:00. Návštěvník neví, které verzi věřit.
- **Ceny si odporují.** Homepage uvádí „vstup od 160 Kč“, ceník posilovnu za 199 Kč. „Měsíční 1 390 Kč“ na homepage je ve skutečnosti studentská cena, dospělý platí 1 690 Kč. „Roční od 990 Kč/měsíc“ neexistuje, ceník zná jen půlroční členství a 990 Kč platí jen pro studenty a seniory.
- **Otevírací doba se liší.** Posilovna je v ceníku „od 6:15“, na stránce O nás „od 6:30“. Hodiny jsou navíc rozepsané na třech stránkách.

### Homepage nevede k žádné akci

- Ticker, novinky, galerie, motivační blok, služby, kompletní rozvrh, karusel s 25 lektory, BMI kalkulačka, ceník a loga partnerů mají stejnou váhu. Stránka je dlouhá přes 5 700 px a nemá jedno jasné tlačítko, co udělat dál.
- **Chybí základní informace.** Na homepage není telefon, adresa, otevírací doba ani mapa. Patička obsahuje jen „© 2017–2024“. Telefon je schovaný v textu na stránce rozvrhu a v kontaktech.
- **Odkazy, které nikam nevedou.** Dlaždice „Posilovna“ a „Skupinová cvičení“ v galerii odkazují na „#“.
- **Novinky jsou neuspořádané.** Karusel řadí příspěvky bez ohledu na datum (listopad, září, srpen, červenec), opakuje text záskoků z tickeru a nabízí soutěž, která už skončila.

### Rezervace a rozvrh se špatně používají

- Rozvrh jako JPG se na mobilu nedá přečíst, nedá se v něm hledat a Google ho nevidí.
- Na stránce rozvrhu stále visí „mimořádné lekce 21.–27. 9.“, přestože je listopad.
- Stránka online rezervace je dlouhý návod psaný velkými písmeny. Člověk musí pochopit rozdíl mezi „registrovat se“ a „aktivovat profil“ dřív, než si vůbec vybere lekci.
- Odkazy typu „>ZDE<“ a „>KOMPLETNÍ ROZVRH ZDE<“ neříkají, kam vedou.

## 3. Obsah

- U pěti lektorů je „Popisek připravujeme…“.
- Jeden lektor má jako fotku placeholder siluety ze Shutterstocku (náhled s vodoznakem).
- Na homepage jsou lektoři Zumby, Power Stepu a Alpinningu, které v rozvrhu nejsou. Naopak David Belšan (SM systém) a Julie Č. mezi lektory chybí.
- U Bodyformu je uvedena „Klára Handrejchová, Klárka Handrejchová“.
- BMI kalkulačka má tabulku v angličtině (Underweight, Obese…).
- Překlepy: „vybere **opravu** každý“, „se**mnou**“ (2×), „poukaz do kosmetické salonu“.

## 4. Vizuál

- **Hero** je stock fotka s obecným nadpisem „Permanentky a jednorázové vstupy“. Neříká, co Harmony je. Klub přitom má vlastní kvalitní fotky nové posilovny a open gymu.
- **Typografie** míchá Open Sans, Roboto, Arial a Tahomu. Nadpisy střídají velká a malá písmena nahodile.
- **Limetková zelená** jako velká plocha působí levně a nesedí k tmavě zelenému logu.
- **Sekce lektorů** se často načte prázdná, je vidět jen rozmazané pozadí se šipkami.
- Šablona je z roku 2017 a postavená na page builderech, což ztěžuje údržbu i další úpravy.

---

## Návrh řešení

Prototyp nové homepage je v [`index.html`](index.html). Je postavený kolem tří úkolů návštěvníka: **kolik to stojí → kdy co je → rezervuj**.

- **Živý panel „Dnes“** ukazuje, jestli je otevřeno a do kdy, a nejbližší lekce.
- **Interaktivní rozvrh** místo obrázku: dny, filtry podle typu lekce a lektora, tlačítko Rezervovat u každé lekce. V ostré verzi by se měl načítat přímo z rezervačního systému, aby existoval jen jeden rozvrh.
- **Ceník** s přepínačem dospělí / student a senior, rozdělený podle toho, jak často člověk chodí. Čísla odpovídají platnému ceníku.
- **Kontakt, otevírací hodiny klubu a wellness** na jednom místě, se zvýrazněným dnešním dnem.
- **Navigace** má pět jasných položek a výrazné tlačítko Rezervovat.
- **Vizuálně:** tmavě zelená z loga jako hlavní barva, limetková jen jako akcent, jeden font, vlastní fotky klubu.

### Jak to realizovat ve WordPressu

**A) Child theme a nová homepage.** Vlastní šablona homepage, ceník a otevírací hodiny jako jedno editovatelné místo (ACF options), které se propíše na všechny stránky. Ostatní stránky jen přestylovat. Rychlejší a levnější, ale stará šablona a page buildery zůstávají.

**B) Nová vlastní šablona.** Převést obsah a starou šablonu i page buildery vyřadit. Víc práce, ale čistý a udržitelný web.

Před výběrem varianty je potřeba zjistit, jaký rezervační systém klub používá (a jestli má API nebo embed rozvrhu), a projít administraci (verze PHP a WordPressu, pluginy, rozsah obsahu ve WPBakery).
