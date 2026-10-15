<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261015090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add fixed CMS pages, structured plain-text sections and board members; preserve PR13/PR14 content.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE ContentPage (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(20) NOT NULL, title VARCHAR(180) NOT NULL, lead LONGTEXT DEFAULT NULL, metaTitle VARCHAR(180) DEFAULT NULL, metaDescription VARCHAR(300) DEFAULT NULL, createdAt DATETIME NOT NULL, updatedAt DATETIME NOT NULL, UNIQUE INDEX UNIQ_CA24D9C677153098 (code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE ContentPageSection (id INT AUTO_INCREMENT NOT NULL, heading VARCHAR(180) DEFAULT NULL, body LONGTEXT NOT NULL, position INT NOT NULL, style VARCHAR(20) NOT NULL, createdAt DATETIME NOT NULL, updatedAt DATETIME NOT NULL, page_id INT NOT NULL, INDEX content_section_order (page_id, position, id), INDEX IDX_55832383C4663E4 (page_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE BoardMember (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(180) NOT NULL, role VARCHAR(180) NOT NULL, position INT NOT NULL, active TINYINT NOT NULL, createdAt DATETIME NOT NULL, updatedAt DATETIME NOT NULL, INDEX board_public_order (active, position, name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE ContentPageSection ADD CONSTRAINT FK_55832383C4663E4 FOREIGN KEY (page_id) REFERENCES ContentPage (id) ON DELETE CASCADE');

        // Immutable snapshot of the existing PR13/PR14 templates. No runtime entity/service dependency.
        // Fixed contact tokens keep centrally configured portal details authoritative.
        $pages = json_decode(<<<'JSON'
[
  {
    "code": "about",
    "title": "Über den Werbekreis Haselünne",
    "lead": "Gemeinsam für einen starken, lebendigen Standort.",
    "metaTitle": "Über uns | {portal_name}",
    "metaDescription": "Der Werbekreis Haselünne: lokale Unternehmen stärken, gemeinsame Aktionen gestalten und den Standort beleben.",
    "sections": [
      {
        "heading": "Gemeinsam für den Standort",
        "body": "Der {organization} ist die Gemeinschaft engagierter Unternehmen aus {city}. Wir machen lokalen Handel, Gastronomie, Dienstleistungen und weitere Unternehmen sichtbar und stärken gemeinsam die Attraktivität unseres Standortes.\n\nDer Werbekreis unterstützt und organisiert Aktionen und Veranstaltungen, fördert die Zusammenarbeit der Unternehmen und schafft zusätzliche Anlässe, Haselünne zu besuchen, vor Ort einzukaufen und lokale Angebote zu entdecken. Dazu gehört auch die Zusammenarbeit mit der Stadt und weiteren lokalen Akteuren.",
        "style": "default",
        "position": 0
      },
      {
        "heading": "Lokal stärken",
        "body": "Wir setzen uns für die Unternehmen vor Ort ein und machen ihre Angebote sichtbar.",
        "style": "feature",
        "position": 10
      },
      {
        "heading": "Gemeinsam sichtbar",
        "body": "Mit einem gemeinsamen Auftritt und abgestimmten Aktionen erreichen wir mehr Menschen.",
        "style": "feature",
        "position": 20
      },
      {
        "heading": "Haselünne erleben",
        "body": "Veranstaltungen und gemeinsame Aktionen beleben die Innenstadt und den gesamten Standort.",
        "style": "feature",
        "position": 30
      },
      {
        "heading": "Unternehmen vernetzen",
        "body": "Wir fördern den Austausch und die Zusammenarbeit unserer Mitgliedsunternehmen.",
        "style": "feature",
        "position": 40
      }
    ]
  },
  {
    "code": "board",
    "title": "Unser Vorstand",
    "lead": "Der Werbekreis lebt vom ehrenamtlichen Engagement seiner Mitglieder.",
    "metaTitle": "Vorstand | {portal_name}",
    "metaDescription": "Lernen Sie die Mitglieder und Funktionen des Vorstands des Werbekreises Haselünne kennen.",
    "sections": []
  },
  {
    "code": "membership",
    "title": "Mitglied werden",
    "lead": "Gemeinsam mehr für Haselünne bewegen.",
    "metaTitle": "Mitglied werden | {portal_name}",
    "metaDescription": "Mitglied im Werbekreis Haselünne werden: gemeinsamer Auftritt, lokale Sichtbarkeit, Aktionen und Vernetzung.",
    "sections": [
      {
        "heading": "Was eine Mitgliedschaft bietet",
        "body": "Im Werbekreis engagieren sich Unternehmen gemeinsam für einen lebendigen Standort. Als Mitglied können Sie Ihre Angebote sichtbar machen und sich mit anderen Unternehmen austauschen.",
        "style": "default",
        "position": 0
      },
      {
        "heading": null,
        "body": "Gemeinsamer Auftritt für die Unternehmen in Haselünne",
        "style": "feature",
        "position": 10
      },
      {
        "heading": null,
        "body": "Sichtbarkeit im Unternehmensverzeichnis",
        "style": "feature",
        "position": 20
      },
      {
        "heading": null,
        "body": "Veröffentlichung von Angeboten und Aktionen",
        "style": "feature",
        "position": 30
      },
      {
        "heading": null,
        "body": "Veranstaltungen bekannt machen",
        "style": "feature",
        "position": 40
      },
      {
        "heading": null,
        "body": "Stellenangebote veröffentlichen",
        "style": "feature",
        "position": 50
      },
      {
        "heading": null,
        "body": "Teilnahme an gemeinsamen Aktionen",
        "style": "feature",
        "position": 60
      },
      {
        "heading": null,
        "body": "Austausch und Vernetzung",
        "style": "feature",
        "position": 70
      },
      {
        "heading": null,
        "body": "Mitgestaltung des Standortes",
        "style": "feature",
        "position": 80
      }
    ]
  },
  {
    "code": "contact",
    "title": "Kontakt",
    "lead": null,
    "metaTitle": "Kontakt | {portal_name}",
    "metaDescription": "Kontakt zum Werbekreis Haselünne e.V.: Anschrift, Telefon und E-Mail für Ihre Fragen und Anliegen.",
    "sections": [
      {
        "heading": "Sprechen Sie uns an",
        "body": "Sie haben Fragen zum Werbekreis, möchten Mitglied werden oder eine Aktion mit uns besprechen? Sprechen Sie uns gerne an.",
        "style": "default",
        "position": 0
      }
    ]
  },
  {
    "code": "imprint",
    "title": "Impressum",
    "lead": null,
    "metaTitle": "Impressum | {portal_name}",
    "metaDescription": "Anbieter- und Kontaktinformationen des Werbekreises Haselünne e.V.",
    "sections": [
      {
        "heading": "Angaben gemäß den gesetzlichen Informationspflichten",
        "body": "{organization}\n{street}\n{postal_code} {city}",
        "style": "default",
        "position": 0
      },
      {
        "heading": "Vertreten durch",
        "body": "{contact_name}\n{contact_role}",
        "style": "default",
        "position": 10
      },
      {
        "heading": "Kontakt",
        "body": "Telefon: {contact_phone}\nE-Mail: {contact_email}",
        "style": "default",
        "position": 20
      },
      {
        "heading": null,
        "body": "Entwicklungshinweis: Registerangaben werden vor dem produktiven Start ergänzt.",
        "style": "notice",
        "position": 30
      }
    ]
  },
  {
    "code": "privacy",
    "title": "Datenschutzerklärung",
    "lead": null,
    "metaTitle": "Datenschutz | {portal_name}",
    "metaDescription": "Informationen zur Datenverarbeitung auf der Plattform des Werbekreises Haselünne: Sessions, Mitgliederbereich und Karten.",
    "sections": [
      {
        "heading": null,
        "body": "Diese Datenschutzerklärung bildet den aktuellen technischen Stand der Plattform ab und ist vor dem endgültigen Produktivstart rechtlich zu prüfen.",
        "style": "notice",
        "position": 0
      },
      {
        "heading": "1. Verantwortlicher",
        "body": "{organization}\n{street}\n{postal_code} {city}\nE-Mail: {contact_email}",
        "style": "default",
        "position": 10
      },
      {
        "heading": "2. Hosting und Server-Logfiles",
        "body": "Beim Aufruf dieser Website können technisch notwendige Verbindungsdaten verarbeitet werden, um die Inhalte bereitzustellen und den sicheren Betrieb zu unterstützen. Dazu können insbesondere IP-Adresse, Datum und Uhrzeit, aufgerufene URL, HTTP-Status und Browser beziehungsweise User-Agent gehören. Die konkrete Protokollierung und Aufbewahrung sind vor dem Produktivstart mit dem Hostinganbieter abzustimmen.",
        "style": "default",
        "position": 20
      },
      {
        "heading": "3. Technisch notwendige Cookies und Sessions",
        "body": "Die Anwendung verwendet Symfony-Sessions insbesondere für Login, Sicherheitsfunktionen, CSRF-Schutz und Sessionzustände. Dafür können technisch notwendige Session-Cookies eingesetzt werden. Im aktuellen Stand sind keine Marketing- oder Tracking-Cookies eingebaut.\n\nZusätzlich wird ausschließlich nach Klick auf „Verstanden“ im Cookie-Hinweis ein First-Party-Präferenz-Cookie gesetzt, damit der Hinweis nicht bei jedem Seitenaufruf erneut angezeigt wird.\n\nName: wk_cookie_notice\n\nZweck: Speichert ausschließlich, dass der Cookie-Hinweis bestätigt wurde.\n\nInhalt: 1; keine User-ID oder sonstigen personenbezogenen Inhalte.\n\nSpeicherdauer: 180 Tage\n\nWeitergabe: Keine Weitergabe an Dritte.",
        "style": "default",
        "position": 30
      },
      {
        "heading": "4. Mitgliederbereich",
        "body": "Bei der Nutzung eines Benutzerkontos werden die für das Konto gespeicherten Daten verarbeitet. Dazu gehören insbesondere Name, E-Mail-Adresse, Rollen und Berechtigungen sowie die Zuordnung zu Unternehmen, soweit vorhanden. Diese Daten dienen der Anmeldung und der Steuerung des Zugriffs auf die Funktionen des Mitgliederbereichs.",
        "style": "default",
        "position": 40
      },
      {
        "heading": "5. Karten",
        "body": "Die Plattform verwendet OpenStreetMap-Karten. Externe Kartenkacheln werden erst geladen, wenn Sie auf „Karte laden“ klicken. Dabei stellt Ihr Browser eine Verbindung zum Kartenanbieter OpenStreetMap her und übermittelt insbesondere Ihre IP-Adresse sowie die für den Abruf notwendigen Verbindungsdaten. Die Bestätigung des Cookie-Hinweises ist keine Zustimmung zum Laden der Karte. Der Klick auf „Karte laden“ bleibt eine separate bewusste Nutzeraktion. Ohne diesen Klick werden keine Kartenkacheln abgerufen. Die Informationen in den Ergebnislisten bleiben auch ohne geladene Karte verfügbar.",
        "style": "default",
        "position": 50
      },
      {
        "heading": "6. Kontakt per E-Mail oder Telefon",
        "body": "Wenn Sie uns per E-Mail oder Telefon kontaktieren, verwenden wir die von Ihnen übermittelten Daten zur Bearbeitung Ihrer Anfrage und für die dazugehörige Kommunikation.",
        "style": "default",
        "position": 60
      },
      {
        "heading": "7. Ihre Rechte",
        "body": "Sie haben nach Maßgabe der gesetzlichen Voraussetzungen das Recht auf Auskunft, Berichtigung, Löschung, Einschränkung der Verarbeitung, Widerspruch und Datenübertragbarkeit, soweit anwendbar. Zur Ausübung Ihrer Rechte können Sie sich an den oben genannten Verantwortlichen wenden. Außerdem haben Sie das Recht, sich bei einer Datenschutzaufsichtsbehörde zu beschweren.",
        "style": "default",
        "position": 70
      },
      {
        "heading": "8. Aktualisierung",
        "body": "Diese Erklärung wird bei Änderungen der Website und ihrer Datenverarbeitung angepasst.",
        "style": "default",
        "position": 80
      }
    ]
  }
]
JSON, true, 512, JSON_THROW_ON_ERROR);
        foreach ($pages as $page) {
            $this->addSql('INSERT INTO ContentPage (code, title, lead, metaTitle, metaDescription, createdAt, updatedAt) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)', [$page['code'], $page['title'], $page['lead'], $page['metaTitle'], $page['metaDescription']]);
            foreach ($page['sections'] as $section) {
                $this->addSql('INSERT INTO ContentPageSection (page_id, heading, body, position, style, createdAt, updatedAt) VALUES ((SELECT id FROM ContentPage WHERE code = ?), ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)', [$page['code'], $section['heading'], $section['body'], $section['position'], $section['style']]);
            }
        }

        $members = [
            ['Andreas Walburg', '1. Vorsitzender'],
            ['Norbert Schnakenberg', '2. Vorsitzender'],
            ['Daniel Pesch', 'Schriftführer'],
            ['Cornelia Sturm', 'Kassiererin'],
            ['Christopher Jansen', 'Vorstand'],
            ['Philipp Langen', 'Vorstand'],
            ['Robert Münster', 'Vorstand'],
            ['Ingo Hoffmeister', 'Vorstand'],
            ['Tanja Kramer', 'Werbung'],
        ];
        foreach ($members as $position => [$name, $role]) {
            $this->addSql('INSERT INTO BoardMember (name, role, position, active, createdAt, updatedAt) VALUES (?, ?, ?, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)', [$name, $role, $position * 10]);
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE ContentPageSection');
        $this->addSql('DROP TABLE ContentPage');
        $this->addSql('DROP TABLE BoardMember');
    }
}
