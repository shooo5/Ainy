import fs from 'fs';
const line = fs.readFileSync(
  'C:/Users/sshog/.cursor/projects/d-LocalSites-aidunite-local-app-public-wp-content-themes-aidunite-original/agent-transcripts/42577368-11cd-4e62-a458-a06f20f42f01/42577368-11cd-4e62-a458-a06f20f42f01.jsonl',
  'utf8'
).split('\n').find((l) => l.includes('schedule-management-page.css') && l.includes('クイック登録'));
const obj = JSON.parse(line);
const p = obj.message.content.find((x) => x.name === 'StrReplace' && x.input.path.includes('schedule-management-page.css'));
console.log('OLD:\n', JSON.stringify(p.input.old_string));
console.log('---');
console.log('NEW start:\n', p.input.new_string.slice(0, 200));
