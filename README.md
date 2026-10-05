# Werbekreis Haselünne

Technisches Grundgerüst für ein lokales Stadtportal. PR1 enthält Anmeldung, Benutzerverwaltung, Admin-Dashboard, einen Mitglieder-Platzhalter und eine statische öffentliche Startseite. PR2 ergänzt die Stammdatenverwaltung für Unternehmen und Kategorien im bestehenden Adminbereich. PR3 macht daraus das öffentliche Unternehmensverzeichnis mit Suche, Kategorie-Filter, Detailseiten und datengetriebener Startseite. PR4 ergänzt Karte und Umkreissuche. PR5 ergänzt echte Angebote und Aktionen; Veranstaltungen und Jobs auf der Startseite bleiben ausdrücklich Beispielinhalte.

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
