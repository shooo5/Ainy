import fs from 'fs';

const transcript =
  'C:/Users/sshog/.cursor/projects/d-LocalSites-aidunite-local-app-public-wp-content-themes-aidunite-original/agent-transcripts/42577368-11cd-4e62-a458-a06f20f42f01/42577368-11cd-4e62-a458-a06f20f42f01.jsonl';

let i = 0;
for (const line of fs.readFileSync(transcript, 'utf8').split('\n')) {
  if (!line.includes('schedule-management-page.css')) continue;
  let obj;
  try {
    obj = JSON.parse(line);
  } catch {
    continue;
  }
  for (const part of obj.message?.content || []) {
    if (part.type !== 'tool_use' || part.name !== 'StrReplace') continue;
    const input = part.input || {};
    if (!input.path?.includes('schedule-management-page.css')) continue;
    i++;
    const ns = input.new_string || '';
    const os = input.old_string || '';
    console.log('--- patch', i, 'new len', ns.length, 'old len', os.length);
    if (ns.includes('.aidunite-schedule-quick-modal {')) {
      console.log('HAS ROOT in new_string');
      fs.writeFileSync(`tools/patch-${i}-new.txt`, ns, 'utf8');
    }
    if (os.includes('.aidunite-schedule-quick-modal {')) {
      console.log('HAS ROOT in old_string');
    }
    if (ns.length > 3000) {
      fs.writeFileSync(`tools/patch-${i}-large-new.txt`, ns, 'utf8');
    }
  }
}
