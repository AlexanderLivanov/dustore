/* ============================================================
 * MINI Server · config.js
 * Конфигурация: переменные окружения имеют приоритет над
 * config.json. Секреты в git не попадают (config.json в .gitignore).
 * ============================================================ */

const fs = require('fs');
const path = require('path');

let fileCfg = {};
const cfgPath = path.join(__dirname, 'config.json');
if (fs.existsSync(cfgPath)) {
  fileCfg = JSON.parse(fs.readFileSync(cfgPath, 'utf8'));
}

const cfg = {
  CLIENT_ID: process.env.MINI_CLIENT_ID || fileCfg.CLIENT_ID || '',
  CLIENT_SECRET: process.env.MINI_CLIENT_SECRET || fileCfg.CLIENT_SECRET || '',
  BASE_URL: (process.env.MINI_BASE_URL || fileCfg.BASE_URL || 'http://localhost:3000').replace(/\/$/, ''),
  PORT: Number(process.env.MINI_PORT || fileCfg.PORT || 3000),
  RELAY_INTERVAL_MS: Number(process.env.MINI_RELAY_INTERVAL || fileCfg.RELAY_INTERVAL_MS || 20000),
  MAX_ENVELOPE_BYTES: Number(fileCfg.MAX_ENVELOPE_BYTES || 256 * 1024),
};

function assertConfigured() {
  const missing = [];
  if (!cfg.CLIENT_ID) missing.push('CLIENT_ID');
  if (!cfg.CLIENT_SECRET) missing.push('CLIENT_SECRET');
  if (missing.length) {
    console.error(
      `MINI Server: не задано ${missing.join(', ')}.\n` +
      'Скопируйте config.example.json → config.json и заполните,\n' +
      'либо задайте MINI_CLIENT_ID / MINI_CLIENT_SECRET в окружении.');
    process.exit(1);
  }
}

module.exports = Object.assign(cfg, { assertConfigured });
