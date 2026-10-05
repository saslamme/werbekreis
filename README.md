# Werbekreis Haselünne

Technisches Grundgerüst für ein lokales Stadtportal. PR1 enthält Anmeldung, Benutzerverwaltung, Admin-Dashboard, einen Mitglieder-Platzhalter und eine statische öffentliche Startseite. PR2 ergänzt die Stammdatenverwaltung für Unternehmen und Kategorien im bestehenden Adminbereich. PR3 macht daraus das öffentliche Unternehmensverzeichnis mit Suche, Kategorie-Filter, Detailseiten und datengetriebener Startseite. PR4 ergänzt Karte und Umkreissuche. PR5 ergänzt echte Angebote und Aktionen. PR6 ergänzt Veranstaltungen, begrenzte Wiederholungen und einen Monatskalender. PR7 ergänzt Aktuelles mit redaktioneller Verwaltung und zeitgesteuerter Veröffentlichung. PR8 ergänzt echte Stellenangebote mit Suche, Beschäftigungsarten, Bewerbungsfristen und JobPosting-Daten.

## Technik

- PHP 8.4 und Symfony 7.4 LTS
- Doctrine ORM und Migrations, MariaDB 10.11
- Symfony Security, Forms, Validator und Twig
- Sylius Stack: Resource, Grid und Bootstrap Admin UI; kein Sylius Shop
- Bootstrap 5, lokales Font Awesome, SCSS und AssetMapper
- npm: Sass und esbuild für lokal gebündelte Assets

## Installation

Voraussetzungen: PHP 8.4 mit PDO MySQL, Intl, Zip, Ctype und Iconv, Composer 2, Node.js 22 oder neuer und MariaDB 10.11. Alternativ steht die unten beschriebene Docker-Umgebung bereit.

```sh
composer install
npm ci
npm run build
```

`.env.local` erstellen, `DATABASE_URL` konfigurieren und einen eigenen `APP_SECRET` setzen. `.env` und `.env.example` enthalten ausschließlich Beispiele. Eine zufällige Zeichenfolge lässt sich mit `php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'` erzeugen. Den Wert lokal eintragen und niemals committen.

```dotenv
APP_SECRET=hier-einen-eigenen-zufaelligen-wert-eintragen
DATABASE_URL="mysql://app:password@127.0.0.1:3306/werbekreis?serverVersion=mariadb-10.11.0&charset=utf8mb4"
```

Datenbank anlegen und Migrationen anwenden:

```sh
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console doctrine:schema:validate
```

Nur für lokale Entwicklung, **löscht vorhandene Daten**:

```sh
php bin/console doctrine:fixtures:load
```

Assets und lokaler Server:

```sh
npm run build
php bin/console asset-map:compile
php -S 127.0.0.1:8080 -t public public/router.php
```

Alternativ `symfony server:start`. In der Entwicklung nach Assetänderungen erneut `npm run build` ausführen. `npm run watch` beobachtet SCSS. Bei Verwendung kompilierter AssetMapper-Dateien nach Änderungen erneut `asset-map:compile` ausführen. Generierte Assets, lokale Fonts, `vendor` und `node_modules` bleiben außerhalb von Git.

## Docker für lokale Entwicklung

```sh
docker compose build
docker compose up -d database
docker compose run --rm php composer install
npm ci
npm run build
docker compose run --rm php php bin/console doctrine:migrations:migrate --no-interaction
docker compose run --rm php php bin/console doctrine:fixtures:load
docker compose up -d php
```

Auch hier `.env.local` mit eigenem `APP_SECRET` anlegen. Docker setzt den Datenbankhost auf `database`. Datenbankdaten bleiben im Compose-Volume erhalten. Server und Datenbank sind nur für Entwicklung vorgesehen; Beispielpasswörter vor jedem produktiven Einsatz ersetzen. In verwalteten Cloud-Umgebungen die Plattform-Proxy- und CA-Anweisungen beachten; niemals TLS-Prüfung deaktivieren.

## Rollen und Sicherheit

- `ROLE_ADMIN`: Dashboard, Unternehmen, Kategorien und Benutzerverwaltung.
- `ROLE_EDITOR`: Dashboard sowie Unternehmens- und Kategorienverwaltung; keine Berechtigung zur Benutzer- oder Rollenverwaltung.
- `ROLE_MEMBER`: ausschließlich Mitgliederbereich.
- Mehrere Rollen: Login-Ziel hat die Priorität Admin, Editor, Mitglied.
- Admin erbt Editor, aber weder Admin noch Editor erben automatisch Mitglied.
- Inaktive Benutzer können sich nicht anmelden.
- Formulare und Logout verwenden CSRF-Schutz, Passwörter den Symfony PasswordHasher.
- Beim Bearbeiten bleibt ein leer gelassenes Passwort unverändert.
- Ein Admin kann sein eigenes Konto weder deaktivieren noch die eigene Admin-Rolle entfernen.
- Das Dashboard zählt ausdrücklich gespeicherte Rollen; Rollenüberschneidungen sind möglich.

## Entwicklungszugänge

| Rolle | E-Mail | Passwort |
| --- | --- | --- |
| Admin | admin@example.local | admin123 |
| Editor | editor@example.local | editor123 |
| Mitglied | member@example.local | member123 |

**Die Testzugänge sind ausschließlich für lokale Entwicklung gedacht und dürfen nicht produktiv verwendet werden.** Fixtures sind nur im Development-/Test-Kernel verfügbar.

## Tests

Die Tests löschen die Benutzerdaten in der Testdatenbank. Nie gegen produktive Daten ausführen. Doctrine hängt im Testmodus `_test` an den Datenbanknamen an; ein gegebenenfalls vorhandenes `TEST_TOKEN` ergänzt den Namen. Der Datenbankbenutzer benötigt Rechte auf dieser separaten Testdatenbank. `.env.test.local` erlaubt eine separate Zugangskonfiguration.

```sh
APP_ENV=test php bin/console doctrine:database:create --if-not-exists
APP_ENV=test php bin/console doctrine:migrations:migrate --no-interaction
php bin/phpunit
```

In Docker die Testdatenbank gegebenenfalls als root anlegen und Rechte vergeben; die Compose-Anwendungskennung besitzt standardmäßig nur die Entwicklungsdatenbank. Tests decken Homepage, echte Logins, Rollenpriorität, Zugriffssperren, inaktive Konten, CSRF, Benutzererstellung, Passworterhalt und Eigendeaktivierung ab.

Abschlusschecks:

```sh
composer validate --strict
php bin/console about
php bin/console lint:twig templates
php bin/console lint:container
php bin/console doctrine:schema:validate
php bin/phpunit
npm run build
php bin/console asset-map:compile
```

## Zentrale Konfiguration

`config/packages/portal.yaml` enthält Portalname, Stadt und Kontakt-E-Mail als Containerparameter. Die Kontaktadresse, Impressum und Datenschutz sind noch Platzhalter. Vor einem öffentlichen Einsatz müssen diese Inhalte sowie eine Produktionskonfiguration ergänzt werden. Es gibt in PR1 keine PortalSettings- oder Member-Entity.

## Spätere PRs

Kartenansicht, OpenStreetMap/Google Maps, Umkreissuche, Geocoding, Bewertungen, Favoriten, Angebote, Veranstaltungen, News, Jobs, Dokumente, Mitgliedsinhalte und Self-Service, Freigabeverfahren und Gutscheinfunktionen gehören in spätere PRs. Statische Vorschaukarten erzeugen keine entsprechenden Entities.

## Verwaltete Cloud-Umgebung ohne PHP auf dem Host

`scripts/cloud-install.sh` baut die PHP-Laufzeit, installiert beide Lockfiles und kompiliert die Assets. Docker übernimmt den von der Plattform konfigurierten Proxy; das öffentliche CA-Bundle wird nur während Builds bzw. read-only zur Laufzeit eingebunden. Composer läuft mit der UID/GID des Benutzers. `scripts/cloud-php.sh` führt einzelne datenbankunabhängige PHP-/Composer-Befehle aus. Datenbankbefehle benötigen zusätzlich das interne Docker-Netz und die dazu passende `DATABASE_URL`; die gespeicherten Startanweisungen der Cloud-Umgebung enthalten den getesteten Ablauf. Keine zusätzlichen Worktrees anlegen.

Docker-Testdatenbank konkret vorbereiten:

```sh
docker compose exec database mariadb -uroot -plocal-root-password -e "CREATE DATABASE IF NOT EXISTS werbekreis_test; GRANT ALL ON werbekreis_test.* TO 'app'@'%';"
docker compose run --rm -e APP_ENV=test php php bin/console doctrine:migrations:migrate --no-interaction
docker compose run --rm php php bin/phpunit
```

In der Cloud startet `scripts/cloud-start.sh` ausschließlich die projektspezifischen Container und prüft die Homepage per HTTP. Fixtures werden dabei absichtlich nicht automatisch geladen, weil sie vorhandene Daten löschen würden. Für eine neue, leere Entwicklungsdatenbank einmal `docker exec wk-pr1-php php bin/console doctrine:fixtures:load --no-interaction` ausführen.

## PR2: Unternehmensverzeichnis und Stammdaten

Die Verwaltung erweitert das vorhandene Sylius-Adminlayout. Es gibt keine zweite UI und keine Commerce-Pakete. Admins und Editoren sehen die Menüpunkte **Unternehmen** und **Kategorien**. Die Benutzerverwaltung bleibt ausschließlich Admins zugänglich. Die öffentliche Homepage bleibt die statische Vorschau aus PR1.

### Datenmodell

