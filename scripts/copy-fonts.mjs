import { cpSync, mkdirSync } from 'node:fs';
mkdirSync('assets/generated/webfonts', { recursive: true });
cpSync('node_modules/@fortawesome/fontawesome-free/webfonts', 'assets/generated/webfonts', { recursive: true });

// Leaflet's BSD-2-Clause license is retained in the installed package.
mkdirSync('assets/generated/images', { recursive: true });
cpSync('node_modules/leaflet/dist/images', 'assets/generated/images', { recursive: true });
cpSync('node_modules/leaflet/LICENSE', 'assets/generated/leaflet-LICENSE.txt');
