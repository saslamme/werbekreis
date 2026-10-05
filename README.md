# Werbekreis Haselünne

Technisches Grundgerüst für ein lokales Stadtportal. PR1 enthält Anmeldung, Benutzerverwaltung, Admin-Dashboard, einen Mitglieder-Platzhalter und eine statische öffentliche Startseite. Unternehmen, Angebote, Veranstaltungen und Jobs auf der Startseite sind ausdrücklich Beispielinhalte; die Datenmodelle und Funktionen folgen später.

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

- `ROLE_ADMIN`: Dashboard und Benutzerverwaltung.
- `ROLE_EDITOR`: Dashboard; keine Berechtigung zur Benutzer- oder Rollenverwaltung.
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

Unternehmensdaten und Kategorien, echte Suche, Angebote, Veranstaltungen, News, Jobs, Dokumente, Karten, Mitgliedsinhalte, Freigabeverfahren und Gutscheinfunktionen sind außerhalb von PR1. Statische Vorschaukarten erzeugen keine entsprechenden Entities.

## Verwaltete Cloud-Umgebung ohne PHP auf dem Host

`scripts/cloud-install.sh` baut die PHP-Laufzeit, installiert beide Lockfiles und kompiliert die Assets. Docker übernimmt den von der Plattform konfigurierten Proxy; das öffentliche CA-Bundle wird nur während Builds bzw. read-only zur Laufzeit eingebunden. Composer läuft mit der UID/GID des Benutzers. `scripts/cloud-php.sh` führt einzelne datenbankunabhängige PHP-/Composer-Befehle aus. Datenbankbefehle benötigen zusätzlich das interne Docker-Netz und die dazu passende `DATABASE_URL`; die gespeicherten Startanweisungen der Cloud-Umgebung enthalten den getesteten Ablauf. Keine zusätzlichen Worktrees anlegen.

Docker-Testdatenbank konkret vorbereiten:

```sh
docker compose exec database mariadb -uroot -plocal-root-password -e "CREATE DATABASE IF NOT EXISTS werbekreis_test; GRANT ALL ON werbekreis_test.* TO 'app'@'%';"
docker compose run --rm -e APP_ENV=test php php bin/console doctrine:migrations:migrate --no-interaction
docker compose run --rm php php bin/phpunit
```

In der Cloud startet `scripts/cloud-start.sh` ausschließlich die projektspezifischen Container und prüft die Homepage per HTTP. Fixtures werden dabei absichtlich nicht automatisch geladen, weil sie vorhandene Daten löschen würden. Für eine neue, leere Entwicklungsdatenbank einmal `docker exec wk-pr1-php php bin/console doctrine:fixtures:load --no-interaction` ausführen.