| Entity | Zweck und Beziehungen |
| --- | --- |
| `Company` | Stammdaten, Adresse, Kontakt, Social Media, optionale Koordinaten, Aktiv-/Featured-Status und Zeitstempel |
| `Category` | Name, eindeutiger Slug, Beschreibung, lokales Font-Awesome-Icon, Position, Aktivstatus und Zeitstempel; ManyToMany zu Company |
| `CompanyImage` | Logo, Cover oder Galerie mit Dateiname, Alternativtext, Titel und Position; gehört genau einem Unternehmen |
| `OpeningHour` | ISO-Wochentag 1–7, Zeitfenster oder geschlossen; mehrere Einträge pro Unternehmen und Tag möglich |
| `ContactPerson` | Ansprechpartner, Funktion und Kontaktangaben; optionales Bild aus demselben Unternehmen |

Collections werden bidirektional gepflegt. Öffnungszeiten, Ansprechpartner und Bilder gehören exklusiv zum jeweiligen Unternehmen und werden mit ihm gelöscht. Beim Entfernen eines Ansprechpartnerbildes wird die optionale Bildreferenz auf null gesetzt. Zugeordnete Kategorien können nicht gelöscht werden, damit kein aktives Unternehmen seine letzte Kategorie unbemerkt verliert.

Aktive Unternehmen brauchen mindestens eine Kategorie. Zeitfenster benötigen beide Uhrzeiten; die Schlusszeit muss später liegen. Überschneidungen sowie geschlossene und offene Einträge am gleichen Tag werden abgewiesen. Über Mitternacht laufende Intervalle sind in PR2 nicht vorgesehen. Es darf höchstens einen primären Ansprechpartner geben. Eingebettete Öffnungszeiten und Ansprechpartner sind auf jeweils 50 Einträge begrenzt. Geo-Werte werden nur validiert und gespeichert; es gibt keine Geocoding-Anbindung.

Die Default-Stadt kommt aus `portal.city`. Neue Slugs werden normalisiert und bei Kollisionen mit `-2`, `-3` usw. ergänzt. Deutsche Umlaute werden als ae/oe/ue transliteriert. Namensänderungen ändern bestehende Slugs nicht automatisch. Wer einen Slug absichtlich neu erzeugen möchte, leert das Feld. Explizite doppelte Slugs und konkurrierende Vergaben ergeben verständliche Formularfehler statt ungefangener Datenbankfehler.

### Verwaltung

- Unternehmensliste: Name, Kategorien, Ort, Status, Featured, Änderungszeit und Aktionen.
- Filter: Name, Kategorie, aktiv und hervorgehoben; Sortierung nach Name oder letzter Änderung.
- Pagination: 25 Unternehmen pro Seite, mit eager geladenen Kategorien; keine Abfrage pro Tabellenzeile.
- Unternehmensformular: Allgemein, Adresse, Kontakt, Öffnungszeiten und Ansprechpartner. Collections können mit dem vorhandenen JavaScript-/Asset-System hinzugefügt und entfernt werden.
- Bilder: eigener Upload-/Metadatenbereich innerhalb derselben Unternehmensbearbeitung. Das Unternehmen und offene Stammdatenänderungen zuerst speichern; anschließend Bilder hinzufügen oder ersetzen. Keine ungespeicherten Bilder-Collections im großen Unternehmensformular.
- Kategorien: CRUD, Sortierung, Aktivstatus und gebündelt abgefragte Unternehmensanzahl.
- Dashboard: bestehende Benutzerzahlen sowie Unternehmen gesamt, aktiv, Featured und Kategorien.
- Alle Mutationen sind rollen- und CSRF-geschützt. Löschungen sind POST-only und werden in der Oberfläche ausdrücklich als endgültig bezeichnet.

### Uploads

`COMPANY_UPLOAD_DIR` ist konfigurierbar. Standard:

```dotenv
COMPANY_UPLOAD_DIR=%kernel.project_dir%/var/uploads/companies
```

Das Verzeichnis liegt außerhalb von `public/` und muss für den PHP-Benutzer beschreibbar sein. Es wird beim ersten Upload angelegt. In Deployments ein persistentes Verzeichnis oder Volume verwenden und gemeinsam mit der Datenbank sichern; bei mehreren PHP-Instanzen muss es gemeinsam verfügbar sein. Keine bestehenden Bilddateien bei Releasewechseln löschen. In `.env.local` bzw. der Deployment-Umgebung kann ein absoluter Pfad eingestellt werden.

Erlaubt sind JPEG, PNG und WebP, geprüft anhand des Dateiinhaltstyps, bis 5 MB und 8000 × 8000 Pixel. Originaldateinamen werden nicht übernommen: jede Datei erhält einen kryptografisch zufälligen Namen mit passender Erweiterung. SVG, HTML und ausführbare Dateien sind nicht erlaubt. Der Docker-PHP-Container ist auf 5 MB Uploadgröße und 8 MB POST-Größe konfiguriert; außerhalb von Docker müssen PHP-/Webserverlimits mindestens dazu passen.

Bilddateien werden derzeit ausschließlich über geschützte Adminrouten ausgeliefert. Öffentliches Rendering folgt in PR3. Ein fehlendes Bild verhindert die Unternehmensdarstellung nicht. Ersatz und Löschung entfernen alte Dateien nach erfolgreicher Datenbankänderung; fehlgeschlagene Datenbankänderungen entfernen bereits neu gespeicherte Uploads wieder. Dateisystem und Datenbank können nicht atomar zusammen committen. Fehlschläge beim Entfernen werden protokolliert und lassen sich später bereinigen:

```sh
# Nur anzeigen; referenzierte Dateien und Dateien jünger als eine Stunde bleiben erhalten.
php bin/console app:company-images:cleanup
# Nach Prüfung verwaiste Dateien entfernen.
php bin/console app:company-images:cleanup --delete
```

Testuploads liegen fest in `var/uploads/test_companies`, unabhängig vom konfigurierten Deployment-Verzeichnis. Die Bildtests erstellen temporäre Bilddateien selbst; es werden keine Binärdateien im Repository benötigt.

### Migration und Fixtures

Die von Doctrine generierte Migration `Version20261005135423` ergänzt die fünf Entities und die Company/Category-Zuordnung. PR1-Migrationen bleiben unverändert.

```sh
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console doctrine:schema:validate
```

Die Development-/Test-Fixtures enthalten acht Kategorien und fünf ausdrücklich fiktive Unternehmen. Sie decken mehrere Kategorien, geteilte Öffnungszeiten, mehrere Ansprechpartner, optionale Websites/Social Media sowie verschiedene Aktiv-/Featured-Zustände ab. Es werden keine realen Firmen oder personenbezogenen Daten importiert. Bilder lassen sich über die Verwaltung testen; die Fixtures funktionieren ohne Bilddateien.

`doctrine:fixtures:load` lädt User- und Directory-Fixtures gemeinsam und löscht vorhandene Daten. **Nur in einer dafür vorgesehenen lokalen Development-/Testdatenbank ausführen; niemals automatisch produktiv einspielen.**

### PR2-Tests und CI

Zusätzlich zu den unveränderten PR1-Tests werden Beziehungen, Slugs, Koordinaten, Kontakt-/URL-Validierung, Öffnungszeiten, Rollen, Stammdaten-CRUD, Filters/Pagination, CSRF, Uploadtyp/-größe, Bildzuordnung, Dateiersatz/-löschung und Bereinigung geprüft. Der kleine DOM-Test prüft das Hinzufügen/Entfernen von Collection-Zeilen und unabhängige Indizes.

```sh
php bin/phpunit
npm ci
npm test
npm run build
php bin/console asset-map:compile
```

Die bestehenden CI-Checks bleiben bestehen und werden um `npm test` erweitert.

## PR3: Öffentliches Unternehmensverzeichnis

### Routen

| Route | Name | Inhalt |
| --- | --- | --- |
| `/` | `app_home` | Startseite mit Hero-Suche, aktiven Kategorien und sechs Unternehmen |
| `/unternehmen` | `company_index` | Übersicht aller aktiven Unternehmen, 12 pro Seite |
| `/unternehmen?q=…` | `company_index` | Suche |
| `/unternehmen?kategorie={slug}` | `company_index` | Kategorie-Filter, kombinierbar mit `q` und `page` |
| `/unternehmen/{slug}` | `company_show` | Detailseite |
| `/unternehmen/{slug}/bilder/{datei}` | `company_image` | Öffentliche Auslieferung von Logo, Titelbild, Galerie- und Ansprechpartnerbildern |

Öffentliche URLs enthalten nur Slugs und zufällige Bilddateinamen, keine Datenbank-IDs. Alle Seiten sind ohne Login erreichbar (`access_control` betrifft weiterhin nur `/admin` und `/member`); die Admin-Sicherheit aus PR1/PR2 ist unverändert. Inaktive oder unbekannte Unternehmen liefern 404 (nicht 403), ebenso unbekannte oder inaktive Kategorien, Seiten jenseits der letzten Ergebnisseite und Bilder inaktiver Unternehmen. Ungültige `page`-Werte führen auf Seite 1.

### Suche, Filter und Sortierung

Alle öffentlichen Listen nutzen `CompanyRepository::createPublicDirectoryQueryBuilder()` (Übersicht, Suche, Kategorie, Startseite) – es gibt keine zweite Query-Variante:

- nur `active = true`;
- Suche (`q`, getrimmt, max. 100 Zeichen) per `LIKE` über Name, Kurzbeschreibung, Beschreibung, Ort und Namen **aktiver** Kategorien, groß-/kleinschreibungsunabhängig; Werte werden gebunden, `%`/`_` maskiert und wörtlich gesucht; leere Suche zeigt alle aktiven Unternehmen;
- Kategorie per Slug (`MEMBER OF`), nur aktive Kategorien;
- feste Sortierung: hervorgehobene Unternehmen zuerst, dann Name, dann ID. Sortierparameter aus der URL werden nicht ausgewertet.

