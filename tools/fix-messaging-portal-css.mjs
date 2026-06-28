import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const file = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../assets/css/pages/messaging.css');
let css = fs.readFileSync(file, 'utf8');

/** シェル外（FAB・モーダル）— page-communication-main の子ではない */
const portalRoots = [
  'new-message-btn-fixed-wrap',
  'new-message-btn--fab',
  'new-message-btn',
  'fab-menu',
  'fab-menu-item',
  'fab-menu-item-icon',
  'start-chat-modal',
  'start-chat-modal-body',
  'start-chat-modal-description',
  'start-chat-members-wrap',
  'start-chat-member-list',
  'start-chat-loading',
  'start-chat-title-wrap',
  'start-chat-actions',
  'message-modal',
  'message-form',
];

for (const root of portalRoots) {
  const re = new RegExp(`\\.page-communication-main (\\.(?:[\\w-]+\\s+)*${root.replace(/-/g, '\\-')})`, 'g');
  css = css.replace(re, '$1');
}

fs.writeFileSync(file, css, 'utf8');
console.log('Fixed portal selectors in messaging.css');
