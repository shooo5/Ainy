import fs from 'fs';

const transcript =
  'C:/Users/sshog/.cursor/projects/d-LocalSites-aidunite-local-app-public-wp-content-themes-aidunite-original/agent-transcripts/42577368-11cd-4e62-a458-a06f20f42f01/42577368-11cd-4e62-a458-a06f20f42f01.jsonl';

const blocks = [];
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
    const { path, new_string: ns } = part.input || {};
    if (!path?.includes('schedule-management-page.css') || !ns) continue;
    if (!ns.includes('.aidunite-schedule-quick-modal')) continue;
    blocks.push(ns);
  }
}

// Use the largest new_string block as base, then apply smaller patches... 
// Simpler: take last block that starts with comment or root selector and is > 5000 chars
const sorted = blocks.sort((a, b) => b.length - a.length);
console.log('blocks found:', blocks.length);
console.log('largest:', sorted[0]?.length || 0);
if (sorted[0]) {
  fs.writeFileSync('tools/largest-sqm-patch.txt', sorted[0], 'utf8');
}

// Also find block that contains the root rule definition
const withRoot = blocks.filter((b) => b.includes('.aidunite-schedule-quick-modal {'));
console.log('with root:', withRoot.length);
if (withRoot.length) {
  fs.writeFileSync('tools/sqm-root-patch.txt', withRoot[withRoot.length - 1], 'utf8');
}
