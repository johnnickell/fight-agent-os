import { createHash } from 'node:crypto';
import { readFile } from 'node:fs/promises';
import { basename } from 'node:path';
import ts from 'typescript';

const namespaceUrls = new Set([
  'http://www.w3.org/1999/xhtml',
  'http://www.w3.org/2000/svg',
  'http://www.w3.org/1998/Math/MathML',
  'http://www.w3.org/1999/xlink',
  'http://www.w3.org/XML/1998/namespace'
]);
const prohibitedPath =
  /(?:^|\/)(?:tests?|stories|fixtures?|prototypes?|\.storybook|\.runs|\.cache)(?:\/|\.)|\.(?:test|spec|stories|map)\.|(?:^|[/.])development(?:[/.]|$)/i;
const credential =
  /-----BEGIN [A-Z ]*PRIVATE KEY-----|\beyJ[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]+|\b(?:AKIA|ASIA)[A-Z0-9]{16}\b|\b(?:gh[pousr]_[A-Za-z0-9]{20,}|github_pat_[A-Za-z0-9_]{20,})\b/;
const privateConfiguration =
  /\b(?:APP_CSRF_MAC_KEY|DATABASE_URL|DB_PASSWORD|JWT_PRIVATE_KEY|JWT_SECRET|AWS_SECRET_ACCESS_KEY)\b|process\.env|import\.meta\.env/;
const privateKey =
  /^(?:access[_-]?token|refresh[_-]?token|api[_-]?key|client[_-]?secret|password|csrf[_-]?(?:proof|token)|private[_-]?key)$/i;

function reject(message) {
  // Never include a matched value in diagnostics: it may itself be a secret.
  throw new Error(`Production artifact policy: ${message}`);
}

function embeddedSvg(value) {
  if (!value.startsWith('data:image/svg+xml,')) return false;
  // CSS color percentages can remain literal in Bootstrap's data URLs.
  const svg = decodeURIComponent(
    value.slice('data:image/svg+xml,'.length).replace(/%(?![0-9a-f]{2})/gi, '%25')
  );
  return (
    svg.startsWith('<svg ') &&
    svg.endsWith('</svg>') &&
    !/(?:href\s*=|url\s*\(|<script|<foreignObject|\bon\w+\s*=)/i.test(svg) &&
    !/(?:https?:)?\/\//i.test(svg.replaceAll('http://www.w3.org/2000/svg', ''))
  );
}

function embeddedImport(entry) {
  return entry.kind === 'url-token' && embeddedSvg(entry.path);
}

function inspectJavascript(text, path) {
  const source = ts.createSourceFile(path, text, ts.ScriptTarget.ES2022, true, ts.ScriptKind.JS);
  function visit(node) {
    if (ts.isStringLiteralLike(node)) {
      const value = node.text;
      if (credential.test(value)) reject(`credential signature in ${path}`);
      // No baked-in principal/session identity; authority must arrive from the server at runtime.
      if (
        /\b[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\b/i.test(value)
      ) {
        reject(`embedded identity in ${path}`);
      }
      if (
        /(?:https?:)?\/\//i.test(value) &&
        !namespaceUrls.has(value) &&
        !value.startsWith('https://react.dev/errors/')
      ) {
        reject(`unapproved remote URL literal in ${path}`);
      }
    }
    if (
      ts.isPropertyAssignment(node) &&
      privateKey.test(node.name.getText(source).replace(/^['"]|['"]$/g, '')) &&
      ts.isStringLiteralLike(node.initializer) &&
      node.initializer.text.length > 0
    ) {
      reject(`literal credential assignment in ${path}`);
    }
    ts.forEachChild(node, visit);
  }
  visit(source);
}

/**
 * Inspects the actual build graph and bytes before publishing any assets
 */
export async function inspectArtifacts(metafile, files, styleInputs) {
  const lock = JSON.parse(await readFile('package-lock.json', 'utf8'));
  const inputs = [...new Set([...Object.keys(metafile.inputs), ...styleInputs])].sort();
  for (const path of inputs) {
    if (prohibitedPath.test(path)) reject(`development/private input ${path}`);
    if (path.startsWith('node_modules/')) {
      const packagePath = path.match(/^node_modules\/(?:@[^/]+\/)?[^/]+/)?.[0];
      const dependency = lock.packages[packagePath];
      if (!dependency || dependency.dev || dependency.link)
        reject(`non-runtime dependency ${path}`);
    } else if (!/^(?:src\/.*\.(?:ts|tsx)|styles\/.*\.scss)$/.test(path)) {
      reject(`input outside production roots ${path}`);
    }
  }
  for (const input of Object.values(metafile.inputs)) {
    if (input.imports.some((entry) => entry.external && !embeddedImport(entry)))
      reject('external source import');
  }
  const outputs = [];
  for (const file of files) {
    const name = basename(file.path);
    if (!/^(?:main|chunk)-[A-Z0-9]{8}\.(?:js|css)(?:\.LEGAL\.txt)?$/.test(name)) {
      reject(`unexpected output ${name}`);
    }
    const text = file.text;
    if (/sourceMappingURL|sourceURL/.test(text)) reject(`source map/reference in ${name}`);
    if (credential.test(text) || privateConfiguration.test(text))
      reject(`private content in ${name}`);
    if (name.endsWith('.js')) inspectJavascript(text, name);
    if (name.endsWith('.css')) {
      if (/@import\b/i.test(text)) reject(`unbundled stylesheet import in ${name}`);
      for (const match of text.matchAll(/url\(\s*(['"]?)(.*?)\1\s*\)/gi)) {
        // Bootstrap's compiled, self-contained SVG controls are the only current CSS assets.
        if (!embeddedSvg(match[2])) reject(`non-embedded CSS resource in ${name}`);
      }
    }
    outputs.push({
      name,
      bytes: file.contents.byteLength,
      sha256: createHash('sha256').update(file.contents).digest('hex')
    });
  }
  for (const output of Object.values(metafile.outputs)) {
    for (const entry of output.imports) {
      if (
        !embeddedImport(entry) &&
        (entry.external || !Object.hasOwn(metafile.outputs, entry.path))
      )
        reject('external or missing output import');
    }
  }
  const report = {
    inputs,
    outputs: outputs.sort((a, b) => a.name.localeCompare(b.name)),
    sourceMaps: false
  };
  console.log(
    `Production artifact policy passed: ${inputs.length} inputs, ${outputs.length} files, ${outputs.reduce((total, file) => total + file.bytes, 0)} bytes; no source maps`
  );
  return report;
}
