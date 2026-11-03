#!/usr/bin/env node
/**
 * Tailwind v4 emits @layer / @property. Old Smart TV browsers (Tizen /
 * Chromium <99) discard unknown at-rule blocks entirely, so the whole
 * stylesheet looks like "no CSS". Flatten layers and drop @property.
 */
import fs from 'node:fs';

const file = process.argv[2];
if (!file) {
  console.error('Usage: node scripts/unwrap-css-layers.mjs <file.css>');
  process.exit(1);
}

let css = fs.readFileSync(file, 'utf8');

function unwrapAtRule(input, name) {
  const needle = `@${name}`;
  let out = '';
  let i = 0;
  while (i < input.length) {
    const start = input.indexOf(needle, i);
    if (start === -1) {
      out += input.slice(i);
      break;
    }
    out += input.slice(i, start);
    let j = start + needle.length;
    while (j < input.length && /\s/.test(input[j])) j++;
    // optional identifier / prelude until `{`
    while (j < input.length && input[j] !== '{' && input[j] !== ';') j++;
    if (input[j] === ';') {
      // e.g. @layer foo, bar;
      i = j + 1;
      continue;
    }
    if (input[j] !== '{') {
      out += input.slice(start, j);
      i = j;
      continue;
    }
    let depth = 0;
    let k = j;
    for (; k < input.length; k++) {
      const ch = input[k];
      if (ch === '{') depth++;
      else if (ch === '}') {
        depth--;
        if (depth === 0) {
          k++;
          break;
        }
      }
    }
    const body = input.slice(j + 1, k - 1);
    // Recurse in case of nested layers
    out += unwrapAtRule(body, name);
    i = k;
  }
  return out;
}

css = unwrapAtRule(css, 'layer');
css = unwrapAtRule(css, 'property');
// Collapse leftover blank runs from removed wrappers
css = css.replace(/\n{3,}/g, '\n\n').trim() + '\n';

fs.writeFileSync(file, css);
console.log(`unwrapped CSS layers: ${file} (${css.length} bytes)`);