`CompanyRepository::publicDirectoryPage()` paginiert mit dem Doctrine-Paginator (ohne zusätzliche Bibliothek); Seitenlinks behalten `q` und `kategorie` (gemeinsames Partial `templates/partials/_pagination.html.twig`, auch im Admin genutzt). Keine externe Suchmaschine.

### Detailseite

Titelbild, Logo (sonst neutraler Initial-Platzhalter), Name, aktive Kategorien, Kurzbeschreibung, Beschreibung; Kontakt (Adresse, Telefon, E-Mail, Website, Facebook, Instagram – nur gesetzte Felder, externe Links mit Hinweis „öffnet in neuem Tab“); Öffnungszeiten nach Wochentagen gruppiert mit mehreren Zeitfenstern – Tage ohne Einträge werden nicht als „geschlossen“ dargestellt; aktive Ansprechpartner (primär zuerst, dann Sortierung, dann Name) mit optionalem Bild, Position, E-Mail, Telefon, Mobil; Galerie nur mit Bildern vom Typ `gallery`.

Die Darstellungslogik liegt in Entity-Methoden statt in Twig: `Company::getLogoImage()`, `getCoverImage()`, `getGalleryImages()`, `getPublicCategories()`, `getPublicContactPersons()`, `getOpeningHoursByDay()`, `getTeaser()` sowie `OpeningHour::getDayName()`. Partials: `frontend/company/_card`, `_logo`, `_opening_hours`, `_contact_person`, `_gallery`, `_search_form` und `frontend/category/_card`.

Bilder liegen weiterhin außerhalb von `public/` (`COMPANY_UPLOAD_DIR`). Die öffentliche Route liefert eine Datei nur aus, wenn sie zu einem aktiven Unternehmen mit passendem Slug gehört; Antworten sind öffentlich cachebar (1 Tag, ETag/Last-Modified, `nosniff`).

### Startseite

Die bisher statischen Kategorie- und Unternehmenskarten kommen jetzt aus der Datenbank: aktive Kategorien nach `position` mit Icon und Anzahl aktiver Unternehmen (eine gruppierte Abfrage) und sechs aktive Unternehmen, hervorgehobene zuerst und mit weiteren aktiven aufgefüllt. Die Hero-Suche ist ein GET-Formular auf `/unternehmen?q=…` (kein JavaScript), „Beliebt“ verlinkt die ersten Kategorien, „Unternehmen finden“ im Header und „Unternehmen“ im Footer zeigen auf die Übersicht. Angebote, Veranstaltungen, Gutschein und Jobs bleiben gekennzeichnete Beispielinhalte.

### SEO

`base.html.twig` bietet die Blöcke `title`, `meta_description`, `canonical` und `meta`.

- Übersicht: „Unternehmen in Haselünne | Werbekreis Haselünne“ bzw. „{Kategorie} in Haselünne | …“, passende Beschreibung, Canonical mit Kategorie und Seite; Suchergebnisseiten tragen `noindex, follow`.
- Detail: „{Name} | Werbekreis Haselünne“, Beschreibung aus der Kurzbeschreibung (sonst gekürzte Beschreibung, sonst Name und Ort), Canonical, Open Graph (`og:title`, `og:description`, `og:url`, `og:type`, `og:image` aus Titelbild oder Logo, falls vorhanden).
- JSON-LD `LocalBusiness` (`App\Service\CompanyStructuredData`): Name, URL, Beschreibung, Adresse, Telefon, E-Mail, Bilder, `sameAs` und `openingHoursSpecification` – nur aus vorhandenen Daten; geschlossene Tage werden weggelassen.

### Performance

Karten laden Kategorien und Bilder per Fetch-Join im selben Query (Paginator mit `fetchJoinCollection`), Öffnungszeiten und Ansprechpartner werden für Listen nicht geladen. Die Detailseite lädt Kategorien und Bilder gemeinsam, Öffnungszeiten und Ansprechpartner mit je einer Abfrage. Die Startseite lädt nur sechs Unternehmen und die aktiven Kategorien samt Zählung.

### Fixtures

`DirectoryFixtures` enthält zusätzlich eine inaktive Kategorie „Archiv“ (einem aktiven Unternehmen zugeordnet, öffentlich unsichtbar), unterschiedliche Kurzbeschreibungen für die Suche und einen weiteren aktiven Ansprechpartner. `DirectoryImageFixtures` erzeugt einfarbige PNG-Platzhalter: Logo, Titelbild und zwei Galeriebilder für „Musterladen Hasebogen“, nur ein Logo für „Beispielcafé Uferpause“, keine Bilder für die übrigen Unternehmen (Fallback). Die Bilder werden über `CompanyImageStorage` in `COMPANY_UPLOAD_DIR` geschrieben. Nach wiederholtem `doctrine:fixtures:load` bleiben alte Dateien liegen; `php bin/console app:company-images:cleanup --delete` entfernt nicht mehr referenzierte Dateien, die älter als eine Stunde sind.

### PR3-Tests

Übersicht, aktive/inaktive Unternehmen, Featured-Reihenfolge, Suche (Name, Kurzbeschreibung, Beschreibung, Kategorie, Sonderzeichen, Empty State), Kategorie-Filter inkl. unbekannter/inaktiver Kategorien, Kombination mit Suche, Pagination mit erhaltenen Parametern, Detailseite (Kontakt, Öffnungszeiten, Ansprechpartner, 404-Fälle), Bildverwendung und -auslieferung, Startseite (Unternehmen, Kategorien, Hero-Suche), SEO-Metadaten, JSON-LD, Ladeverhalten der Listen sowie unveränderter Admin-Schutz.

## PR4: Karte und Umkreissuche

Das öffentliche Verzeichnis unterstützt Liste und **Karte und Liste** (`ansicht=karte`). Die Karte zeigt dieselbe paginierte Auswahl wie die Karten darunter, nicht sämtliche Treffer einer Suche. Unternehmen ohne vollständige, gültige Koordinaten bleiben ohne Radiusfilter in der Liste; auf der Karte erscheinen sie nicht. Detailseiten mit Koordinaten bieten einen Kartenabschnitt neben der Adresse. Bestehende Admin-/Mitgliedsrechte bleiben unverändert.

Leaflet **1.9.4** wird lokal über npm, Sass/esbuild und AssetMapper gebündelt (BSD-2-Clause, Lizenz in `node_modules/leaflet/LICENSE`). OpenStreetMap-Kacheln werden erst nach Klick auf „Karte laden“ abgerufen; der Hinweis erklärt die Übermittlung der IP-Adresse. Die OSM-Attribution bleibt sichtbar. Karte und Kacheln sind eine optionale Ergänzung: ohne JavaScript, bei Tile-Ausfall oder fehlenden Koordinaten bleibt die Liste mit Detaillinks nutzbar. Marker-Popups verwenden DOM-Text statt interpoliertem HTML; Marker und „Auf Karte zeigen“-Buttons synchronisieren die Hervorhebung. Auf Mobilgeräten ist die Kartenhöhe reduziert. Es gibt kein SPA und keine CDN-Script-Abhängigkeit.

### Standort, Radius und Entfernung

Das bestehende GET-Formular ergänzt **Ort / PLZ** und **Umkreis**. Beispiele:

```text
/unternehmen?ort=49740%20Hasel%C3%BCnne&radius=10
/unternehmen?lat=52.674&lng=7.484&radius=5
/unternehmen?q=caf&kategorie=gastronomie&ort=Hasel%C3%BCnne&radius=25&ansicht=karte
```

Parameter: `q`, `kategorie`, `ort` (max. 150 Zeichen), `lat`, `lng`, `radius`, `ansicht` (`liste` oder `karte`), `page`. Radien: 1, 5, 10, 25, 50, 100 km; Standard 10 km. Koordinaten werden auf endliche Zahlen, Latitude −90…90 und Longitude −180…180 geprüft. Ein vollständiges Koordinatenpaar hat Vorrang vor `ort`. Ungültige Koordinaten bzw. ungültige Radien ergeben verständliche Hinweise; ungültige Radien fallen auf 10 km zurück. Ohne auflösbaren Suchpunkt werden die übrigen Suchfilter weiterhin angewendet. Bei aktivem Suchpunkt werden Unternehmen ohne gültiges Koordinatenpaar ausgeschlossen.

`GeoPoint` kapselt Koordinaten und Haversine-Distanz (Erdmittelradius 6371,0088 km). Die Doctrine-Funktion `GEO_DISTANCE` setzt dieselbe Berechnung in MariaDB-SQL um, mit Clamp gegen Rundungsfehler. Radiusfilter, Gesamttrefferzahl, Distanzsortierung und Pagination bleiben in der Datenbank: kein Laden und Filtern aller Unternehmen in PHP. Die bestehende öffentliche QueryBuilder-Suche wird wiederverwendet; Kategorien und Bilder bleiben fetch-joined. Sortierung mit Standort: Entfernung, Featured, Name, ID; ohne Standort bleibt die PR3-Sortierung erhalten. PHP berechnet nur die Distanzanzeige für die geladene Ergebnisseite. Die SQL-Formel ist nicht indexfähig; für wesentlich größere Verzeichnisse kann später ein räumlicher Index/BBox-Vorfilter ergänzt werden. Die Kugelberechnung ist eine Luftlinienentfernung, keine Straßenroute.

Pagination und Kategoriechips erhalten die Standort-, Radius- und Ansichtsparameter. Nach erfolgreicher Ortssuche tragen Folgelinks die aufgelösten Koordinaten mit, wodurch beim Seitenwechsel kein erneutes Geocoding nötig ist. Standortfilter erhalten `noindex, follow` und einen Canonical auf `/unternehmen`. Fiktive Fixtures enthalten verschiedene Distanzen und ein Unternehmen ohne Koordinaten.

