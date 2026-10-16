<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
/** Immutable data snapshot. Existing editorial records are copied from the database, never reseeded. */
final class Version20261016090000 extends AbstractMigration
{
    public function getDescription(): string { return 'Modular CMS, secure content media, lossless legacy text conversion and editable homepage'; }
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ContentPage ADD showDefaultHeader TINYINT(1) DEFAULT 1 NOT NULL');
        $this->addSql('CREATE TABLE ContentElement (id INT AUTO_INCREMENT NOT NULL, page_id INT NOT NULL, linkedEvent_id INT DEFAULT NULL, linkedCompany_id INT DEFAULT NULL, type VARCHAR(30) NOT NULL, position INT NOT NULL, active TINYINT(1) NOT NULL, anchor VARCHAR(100) DEFAULT NULL, data JSON NOT NULL, createdAt DATETIME NOT NULL, updatedAt DATETIME NOT NULL, INDEX content_element_order (page_id, position, id), UNIQUE INDEX content_element_anchor (page_id, anchor), INDEX IDX_D29C885297815337 (linkedEvent_id), INDEX IDX_D29C885283587710 (linkedCompany_id), PRIMARY KEY(id), FOREIGN KEY (page_id) REFERENCES ContentPage(id) ON DELETE CASCADE, FOREIGN KEY (linkedEvent_id) REFERENCES Event(id) ON DELETE SET NULL, FOREIGN KEY (linkedCompany_id) REFERENCES Company(id) ON DELETE SET NULL) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE ContentMedia (id INT AUTO_INCREMENT NOT NULL, element_id INT NOT NULL, fileName VARCHAR(40) NOT NULL, originalName VARCHAR(255) NOT NULL, mimeType VARCHAR(30) NOT NULL, altText VARCHAR(255) NOT NULL, caption LONGTEXT DEFAULT NULL, position INT NOT NULL, decorative TINYINT(1) NOT NULL, createdAt DATETIME NOT NULL, INDEX IDX_239BC82E1F1F2A24 (element_id), PRIMARY KEY(id), FOREIGN KEY (element_id) REFERENCES ContentElement(id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql("INSERT INTO ContentElement (page_id,type,position,active,data,createdAt,updatedAt) SELECT id,'portal_contact',0,1,JSON_OBJECT('showAddress',true,'showPerson',true,'showPhone',true,'showEmail',true),CURRENT_TIMESTAMP,CURRENT_TIMESTAMP FROM ContentPage WHERE code='contact'");
        $this->addSql("INSERT INTO ContentElement (page_id, type, position, active, data, createdAt, updatedAt) SELECT page_id, 'text', position, 1, JSON_OBJECT('heading', COALESCE(heading, ''), 'body', body, 'variant', CASE style WHEN 'feature' THEN 'card' WHEN 'notice' THEN 'notice' ELSE 'default' END), createdAt, updatedAt FROM ContentPageSection ORDER BY page_id, position, id");
        // Keep the old table as an archival source during rollout. It is no longer edited or rendered.
        $this->addSql("INSERT INTO ContentPage (code,title,lead,metaTitle,metaDescription,showDefaultHeader,createdAt,updatedAt) SELECT 'home','Haselünne hat mehr zu bieten.',NULL,'{portal_name} | Einkaufen. Genießen. Erleben.','Entdecken Sie lokale Unternehmen, Angebote und Veranstaltungen in Haselünne.',0,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP WHERE NOT EXISTS (SELECT 1 FROM ContentPage WHERE code = 'home')");
        $home = json_decode(<<<'JSON'
[
  [
    "hero",
    null,
    {
      "eyebrow": "EINKAUFEN. GENIESSEN. ERLEBEN.",
      "heading": "{city} hat mehr zu bieten.",
      "text": "Entdecken Sie lokale Geschäfte, Gastronomie, Dienstleistungen und Veranstaltungen direkt vor Ihrer Haustür.",
      "buttonLabel": "",
      "buttonUrl": "",
      "showCompanySearch": true,
      "showPopularCategories": true,
      "searchPlaceholder": "z. B. Schuhe, Café, Friseur",
      "asideHeading": "Lokal.\nLebendig.\nZusammen.",
      "asideText": "Das Beste liegt oft ganz nah.",
      "variant": "default"
    }
  ],
  [
    "categories",
    "entdecken",
    {
      "heading": "Was möchten Sie entdecken?",
      "subheading": "Vielfalt direkt vor der Haustür",
      "limit": 0
    }
  ],
  [
    "offers",
    "angebote",
    {
      "heading": "Angebote & Aktionen",
      "limit": 3,
      "showAllLink": true
    }
  ],
  [
    "news",
    "aktuelles",
    {
      "heading": "Neuigkeiten von hier.",
      "limit": 3,
      "showAllLink": true
    }
  ],
  [
    "event_list",
    "veranstaltungen",
    {
      "heading": "Gemeinsam etwas erleben.",
      "limit": 3,
      "showAllLink": true
    }
  ],
  [
    "company_list",
    "unternehmen",
    {
      "heading": "Unternehmen entdecken",
      "subheading": "Menschen und Ideen von hier",
      "limit": 6,
      "showAllLink": true
    }
  ],
  [
    "voucher",
    "gutschein",
    {
      "eyebrow": "FREUDE SCHENKEN. REGIONAL DENKEN.",
      "heading": "Der Haselünner Gutschein",
      "text": "Eine Idee, viele Möglichkeiten: regional schenken und lokale Unternehmen unterstützen.",
      "buttonLabel": "Gutschein entdecken"
    }
  ],
  [
    "jobs",
    "jobs",
    {
      "heading": "Ihre Zukunft liegt vor Ort.",
      "limit": 3,
      "showAllLink": true
    }
  ],
  [
    "cta",
    "mitglied",
    {
      "eyebrow": "WERBEKREIS {city}",
      "heading": "Gemeinsam ist hier mehr möglich.",
      "text": "Stärken Sie mit uns den Standort {city}. Werden Sie Teil eines Netzwerks, das vor Ort etwas bewegt.",
      "buttonLabel": "Mitglied werden",
      "buttonUrl": "/mitglied-werden",
      "variant": "green"
    }
  ]
]
JSON, true, 512, JSON_THROW_ON_ERROR);
        foreach ($home as $position => [$type, $anchor, $data]) {
            $this->addSql('INSERT INTO ContentElement (page_id,type,position,active,anchor,data,createdAt,updatedAt) SELECT id,?,?,1,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP FROM ContentPage WHERE code = ?', [$type, $position, $anchor, json_encode($data, JSON_THROW_ON_ERROR), 'home']);
        }
        $extras = json_decode(<<<'JSON'
{
  "about": [
    [
      "cta",
      {
        "eyebrow": "",
        "heading": "",
        "text": "",
        "buttonLabel": "Mitglied im Werbekreis werden",
        "buttonUrl": "/mitglied-werden",
        "variant": "light"
      }
    ],
    [
      "cta",
      {
        "eyebrow": "",
        "heading": "",
        "text": "",
        "buttonLabel": "Unser Vorstand",
        "buttonUrl": "/werbekreis/vorstand",
        "variant": "light"
      }
    ]
  ],
  "board": [
    [
      "board_list",
      {}
    ],
    [
      "cta",
      {
        "eyebrow": "",
        "heading": "",
        "text": "",
        "buttonLabel": "Kontakt zum Werbekreis",
        "buttonUrl": "/kontakt",
        "variant": "light"
      }
    ]
  ],
  "membership": [
    [
      "cta",
      {
        "eyebrow": "",
        "heading": "Interesse an einer Mitgliedschaft?",
        "text": "Wir informieren Sie gerne persönlich über die Mitgliedschaft und die nächsten Schritte.",
        "buttonLabel": "Zur Kontaktseite",
        "buttonUrl": "/kontakt",
        "variant": "light"
      }
    ],
    [
      "portal_contact",
      {
        "showAddress": true,
        "showPerson": true,
        "showPhone": true,
        "showEmail": true
      }
    ]
  ],
  "contact": [
    [
      "portal_contact",
      {
        "showAddress": true,
        "showPerson": true,
        "showPhone": true,
        "showEmail": true
      }
    ]
  ],
  "imprint": [
    [
      "portal_contact",
      {
        "showAddress": true,
        "showPerson": true,
        "showPhone": true,
        "showEmail": true
      }
    ]
  ]
}
JSON, true, 512, JSON_THROW_ON_ERROR);
        foreach ($extras as $code => $elements) {
            if ($code === 'contact') { continue; }
            foreach ($elements as $offset => [$type, $data]) {
                // A separate subquery avoids reading the target table from a MySQL INSERT expression.
                $this->addSql('INSERT INTO ContentElement (page_id,type,position,active,data,createdAt,updatedAt) SELECT p.id,?,COALESCE(s.maxPosition,-1)+?,1,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP FROM ContentPage p LEFT JOIN (SELECT page_id,MAX(position) maxPosition FROM ContentPageSection GROUP BY page_id) s ON s.page_id=p.id WHERE p.code=?', [$type, 1 + $offset, json_encode($data, JSON_THROW_ON_ERROR), $code]);
            }
        }
    }
    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('CMS edits and uploaded media must be preserved; restore a database backup to roll back.');
    }
}
