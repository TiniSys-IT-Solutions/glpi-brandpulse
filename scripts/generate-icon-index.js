#!/usr/bin/env node

const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '../public/icons/pulse');
const entries = [];

function walk(directory) {
  for (const dirent of fs.readdirSync(directory, { withFileTypes: true })) {
    const fullPath = path.join(directory, dirent.name);
    if (dirent.isDirectory()) {
      walk(fullPath);
      continue;
    }

    if (!dirent.isFile() || !dirent.name.toLowerCase().endsWith('.svg')) {
      continue;
    }

    const relativePath = path.relative(root, fullPath).split(path.sep).join('/');
    const parts = relativePath.split('/');
    const filename = parts.pop();
    const category = parts.join(' / ') || 'General';
    const label = filename.replace(/\.svg$/i, '');

    entries.push({
      p: relativePath,
      l: label,
      c: category,
      s: [relativePath, label, category].join(' ').toLowerCase(),
    });
  }
}

walk(root);
entries.sort((left, right) => (
  left.c.localeCompare(right.c, 'en', { sensitivity: 'base' })
  || left.l.localeCompare(right.l, 'en', { numeric: true, sensitivity: 'base' })
  || left.p.localeCompare(right.p, 'en', { numeric: true, sensitivity: 'base' })
));

fs.writeFileSync(path.join(root, 'index.json'), JSON.stringify({
  count: entries.length,
  icons: entries,
}));
console.log(`Generated ${entries.length} Pulse icons.`);
