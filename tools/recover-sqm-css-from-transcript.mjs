import fs from 'fs';
import path from 'path';

const transcript = 'C:/Users/sshog/.cursor/projects/d-LocalSites-aidunite-local-app-public-wp-content-themes-aidunite-original/agent-transcripts/42577368-11cd-4e62-a458-a06f20f42f01/42577368-11cd-4e62-a458-a06f20f42f01.jsonl';
const lines = fs.readFileSync(transcript, 'utf8').split('\n');
const chunks = new Set();
for (const line of lines) {
  if (!line.includes('schedule-management-page.css') && !line.includes('aidunite-schedule-quick-modal')) continue;
  try {
    const obj = JSON.parse(line);
    const text = JSON.stringify(obj);
    const matches = text.match(/\.aidunite-schedule-quick-modal[\s\S]{0,8000}/g);
    if (matches) matches.forEach((m) => chunks.add(m.slice(0, 4000)));
  } catch (_) {}
}
const out = [...chunks].join('\n\n/* --- chunk --- */\n\n');
fs.writeFileSync('tools/recovered-sqm-chunks.txt', out, 'utf8');
console.log('chunks:', chunks.size, 'bytes:', out.length);