### Geocoding, Cache und Betrieb

`GeocodingServiceInterface` / `NominatimGeocodingService` lösen ausschließlich eingegebene Suchorte auf; `GeocodingResult` unterscheidet fehlende Treffer von vorübergehender Nichtverfügbarkeit. Es gibt kein automatisches Unternehmens-, Cron- oder Massengeocoding. Symfony HttpClient verwendet TLS-Prüfung, einen identifizierenden User-Agent, ein hartes Zeitlimit und keine Redirects. API-Fehler, ungültige Antworten und Cache-Ausfälle führen zu einem Hinweis statt einer Exception-Seite. Tests benutzen ausschließlich MockHttpClient bzw. ersetzte Geocoding-Services.

Symfony `cache.app` speichert normalisierte Suchorte (Cache-Key gehasht, providerabhängig), Ergebnisse und negative Treffer standardmäßig 24 Stunden; Fehler werden kurz für 30 Sekunden gecacht. Ein gemeinsames Dateilock in `kernel.cache_dir` verhindert parallele Provideraufrufe dieser Instanz und lässt höchstens einen Request pro Sekunde zu. Bei belegtem Gate wird ein kurzlebiger Hinweis zurückgegeben statt wartende Requests aufzubauen. Für mehrere Produktionsinstanzen ist ein gemeinsamer globaler Limiter oder ein eigener Geocoding-Anbieter erforderlich. Nicht die öffentliche Nominatim-Instanz für Autocomplete oder Massenabfragen verwenden; [Nominatim-Nutzungsrichtlinie](https://operations.osmfoundation.org/policies/nominatim/) und [OSM-Tile-Richtlinie](https://operations.osmfoundation.org/policies/tiles/) beachten.

Konfiguration in `.env` / `.env.local` (keine Secrets oder API-Keys nötig):

- `GEOCODING_BASE_URL`: standardmäßig `https://nominatim.openstreetmap.org`; austauschbarer kompatibler Anbieter.
- `GEOCODING_USER_AGENT`: Anwendung und Kontakt-/Projektadresse; vor Produktion passend setzen.
- `GEOCODING_TIMEOUT`: Sekunden, Standard 3.
- `GEOCODING_CACHE_TTL`: Sekunden, Standard 86400.
- `MAP_TILE_URL`: Standard `https://tile.openstreetmap.org/{z}/{x}/{y}.png`; bei Anbieterwechsel Attribution und Datenschutzhinweis ebenfalls prüfen.

Cloud-Netzwerkzugriff für serverseitiges Geocoding benötigt `nominatim.openstreetmap.org`; Tiles lädt der Browser. OSM erhält beim Kartenladen IP-Adresse und sichtbare Kartenausschnitte; Nominatim erhält den eingegebenen Ort. Geocoding-Caches enthalten Ortsauflösungen. Browser-Geolocation ist in PR4 bewusst nicht implementiert: keine automatische Standortabfrage und keine Standortpersistenz/Tracking-Funktion. Direkt angegebene Suchkoordinaten werden nur für den Request und die GET-Folgelinks verwendet; im Betrieb Querystrings möglichst aus Access-Logs entfernen. Datenschutzinformationen vor öffentlichem Betrieb ergänzen.

PR4 enthält Karten, Geodaten-Nutzung, Standort-/Radiusfilter, Entfernungsanzeige und -sortierung sowie Tests. Angebote, Veranstaltungen, News, Jobs, Gutscheine, Bewertungen, Mitglieder-Self-Service und Approval-Workflow bleiben außerhalb des Scopes. Für PR5 offen: räumliche Indizes bei größerem Datenbestand, optional Browser-Geolocation/Clustering und ein gesonderter Admin-Geocoding-Workflow.

Die Cloud-Hilfsskripte setzen zusätzlich `WK_CA_BUNDLE` auf den read-only eingebundenen öffentlichen CA-Pfad. Die optionale PHP-INI-Konfiguration verwendet ihn für `openssl.cafile` und `curl.cainfo`, damit auch Symfony HttpClient dem Plattformproxy bei aktivierter TLS-Prüfung vertraut. Außerhalb der Cloud bleibt die normale Systemtrust-Konfiguration erhalten.

## PR5: Angebote und Aktionen

`Offer` gehört genau einem `Company` (ManyToOne / OneToMany mit bidirektionalen `addOffer()` / `removeOffer()`-Helpern). Die neue Migration ergänzt die Offer-Tabelle, einen eindeutigen Slug, den Unternehmens-Fremdschlüssel mit Delete-Cascade sowie einen Index für die Zeitsteuerung. Bestehende Migrationen bleiben unverändert. Angebotstypen sind das Enum `OfferType`: Angebot, Aktion, Rabatt und Neuheit. Weitere Felder: Titel, Kurz-/Langbeschreibung, Aktivstatus, Featured, Start/Ende, optionale Preise/Rabatttext, Bild/Alternativtext, externe URL, Bedingungen und Zeitstempel.

### Sichtbarkeit und Zeitsteuerung

Alle öffentlichen Abfragen verwenden `OfferRepository::createCurrentPublicQueryBuilder()`: Angebot aktiv, Unternehmen aktiv, Beginn leer oder bereits erreicht, Ende leer oder noch nicht überschritten. Beide Zeitgrenzen sind **einschließlich**. `Offer::isCurrentlyActive($now)` und `statusAt($now)` spiegeln dieselben Regeln für Domain-/Adminanzeigen. Der Repository-Clock ist über Symfony `ClockInterface` injiziert; Tests frieren Zeit mit `MockClock` ein und prüfen auch den Ablauf ohne Datenbankänderung. Ein Cronjob ist nicht erforderlich. Ungültige Zeiträume erzeugen Formularfehler am Enddatum.

Zeiten werden im Admin in **Europe/Berlin** eingegeben und für das Datenbankmodell in UTC umgerechnet. Nutzer sehen deutsche Datums-/Uhrzeitangaben mit der lokalen Zeitzone. Die Endzeit ist ein genauer Zeitpunkt, nicht automatisch das Ende des ausgewählten Tages. Adminstatus: aktuell, geplant, abgelaufen, deaktiviert bzw. Unternehmen inaktiv. Zeitstempel verwenden weiterhin das vorhandene TimestampedTrait.

### Verwaltung

Admins und Editoren verwalten Angebote unter `/admin/offers`: gefilterte/paginierte Liste, Detailansicht, Anlegen, Bearbeiten, Aktivieren/Deaktivieren und CSRF-geschützte Löschung. Filter: Titel, Unternehmen, Typ, aktiv und Featured. Geplante, abgelaufene und deaktivierte Angebote bleiben im Admin sichtbar. Mitglieder erhalten keinen Adminzugriff. In der Unternehmensansicht verlinken „Neues Angebot für dieses Unternehmen“ und „Angebote verwalten“ in denselben Verwaltungsworkflow.

Das Formular gliedert sich in Allgemein, Zeitraum, Preis und Darstellung. Titeländerungen erhalten bestehende Slugs; ein leeres Slugfeld erzeugt einen neuen Slug über den bestehenden `DirectorySlugger` / `DirectorySlugSubscriber`. Umlaute, Kollisionen und eindeutige Datenbankconstraints entsprechen den Company-/Category-Patterns; konkurrierende Vergaben werden als Formularfehler behandelt.

### Preislogik

`regularPrice` und `offerPrice` sind nullable **DECIMAL(10,2)** und im PHP-Modell Strings. Das Formular verarbeitet ebenfalls Decimal-Strings (zum Beispiel `59,90` oder `59.90`, ohne Tausendertrennzeichen), ohne Float-Konvertierung oder stille Rundung. Negative Preise, mehr als zwei Nachkommastellen, Überläufe und ein Angebotspreis über dem vorhandenen regulären Preis werden abgewiesen. Ein Preis von null ist gültig; ein Preis von `0,00 €` wird als solcher angezeigt. Ohne Preise erscheinen keine leeren Euro-Symbole. `discountText` ist eine eigenständige kurze Angabe, beispielsweise „20 % Rabatt“ oder „2 für 1“. Es gibt keine automatische Rabattberechnung oder Money-/Commerce-Bibliothek. Formatierung und Cent-Vergleich liegen zentral im Modell, nicht in Twig.

### Bilder

Angebote verwenden **denselben `CompanyImageStorage` und Uploadordner** wie Unternehmensbilder, ebenso die gemeinsamen Image-Constraints: MIME JPEG/PNG/WebP, maximal 5 MB und 8000 × 8000 Pixel. Dateien liegen außerhalb von `public/` und erhalten zufällige Hex-Dateinamen; im Modell steht nur ein validierter Dateiname. Es gibt keine Base64-Speicherung und keinen zweiten Uploaddienst. Austausch, Entfernen und Angebots-/Unternehmenslöschung entfernen zugehörige Dateien nach erfolgreichem DB-Commit; bei fehlgeschlagenem Speichern wird das neue Bild zurückgenommen. Gleichzeitiges Hochladen und Entfernen ersetzt das Bild durch den Upload. Ohne Bild erscheint eine neutrale Kartenillustration; der Alternativtext fällt auf den Angebotstitel zurück.

Die bestehende `app:company-images:cleanup`-Bereinigung berücksichtigt zusätzlich alle Angebotsbildreferenzen, auch geplante/deaktivierte Angebote. Sie entfernt nur verwaiste Dateien, die älter als eine Stunde sind, und nur mit `--delete`. Öffentliche Angebotsbilder werden bei jedem Zugriff gegen die aktuelle Sichtbarkeit geprüft und mit `no-store` ausgeliefert; nicht aktuelle oder fremde Bildnamen ergeben 404. Admin-Bilder sind rollenbeschränkt und privat.

### Öffentliche Seiten und Integration

- `/angebote`: aktuelle Angebote, GET-Typfilter `typ=offer|promotion|discount|new_product`, optional `unternehmen=<company-slug>`, Pagination über `page`. Ungültige Typen zeigen einen Hinweis und alle Typen; unbekannte/inaktive Unternehmen liefern 404. Alle Parameter bleiben bei Pagination erhalten.
- `/angebote/{slug}`: stabile Angebotsdetailseite mit Typ, Unternehmen, Texten, optionalen Preisen/Rabatt, Laufzeit, Bild, Bedingungen und externer URL. Inaktive, abgelaufene, zukünftige Angebote sowie Angebote inaktiver Unternehmen liefern 404, ebenso unbekannte Slugs.
- Unternehmensdetails zeigen maximal drei aktuelle eigene Angebote, bei mehr einen Link zur gefilterten Gesamtübersicht. Die PR4-Karten-/Umkreissuche bleibt erhalten.
- Die Startseite ersetzt die früheren Angebotsbeispiele vollständig durch drei aktuelle Angebote: Featured zuerst, dann normale Treffer. Hauptnavigation, Footer und „Alle Angebote“ führen auf `/angebote`. Bei leerer Auswahl erscheint „Aktuell sind keine Angebote verfügbar.“

Öffentliche Sortierung: Featured, dann nächstes Ende (offene Enden zuletzt), Titel und ID. Die zentralen Queries laden das Unternehmen per Fetch-Join, keine Bilder-/Kategorien-/Kontaktgraphen; Homepage und Unternehmensdetails begrenzen ihre Abfragen in SQL. Die Unternehmensseite lädt einen vierten Treffer nur für die „Alle Angebote“-Entscheidung. Es gibt keine N+1-Abfrage pro Angebotskarte.

### SEO, Darstellung und Tests

Übersicht und Detailseiten besitzen eigene Titel/Descriptions/Canonical-URLs; Filter-/Folgeseiten erhalten `noindex, follow` mit Canonical auf `/angebote`. Details enthalten Open-Graph-Metadaten und sicher escaptes Schema.org-`Offer`-JSON-LD mit `seller`, vorhandenen Preisen/Währung, Bild und Zeitgrenzen. Es werden keine künstlichen Preise oder Verfügbarkeiten erfunden. Wiederverwendbare Twig-Partials kapseln Karte, Preis, Laufzeit und Status. Karten behalten den Portalstil, verständliche Links, Bild-Alt-Texte und responsive Raster; Preise/Rabatte sind auch als Text ausgezeichnet.

`OfferFixtures` erzeugt zehn vollständig fiktive Angebote relativ zum injizierten Clock: mehrere Unternehmen, Featured/normal, laufend/geplant/abgelaufen/deaktiviert, inaktives Unternehmen, Preise/kein Preis/Rabatt sowie generierte Bildmotive und Bild-Fallbacks. Fixture-Bilder nutzen dieselbe PNG-Erzeugung wie die vorhandenen DirectoryImageFixtures. Niemals Fixtures gegen produktive Daten laden. Neue Tests decken Domainzeit und -preise, Slugs, Queryfilter/Pagination, öffentliche 404-Regeln, Homepage/Company-Integration, echte Admin-/Editor-CRUDs, Rollen/CSRF, Bildvalidierung und den Dateilebenszyklus ab; alle Zeitfälle verwenden MockClock und bleiben unabhängig vom Kalenderjahr.

PR5 umfasst Angebote und Aktionen. **Nicht enthalten:** Gutscheincodes/Voucher-System, Gutscheinverwaltung, Warenkorb, Checkout, Onlinezahlung, Reservierung, Veranstaltungen, News, Jobs, Bewertungen, Push-Benachrichtigungen, Mitglieder-Self-Service und Approval-Workflow. Für PR6 sind diese Bereiche gesondert zu planen; es wurde keine Commerce- oder Sylius-Shop-Architektur eingeführt.


## PR6: Veranstaltungen und Kalender

### Datenmodell und Zuordnung

`Event` enthält Titel, eindeutigen stabilen Slug, Kurz-/Langbeschreibung, Aktiv-/Featured-Flag, Zeitstempel, Datum, Ganztagsflag, Wiederholung, Veranstalter, eigene Adresse, optionale Geo-Koordinaten, Hauptbild, externe Informations-/Ticketlinks, Eintrittstext und Absageinformationen. `EventCategory` ist unabhängig von den Unternehmenskategorien: Name, Slug, Beschreibung, Font-Awesome-Icon, Position, Aktivstatus und Zeitstempel. Veranstaltungen können mehrere Kategorien besitzen; deaktivierte Kategorien werden öffentlich weder als Filter noch als Labels ausgegeben.

`Company OneToMany Event` / `Event ManyToOne Company` ist optional und besitzt beidseitige Helper. Adresse und Veranstalter können unabhängig vom Unternehmen gepflegt werden. Eine Veranstaltung bleibt bei Löschung des Unternehmens bestehen (`SET NULL`); ihr Bild und ihre Termine bleiben erhalten. Der Company-Aktivstatus deaktiviert eigenständig gepflegte Veranstaltungen nicht. Links zu inaktiven Companies werden öffentlich nicht ausgegeben. Bilder bleiben Bestandteil der vorhandenen privaten Upload-Verwaltung, inklusive MIME-/Größenprüfung, sicherer Namen, Ersetzen, Entfernen und Bereinigung verwaister Dateien; keine zweite Upload-Infrastruktur.

### Zeitmodell, Wiederholung und Status

Beginn und Ende sind verpflichtende `DateTimeImmutable`-Zeitpunkte in UTC, Ende darf nicht vor Beginn liegen. Die zentrale Konfiguration `portal.timezone` ist `Europe/Berlin`; Formulare, Kalender und Darstellung verwenden diese Ortszeit. Auch die bisherigen Angebotsformulare und Laufzeitdarstellungen nutzen diese Konfiguration.

Ganztägige Veranstaltungen decken den ersten Tag ab 00:00 bis zum letzten Tag um 23:59:59 in Portal-Ortszeit ab. Es werden keine pauschalen 24-Stunden-Blöcke verwendet: Sommerzeit-/Winterzeit-Tage können 23 bzw. 25 Stunden besitzen. Mehrtägige Veranstaltungen besitzen einen zusammenhängenden Zeitraum.

`EventRecurrence` unterstützt keine, tägliche, wöchentliche und monatliche Wiederholung. `recurrenceUntil` ist ein **Kalendertag**, kein Zeitpunkt, und bezeichnet den letzten erlaubten Beginn einer Wiederholung. Wiederholungen müssen spätestens ein Jahr nach dem ersten Beginn enden; maximal 367 Vorkommen sind möglich. Ein Termin darf über diesen letzten Beginn hinauslaufen. Monatliche Wiederholungen sind am ursprünglichen Monatstag verankert: z. B. 31. Januar → 31. März, ohne Überlauf in den Februar. Fehlende Monatstage werden übersprungen. Lokale Uhrzeiten und mehrtägige Dauern folgen Kalenderarithmetik über Zeitumstellungen hinweg. Symfony übernimmt die Umwandlung eingegebener Ortszeiten; nicht existierende Uhrzeiten werden entsprechend der PHP-Zeitzonenregeln normalisiert. Falls dadurch ein Wiederholungsende vor seinen Beginn fallen würde, wird die Serie mit einem Formularfehler abgelehnt.

`EventSchedule` materialisiert die begrenzten Termine als `EventOccurrence` beim Speichern in derselben ORM-Transaktion wie Event und Bildmetadaten. Die Zeitabfragen verwenden diese indizierten Termine. Keine endlosen Serien, Hintergrundjobs oder RFC-5545-Abhängigkeiten. Unveränderte Starttermine behalten ihre Vorkommens-ID, auch bei Änderung des Inhalts oder der Endzeit. Verkürzte Serien entfernen weggefallene Termine per Orphan Removal. Ein eindeutiger Index verhindert doppelte Starttermine derselben Serie.

Der zentrale Vorkommensstatus lautet Deaktiviert, Abgesagt, Vergangen, Geplant oder Läuft. Zeitgrenzen sind einschließlich: `endsAt >= Clock::now()` ist noch nicht vergangen. Standardlisten enthalten aktive kommende und laufende Termine; vergangene aktive Detailseiten bleiben 200. Inaktive Events und fremde/unbekannte Vorkommens-IDs liefern 404. Abgesagte aktive Events bleiben öffentlich mit Textkennzeichnung und Absagehinweis sichtbar.

### Öffentliche Routen, Filter und Kalender

- `/veranstaltungen`: chronologische Liste mit Pagination (12 Termine). Kategorie/Company werden gezielt fetch-gejoint, Company-Collections bleiben ungeladen; ORM-Pagination berücksichtigt die Kategorie-Collection ohne Duplikate.
- GET `zeitraum`: `upcoming`, `today`, `tomorrow`, `weekend`, `week`, `month`. `EventDateRangeResolver` bildet lokale, halb offene Kalenderbereiche in UTC ab. Das Wochenende ist Samstag/Sonntag der laufenden Woche; laufende mehrtägige Events werden nach Zeitraumüberschneidung gefunden. Abgelaufene Termine erscheinen nicht in diesen Standardlisten.
- GET `kategorie=<slug>` und optional `unternehmen=<slug>` sind kombinierbar; Filterzustand bleibt erhalten. Unbekannte/inaktive Filterentitäten liefern 404, unbekannte Zeitraumwerte fallen auf kommende Termine zurück.
- `/veranstaltungen/kalender?month=YYYY-MM`: serverseitiger Monatskalender mit vorherigem/nächstem Monat und Kategorie-/Company-Filter. Ungültige oder nicht skalare Monatswerte fallen auf den aktuellen Clock-Monat zurück. Der Monatsfilter umfasst die angezeigten Randtage der Kalenderwochen. Hier bleiben auch vergangene aktive Termine sichtbar. Mehrtägige Termine erscheinen an jedem betroffenen Tag, ohne neue Entities dafür zu erzeugen. Auf Mobile wird der Kalender zu einer semantischen Tagesliste; die alternative Eventliste bleibt erreichbar. Der Listen-Zeitraumfilter gilt nicht zusätzlich im Kalender.
- `/veranstaltungen/{slug}`: nächste laufende/kommende Instanz einer aktiven Serie, nach Serienende die letzte Instanz.
- `/veranstaltungen/{slug}/termine/{id}`: spezifisches Vorkommen derselben Serie mit eigener Canonical-URL; Kalender und Karten verlinken auf diesen Termin.
- `/veranstaltungen/{slug}/bilder/{fileName}`: geprüftes öffentliches Hauptbild, nur für aktive Events, `nosniff` und keine dauerhafte Cache-Freigabe.

Die Startseite zeigt drei kommende Featured-Termine, füllt bei Bedarf mit normalen Terminen auf und sortiert die Auswahl chronologisch. Unternehmensdetailseiten zeigen die nächsten drei Company-Termine und bei Bedarf einen gefilterten Link zu allen Veranstaltungen. Ohne Termine wird der Company-Abschnitt ausgeblendet. Navigation und Footer führen auf die echte Veranstaltungsroute.

### Administration, Geo und SEO

Admins und Editoren verwalten Veranstaltungen unter `/admin/events` sowie Veranstaltungskategorien unter `/admin/event-categories`; Mitglieder bleiben ausgeschlossen. Eventliste mit Titel-, Company-, Kategorie-, Aktiv-, Featured-, Status- und Zeitraumfiltern sowie Pagination (25 Events), vollständigem CRUD, Absage und Company-Kontextlinks. Zugeordnete Eventkategorien müssen vor der Löschung von ihren Events gelöst werden. Alle schreibenden Formulare und Löschaktionen sind CSRF-geschützt. Das Dashboard zählt kommende Termine, kommende Termine im aktuellen Monat und hervorgehobene Termine; es zählt Vorkommen, nicht Serien.

Geo-Koordinaten werden als Paar validiert. Die Eventdetailkarte erweitert die vorhandenen `DirectoryMap`-/Leaflet-Komponenten aus PR4, mit bewusstem Laden, bestehendem OSM-Tile-Anbieter, sicheren Text-Popups und externem Route-planen-Link. Ohne Koordinaten erscheint keine leere Karte. Koordinaten können im Admin manuell gepflegt werden; automatisches Event-Geocoding und eine zusätzliche Übersichtskarte sind nicht Teil dieser Umsetzung.

Übersicht/Detail besitzen Titel, Beschreibung und Canonical; Kalender, Filter- und Paginationvarianten werden nicht zusätzlich indexiert. Detailseiten enthalten Open Graph und sicher hex-kodiertes Schema.org-`Event` für das konkret ausgewählte Vorkommen: Datum/Zeiten, optional Bild, tatsächlichen Ort, Veranstalter und `EventCancelled` bei Absage. Keine erfundenen Preise, Tickets oder Verfügbarkeiten. Ganztägige Structured-Data-Daten verwenden Kalendertage statt künstlicher Uhrzeiten.

### Migration, Fixtures und Prüfung

`Version20261005192631` ergänzt ausschließlich Event, EventCategory, EventOccurrence und die Kategorie-Zuordnung, inklusive Indizes und Fremdschlüsseln. Bestehende Migrationen bleiben unverändert.

`EventFixtures` erzeugt zwölf fiktive Veranstaltungen und neun aktive Kategorien sowie eine inaktive Testkategorie relativ zur injizierbaren Clock: heute, morgen, Wochenende, nächster Monat, Featured/normal, vergangen, inaktiv, ganztägig, mehrtägig, externe Veranstalter, Company, Bilder/Fallback, Geo/kein Geo, Absage und begrenzte wöchentliche Serie. Entwicklung mit `doctrine:fixtures:load` wie zuvor; dies löscht vorhandene Daten und gehört nicht in Produktivsysteme.

Neue Domain-/Integrationstests decken Statusgrenzen, Relations, Slugs, Datumskonsistenz, tägliche/wöchentliche/monatliche Serien, Serienende, maximale Anzahl, fehlende Monatstage, stabile Vorkommens-URLs, DST, Datumsfilter, Monatsnavigation, mehrtägige Kalenderbelegung, Rollen/CSRF, CRUD, Kategorie-Löschschutz, Uploads/Cleanup, Company-Löschung, SEO und Pagination mit Collection-Fetch-Joins ab. Die bestehenden PR1–PR5-Tests bleiben unverändert erhalten. JavaScript-Tests prüfen zusätzlich Eventmarker und die Ablehnung unsicherer Popup-URLs. Vollständige Checks entsprechen dem bestehenden CI-Workflow.

**Außerhalb von PR6:** Ticketshop, Reservierungen, Zahlungsabwicklung, Sitzplätze, QR-Tickets, Gutscheine, News, Jobs, Bewertungen, Push Notifications, Mitglieder-Self-Service und Approval-Workflow. Ein Ticketlink ist ausschließlich ein externer Link. Weitere Kalenderfunktionen wie individuelle Ausnahmen/verschobene Serientermine, ICS und ausgewählte Wochentage sind mögliche spätere Erweiterungen.


## PR7: News und redaktionelle Inhalte

### Datenmodell, Inhalt und Zuordnung

`NewsArticle` enthält Titel, eindeutigen stabilen Slug, Teaser (maximal 500 Zeichen), verpflichtenden Inhalt, Featured-Flag, Zeitstempel, Status, optionalen Veröffentlichungszeitpunkt, Hauptbild mit Alternativtext, optionalen Autor und externen Link. `NewsCategory` ist unabhängig von Company- und Eventkategorien: Name, Slug, Beschreibung, manuelle Position, Aktivstatus und Zeitstempel. `NewsArticle ManyToMany NewsCategory` unterstützt mehrere Kategorien. Öffentliche Filter und Labels enthalten ausschließlich aktive Kategorien.

`NewsArticle ManyToOne Company` / `Company OneToMany NewsArticle` ist optional und besitzt beidseitige Helper. Allgemeine Werbekreis-News funktionieren ohne Company. Bei Company-Löschung bleibt der Artikel mit Bild bestehen und wird per `SET NULL` bzw. expliziter Helper-Aktualisierung gelöst. Die Veröffentlichung ist unabhängig vom Company-Aktivstatus; eine inaktive Company wird öffentlich nicht verlinkt. Auf aktiven Unternehmensdetailseiten erscheinen nur deren eigene öffentlichen Beiträge.

Der Inhalt ist **Klartext** aus einem normalen Textarea. Twig escaped alle Inhalte und erhält Zeilenumbrüche über `nl2br`; eingegebenes HTML wird als Text angezeigt. Kein HTML-Renderer, Markdown-Paket, WYSIWYG oder Page Builder. JSON-LD wird separat mit den bestehenden JSON-HEX-Flags sicher ausgegeben. Externe URLs sind auf HTTP/HTTPS beschränkt.

Slugger und Formular-Subscriber werden um die beiden Entities erweitert, ohne eine weitere Slug-Implementierung. Titeländerungen erhalten bestehende Slugs, leere Slugs werden mit Kollisionssuffix vergeben und ein Unique-Index sichert die DB. Die private Upload-Lösung wird um Newsbilder erweitert: echte MIME-Prüfung, 5-MB-/8000-Pixel-Grenze, sichere Dateinamen, Alternativtext/Fallback, Ersetzen/Entfernen nach erfolgreichem DB-Commit und Cleanup unter Berücksichtigung aller noch referenzierten Newsbilder. Kein Base64 und keine neue Upload-Infrastruktur.

### Statusmodell und Veröffentlichung

`NewsStatus` ist ein PHP-Enum mit `draft`, `scheduled`, `published`. Die Domainmethode `isPubliclyVisible(now)` und der zentrale `NewsArticleRepository::createPublicQueryBuilder()` verwenden dieselbe Regel:

- `draft` bleibt unabhängig vom Datum unsichtbar.
- `published` ist öffentlich, wenn `publishedAt` leer oder einschließlich `<= Clock::now()` ist. Ein explizites zukünftiges Datum bleibt bis dahin unsichtbar.
- `scheduled` benötigt ein Datum und wird bei dessen Erreichen automatisch öffentlich. Der gespeicherte Status bleibt `scheduled`; das effektive Statuslabel zeigt dann „Veröffentlicht“. Es ist kein Cronjob oder Hintergrundprozess erforderlich.

Beim Speichern eines veröffentlichten Beitrags ohne Datum setzt `NewsPublishing` den Zeitpunkt aus der injizierten Clock. Neue/geänderte Planungen benötigen einen zukünftigen Zeitpunkt. Bereits fällige gespeicherte Planungen bleiben für Inhaltskorrekturen bearbeitbar; ihre Minute, gespeicherten Sekunden und eine gegebenenfalls mehrdeutige DST-Ortszeit werden durch unveränderte Datumsfelder nicht verschoben. Wieder auf Entwurf setzen entfernt den Beitrag sofort von allen öffentlichen Seiten und der Bildroute; das Datum kann für redaktionelle Historie erhalten bleiben.

Zeitpunkte werden als `DateTimeImmutable` in UTC gespeichert, Formulare und Frontend verwenden die zentrale `portal.timezone` (`Europe/Berlin`). Sichtbarkeit und Veröffentlichungs-/Änderungszeitstempel verwenden Symfony Clock; Tests setzen MockClock. Artikel ohne explizites Datum verwenden ihren gespeicherten `createdAt` als sichtbares Datum, Sortierungsdatum und `datePublished`-Fallback. Neu angelegte redaktionelle Artikel erhalten bei Veröffentlichung stets einen expliziten Clock-Zeitpunkt. Kein Datum wird nur für SEO erfunden.

### Administration und öffentliche Seiten

Admins und Editoren verwalten Beiträge unter `/admin/news` und Kategorien unter `/admin/news-categories`. Mitglieder und anonyme Nutzer besitzen keinen Adminzugriff. Beitrags-CRUD einschließlich Anzeige, Entwurf, Planung, Veröffentlichung, Featured, Company, Kategorien, Autor, externem Link und Bildverwaltung. Liste mit Titel-, Company-, Kategorie-, gespeichertem Status- und Featured-Filtern, effektiven Statuslabels, Veröffentlichungs-/Änderungsdatum und Pagination (25 Beiträge). Die News-Kategorieliste ist ebenfalls paginiert; zugeordnete Kategorien müssen vor dem Löschen von ihren Artikeln gelöst werden. Bestehende CSRF-geschützte Formular-/Löschpatterns bleiben erhalten. Company-Kontextlinks und einfache Dashboard-Zahlen für öffentliche News, Entwürfe und gespeicherte Planungen sind ergänzt; fällige Planungen zählen weiterhin zum gespeicherten Planungstyp.

- `/aktuelles`: öffentliche chronologische Übersicht, 12 Beiträge pro Seite. Sortierung nach `COALESCE(publishedAt, createdAt) DESC`, danach `createdAt DESC` und ID als stabiler Tie-Breaker. Featured kennzeichnet Karten und verändert die Übersichts-Chronologie nicht.
- `?kategorie=<slug>`: aktive NewsCategory; optional `?unternehmen=<company-slug>` nach dem bestehenden Offer-/Event-Pattern. Kombinierte Filter und Pagination erhalten ihre Parameter. Unbekannte/inaktive Filterentitäten liefern 404. Nicht skalare Parameter werden sicher behandelt.
- `/aktuelles/{slug}`: nur öffentlich sichtbare Beiträge; Entwürfe, zukünftige Planungen/veröffentlichte Beiträge und unbekannte Slugs liefern 404. Alte veröffentlichte Artikel bleiben über Detail-URLs und Pagination erreichbar; keine zusätzliche Monats-/Jahresarchivroute.
- `/aktuelles/{slug}/bilder/{fileName}`: geprüftes öffentliches Bild, gleiche Veröffentlichungsregel, `nosniff` und keine dauerhafte Cache-Freigabe.

Öffentliche Karten zeigen Bild/Fallback, Datum mit semantischem `time`, aktive Kategorien, Titel, Teaser und Company, gegebenenfalls Featured-Kennzeichnung. Detailseiten ergänzen den vollständigen sicheren Klartext, optional Autor und externen Link.

Die Startseite erhält eine echte Aktuelles-Sektion (zuvor keine eigene News-Sektion): drei öffentliche Featured-Beiträge, mit neuesten normalen Beiträgen aufgefüllt und innerhalb der Auswahl chronologisch ausgegeben. Die Unternehmensseite zeigt deren drei neueste öffentliche Beiträge; bei mehr Ergebnissen führt ein gefilterter Link zur Übersicht. Ohne Beiträge bleibt dieser Abschnitt ausgeblendet. Hauptnavigation und Footer besitzen einen echten Aktuelles-Link; die vorhandenen Navigationspunkte bleiben erhalten.

### Performance, SEO und Fixtures

Öffentliche Listen nutzen skalare Kartenprojektionen **ohne Hauptinhalt** und eine zusätzliche begrenzte Kategorieabfrage für die ausgewählten Artikel-IDs. Damit gibt es keine N+1-Queries und keine teilweise geladenen Entities im Identity Map. Company-Daten enthalten nur Name, Slug und Aktivstatus. Detailabfragen laden den vollständigen Artikel mit gezielten Company-/Kategorie-Fetch-Joins, ohne Company-Collections. Admin-Pagination berücksichtigt Kategorie-Collection-Joins ohne doppelte oder fehlende Artikel.

Übersicht/Detail verwenden die bestehende SEO-Struktur mit Title, Teaser-Description und Canonical. Filter-/Paginationvarianten erhalten `noindex,follow`. Detailseiten enthalten Open Graph mit `og:type=article` und gegebenenfalls Bild. Schema.org-`NewsArticle` enthält tatsächliche Headline, Beschreibung, gespeicherte Veröffentlichungs-/Änderungsdaten, Bild, Autor (nur wenn vorhanden), konfigurierten Portal-Publisher und `mainEntityOfPage` mit eigener Slug-URL.

`Version20261005200912` ergänzt ausschließlich NewsArticle, NewsCategory und deren Zuordnung, einschließlich eindeutiger Slugs, Veröffentlichungsindex und optionaler Company-FK. Bestehende Migrationen bleiben unverändert.

`NewsFixtures` erzeugt zwölf fiktive Artikel, acht aktive Kategorien und eine inaktive Testkategorie relativ zur Clock: veröffentlicht, Featured/normal, Entwurf, künftige/fällige Planung, veröffentlicht mit künftigem Zeitpunkt, Company/allgemein, mehrere Kategorien, Bilder/Fallback und ältere Archivartikel. Entwicklung wie zuvor über `doctrine:fixtures:load`; dies löscht vorhandene Daten und gehört nicht in Produktivsysteme. Test-Fixtures verwenden dieselbe stabile MockClock.

Neue Tests decken Statusgrenzen, Clock-Fortschritt, Formvalidierung, fällige Planungsbearbeitung mit Sekundenerhalt, Relations, Slugs, Rollen/CSRF, Kategorie-Löschschutz, Uploads/Cleanup, Company-Löschung, Kartenprojektionen ohne Inhalt/Entities, Sortierung, kombinierte Filter, Pagination, Homepage/Company-Integration, HTML-/JSON-LD-Sicherheit und SEO ab. Alle bestehenden PR1–PR6-Tests bleiben unverändert erhalten; Checks entsprechen dem vorhandenen CI-Workflow.

**Außerhalb von PR7:** Page Builder/WYSIWYG, Kommentare, Likes, Social Login, Newsletter-Versand, Push Notifications, Jobs, Gutscheine, Bewertungen, Mitglieder-Self-Service und Approval-Workflow. Redaktionelle Revisionen, Autorenzuordnung zu Benutzerkonten und ein eigenes Monats-/Jahresarchiv sind mögliche spätere Erweiterungen.


## PR8: Jobs und Stellenangebote

`JobPosting` gehört verpflichtend zu genau einer `Company`. Die Company-Helper halten beide Seiten konsistent; beim Löschen eines Unternehmens werden seine Stellen gelöscht, entsprechend dem vorhandenen Offer-Pattern. Inaktive Unternehmen haben keine öffentlich sichtbaren Jobs. Arbeitsort und Koordinaten werden beim Anlegen im Company-Kontext einmalig kopiert, sind unabhängig bearbeitbar und werden durch spätere Company-Adressänderungen nicht überschrieben. Ohne Company-Kontext wird der Ort ausdrücklich eingetragen. Titel werden unverändert übernommen; es gibt keine automatische Ergänzung von Genderzusätzen.

Das Modell enthält Titel, eindeutigen stabilen Slug, Kurzbeschreibung (maximal 500 Zeichen), erforderliche Beschreibung, optionale Anforderungen/Benefits/Referenznummer und Arbeitsbeginn sowie Featured, Clock-Zeitstempel, Veröffentlichung, Frist, Beschäftigungsart, Arbeitsmodell, Ort/Adresse/optionales Land/Koordinaten, Bewerbungskontakt und optionale Gehaltsgrenzen. Slugs verwenden die vorhandene `DirectorySlugger`-Infrastruktur einschließlich Umlautumschreibung, Kollisionssuffix und UniqueEntity/DB-Absicherung. Inhaltsfelder sind Klartext und werden mit Twig-Escaping und Zeilenumbrüchen ausgegeben. Keine WYSIWYG-Abhängigkeit und kein ungeprüftes HTML.

### Beschäftigung und Gehalt

`EmploymentType` enthält Vollzeit, Teilzeit, Minijob, Ausbildung, Praktikum, Werkstudent und Befristet. Deutsche Labels und Schema-Mapping liegen zentral im Enum: `FULL_TIME`, `PART_TIME`, `PART_TIME`, `OTHER`, `INTERN`, `PART_TIME`, `TEMPORARY`. Ausbildung wird bewusst als `OTHER` abgebildet, ohne einen nicht standardisierten Google-Beschäftigungswert zu erfinden. `WorkModel` unterscheidet Vor Ort, Hybrid und Remote. Vor Ort/Hybrid erfordern einen Ort; Remote erlaubt einen leeren Ort.

`salaryMin` und `salaryMax` sind nullable `DECIMAL(10,2)` und bleiben in Speicherung, Formular und Validierung Strings. Das Offer-Money-Pattern normalisiert Dezimalbeträge ohne Rundung, vergleicht ganzzahlige Cent-Beträge und erlaubt keine negativen Werte, mehr als zwei Nachkommastellen oder Mindestgehalt oberhalb des Höchstgehalts. Ein einzelner Grenzwert ist erlaubt; bei einer Gehaltsangabe ist `SalaryPeriod` (pro Stunde/Monat/Jahr) erforderlich. Die Formularangaben und Darstellung verwenden Euro; nur bei der Ausgabe numerischer JSON-LD-Werte findet eine Darstellungskonvertierung statt.

### Veröffentlichung, Scheduling und Ablauf

Jobs verwenden das PR7-Statusmodell `NewsStatus` (`draft`, `scheduled`, `published`) und den bestehenden `NewsPublishing`-Service, der nun beide Content-Entities unterstützt. Der historische Name bleibt zur Vermeidung eines unnötigen PR7-Umbaus erhalten. Der Service setzt beim Veröffentlichen ohne Zeitpunkt die aktuelle Symfony-Clock-Zeit. Neue/geänderte Planungen erfordern einen zukünftigen Zeitpunkt; unveränderte bereits verstrichene Planungen bleiben bearbeitbar. Unveränderte Minutenfelder behalten gespeicherte Sekunden und das ursprüngliche Datum bei mehrdeutigen Sommerzeitstunden.

Öffentlich sichtbar sind nur veröffentlichbare Jobs aktiver Unternehmen: Veröffentlichung leer oder erreicht, geplante Stellen mit vorhandenem erreichtem Zeitpunkt, Bewerbungsfrist leer oder noch nicht überschritten. Beide Zeitgrenzen sind einschließlich. Diese Regeln stehen zentral im Repository und als Domain-Prädikat für dieselben Clock-Werte; Controller enthalten keine eigenen Zeitregeln. Kein Cronjob oder Status-Update ist erforderlich. Entwürfe, zukünftige und abgelaufene Stellen liefern öffentlich 404, bleiben im Admin sichtbar. `createdAt`, `updatedAt` und Veröffentlichung verwenden Symfony Clock.

Das Formular behandelt `validThrough` als **letzten lokalen Bewerbungstag** in `portal.timezone` (standardmäßig Europe/Berlin): UTC-Speicherung des Tagesendes `23:59:59`, einschließlich korrekter kurzer/langer Tage bei Zeitumstellung. Programmatisch gesetzte Zeitstempel behalten ihre genaue Ablaufgrenze bis zur nächsten Formularbearbeitung; die Fixtures verwenden relative Clock-Zeitpunkte. Die Frist darf nicht vor einem expliziten oder beim Speichern gesetzten Veröffentlichungszeitpunkt liegen. Eine bereits abgelaufene Stelle kann im Admin bearbeitet oder verlängert werden.

### Administration und Bewerbung

`/admin/jobs` bietet die Liste mit Titel, Company, Beschäftigungsart/Arbeitsmodell, Ort, effektivem Status, Veröffentlichung, Frist, Featured und Änderung. Titel, Company, Beschäftigungsart, Arbeitsmodell, gespeicherter Status und Featured sind kombinierbar; 25 Stellen pro Seite und Filtererhalt einschließlich `featured=0`. Admins und Editoren können erstellen, anzeigen, bearbeiten, planen, veröffentlichen, auf Entwurf setzen und mit CSRF-Schutz löschen. Mitglieder haben keinen Adminzugriff. Company-Adminseiten verlinken auf vorbelegte neue Stellen und die gefilterte Verwaltung. Das Dashboard zeigt offene, geplante und innerhalb der nächsten sieben Tage auslaufende Stellen.

Mindestens Bewerbungs-URL, eigene Bewerbungs-E-Mail oder Company-E-Mail muss vorhanden sein. Die externe HTTP(S)-URL führt über „Jetzt bewerben“ mit sichtbarem Hinweis auf den neuen Tab sowie `noopener noreferrer`; E-Mail führt zu `mailto:`. Wenn keine eigene E-Mail angegeben ist, wird die Company-E-Mail verwendet. Ansprechpartner, Telefon und Referenznummer sind optional. Es werden keine Bewerbungen oder Lebensläufe im Portal erfasst. Jobs verwenden Company-Cover/Logo für Open Graph; ein zusätzliches Job-Upload-System wurde nicht eingeführt.

### Öffentliche Seiten und Integration

- `/jobs`: öffentlich sichtbare Stellen, Suche `q` in Titel, Kurzbeschreibung, vollständiger Beschreibung, Company-Name und Ort; kombinierbare GET-Filter `employmentType`, `workModel`, `unternehmen={company-slug}`.
- `/jobs/{slug}`: stabile Detail-URL mit Beschreibung, Anforderungen/Benefits, Beschäftigung, Ort, Bewerbung, optionalem Gehalt, Frist und Company-Link.
- Listen zeigen zwölf Ergebnisse pro Seite, erhalten sämtliche Filter und bieten freundliche Leerzustände mit Zurücksetzen. Unbekannte konkrete Filter/Companies und nicht vorhandene Seiten liefern 404; nicht skalare Parameter werden defensiv ignoriert.
- Reihenfolge: Featured zuerst, dann Veröffentlichung (bei fehlendem Datum gespeicherter Erstellungszeitpunkt) absteigend, Titel aufsteigend und ID als stabiler Tie-Breaker.
- Die bisher statische Homepage-Jobsektion ist durch drei öffentliche Featured-Stellen mit aktuellen normalen Stellen als Fallback ersetzt. Header und Footer führen auf `/jobs`.
- Unternehmensdetailseiten zeigen bis zu drei eigene offene Stellen unter „Offene Stellen“, bei mehr Stellen einen Company-gefilterten Listenlink; ohne Treffer bleibt der Abschnitt verborgen.
- Karten verwenden Scalar-Projektionen ohne volle Beschreibung und ohne verwaltete Company-Graphen. Die Suche bleibt eine parametergebundene Doctrine-Abfrage ohne neue Such-Dependency; SQL-Wildcards werden escaped.
- Gespeicherte Koordinaten verwenden die bestehende PR4-`DirectoryMap`-/Leaflet-Lösung mit ausdrücklichem Laden und Datenschutzhinweis. Ohne Koordinaten wird keine leere Karte dargestellt. Automatische Geocodierung ist kein Pflichtbestandteil von PR8.

### SEO und JobPosting-Daten

Übersicht und Detail erhalten eigene Titel/Descriptions, Detail-Canonical und Open Graph mit vorhandenem Company-Bild. Gefilterte/paginierte Listen verwenden den Canonical `/jobs` und `noindex,follow`, entsprechend PR7. Sicher HEX-encodiertes JSON-LD enthält `JobPosting`, Titel, escaped Beschreibung einschließlich vorhandener Anforderungen/Benefits, tatsächlichen `datePosted`, optionales `validThrough`, zentrales Beschäftigungs-Mapping, `hiringOrganization`, URL und vorhandene PostalAddress-/Geo-Daten. Fehlendes Veröffentlichungsdatum nutzt ausschließlich den gespeicherten Erstellungszeitpunkt.

Nur vorhandene Gehaltsdaten erzeugen `baseSalary` mit EUR und `HOUR`/`MONTH`/`YEAR`; einzelne Grenzwerte bleiben einzelne Grenzwerte. Remote erzeugt `jobLocationType=TELECOMMUTE`; Hybrid und Vor Ort tun dies nicht. Ohne Remote-Standortrestriktion wird kein `applicantLocationRequirements` erfunden. Es werden keine fehlenden Länder, Adressen, Gehälter oder Autoren erfunden. JobPosting-Daten orientieren sich an Schema.org/Google Jobs; Aufnahme oder Darstellung durch Google wird nicht garantiert. Das optionale Land des Arbeitsorts wird ausdrücklich im Formular erfasst und nur bei vorhandenen Daten als `addressCountry` ausgegeben. Remote-Stellen ohne gespeicherte Standortrestriktion können zusätzliche redaktionelle Angaben für Suchmaschinen benötigen.

### Migration, Fixtures und Tests

`Version20261005205439` ergänzt ausschließlich die neue JobPosting-Tabelle, eindeutige Slugs, Veröffentlichung-/Beschäftigungsindizes und die erforderliche Company-FK mit Cascade. Frühere Migrationen bleiben unverändert. `JobFixtures` hängt von `DirectoryFixtures` ab und erzeugt zwölf ausschließlich fiktive Stellen für mehrere Beispielunternehmen relativ zur Clock: alle sieben Beschäftigungsarten, alle drei Arbeitsmodelle, Featured/normal, Entwurf/geplant/veröffentlicht/abgelaufen/zukünftig, mit/ohne Gehalt, E-Mail/externe URL, unterschiedliche Orte und offene Fristen.

Die Jobs-Tests prüfen Domain- und Repository-Sichtbarkeit, sekundengenaue Grenzen mit MockClock, Veröffentlichung und Ablauf, Beziehungen, Salary-Validierung, Labels/Schema-Mapping, Suche/Filter, Featured/Fallback, Pagination, Admin-/Editor-CRUD, Member-Verbot, CSRF, Sekunden-/Sommerzeit-Erhalt, Company-E-Mail-Fallback und Löschkaskade, XSS, SEO/JSON-LD, Karte und Scalar-Projektionen. Vorhandene PR1–PR7-Tests bleiben unverändert. Zusätzlich erfolgen Composer-/Symfony-/Twig-/Container-/Schema-Prüfungen, npm-Install/Build/Tests, AssetMapper und Browser-Smoketests auf Desktop/Tablet/Mobil.

### Scope und mögliche Folgearbeiten

PR8 enthält keine Bewerberkonten, interne Online-Bewerbung, Lebenslauf-Uploads/-Verwaltung, ATS, Bewerbungshistorie, Messaging, Interviewplanung, Newsletter, Gutscheine, Bewertungen, Self-Service oder Approval-Workflow. Denkbare spätere Erweiterungen: explizite Remote-Regionangaben für detailliertere Suchmaschinen-Anforderungen, Admin-Geocodierung und weitere redaktionelle Filter. Diese sind keine verdeckten Bewerbermanagement-Funktionen.
