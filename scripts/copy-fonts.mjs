import { cpSync, mkdirSync } from 'node:fs';
mkdirSync('assets/generated/webfonts', { recursive: true });
cpSync('node_modules/@fortawesome/fontawesome-free/webfonts', 'assets/generated/webfonts', { recursive: true });
