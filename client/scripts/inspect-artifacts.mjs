import { createHash } from 'node:crypto';
import { readFile } from 'node:fs/promises';
import { basename } from 'node:path';
import ts from 'typescript';

const namespaceUrls = new Set([
  // URL-grammar delimiter used to reject protocol-relative input; not a resource destination.
  '//',
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

// Exact immutable raster resources shipped by the qualified Swagger 5.34.0 stylesheet.
// These are embedded pixels, not permission for files, arbitrary data URLs or network resources.
const swaggerRasterHashes = new Set([
  '2e5864493d85e06550ffd34887b3d9b410ad233919d745fe5f16a19b0f1bd42f',
  '96453982bf221219c8e81eb30a7f7cb44f9ba39e658c2f97d56fdfbe9b1a3d37',
  '1f6e5d271646bea5500fca01a74b3575cfa268b866aab636c4b867f1f03858e7',
  '2f212be93fa76facd4848d84646aed339ceeaf0fe4a609550c736b5482dbfa47'
]);
function embeddedRaster(value) {
  return swaggerRasterHashes.has(createHash('sha256').update(value).digest('hex'));
}

function embeddedSvg(value) {
  const prefix = value.startsWith('data:image/svg+xml;charset=utf-8,')
    ? 'data:image/svg+xml;charset=utf-8,'
    : 'data:image/svg+xml,';
  if (!value.startsWith(prefix)) return false;
  // CSS color percentages can remain literal in Bootstrap's data URLs.
  const svg = decodeURIComponent(value.slice(prefix.length).replace(/%(?![0-9a-f]{2})/gi, '%25'));
  return (
    svg.startsWith('<svg ') &&
    svg.endsWith('</svg>') &&
    !/(?:href\s*=|url\s*\(|<script|<foreignObject|\bon\w+\s*=)/i.test(svg) &&
    !/(?:https?:)?\/\//i.test(svg.replaceAll('http://www.w3.org/2000/svg', ''))
  );
}

function embeddedImport(entry) {
  return entry.kind === 'url-token' && (embeddedSvg(entry.path) || embeddedRaster(entry.path));
}

function inspectJavascript(text, path, qualifiedLiterals = new Set()) {
  const source = ts.createSourceFile(path, text, ts.ScriptTarget.ES2022, true, ts.ScriptKind.JS);
  function visit(node) {
    if (ts.isStringLiteralLike(node)) {
      const value = node.text;
      if (credential.test(value)) reject(`credential signature in ${path}`);
      // No baked-in principal/session identity; authority must arrive from the server at runtime.
      if (
        /\b[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\b/i.test(
          value
        ) &&
        !qualifiedLiterals.has(value)
      ) {
        reject(`embedded identity in ${path}`);
      }
      if (
        /(?:https?:)?\/\//i.test(value) &&
        !namespaceUrls.has(value) &&
        !qualifiedLiterals.has(value) &&
        !value.startsWith('https://react.dev/errors/') &&
        // Formik's locked runtime hoisting dependency retains this static PropTypes diagnostic.
        // Exact text only: it is not a resource URL or permission for arbitrary documentation links.
        value !==
          'Calling PropTypes validators directly is not supported by the `prop-types` package. Use PropTypes.checkPropTypes() to call them. Read more at http://fb.me/use-check-prop-types'
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
  const fontInventory = JSON.parse(await readFile('assets/fonts/checksums.json', 'utf8'));
  const fonts = new Map(fontInventory.map((font) => [`assets/fonts/${font.file}`, font.sha256]));
  for (const [path, sha256] of fonts) {
    if (
      createHash('sha256')
        .update(await readFile(path))
        .digest('hex') !== sha256
    )
      reject(`font checksum mismatch in ${path}`);
  }
  const inputs = [...new Set([...Object.keys(metafile.inputs), ...styleInputs])].sort();
  const qualifiedLiterals = new Set();
  if (inputs.includes('node_modules/swagger-ui-dist/swagger-ui-bundle.js')) {
    // Qualification covers these exact upstream bytes, including inert grammar/schema/diagnostic
    // URLs, disabled validator/social-link defaults and the upstream UUID example. This is not a
    // package-name exemption: a dependency change requires new source and browser qualification.
    const vendor = await readFile('node_modules/swagger-ui-dist/swagger-ui-bundle.js', 'utf8');
    const css = await readFile('node_modules/swagger-ui-dist/swagger-ui.css');
    if (
      createHash('sha256').update(vendor).digest('hex') !==
        '3f6abf70480db7e07914df4c98a77385f22ea585eaf457a3e6415ba0eae2d167' ||
      createHash('sha256').update(css).digest('hex') !==
        'c3cfe8248c4b0b89673600fb829924eafbac6de4bc4b2b06f5f5243483074d0a'
    ) {
      reject('unqualified Swagger distribution');
    }
    const source = ts.createSourceFile(
      'swagger.js',
      vendor,
      ts.ScriptTarget.ES2022,
      true,
      ts.ScriptKind.JS
    );
    const visit = (node) => {
      if (
        ts.isStringLiteralLike(node) &&
        (/(?:https?:)?\/\//i.test(node.text) ||
          node.text === '3fa85f64-5717-4562-b3fc-2c963f66afa6')
      )
        qualifiedLiterals.add(node.text);
      ts.forEachChild(node, visit);
    };
    visit(source);
  }
  for (const path of inputs) {
    if (prohibitedPath.test(path)) reject(`development/private input ${path}`);
    if (path.startsWith('node_modules/')) {
      const packagePath = path.match(/^node_modules\/(?:@[^/]+\/)?[^/]+/)?.[0];
      const dependency = lock.packages[packagePath];
      if (!dependency || dependency.dev || dependency.link)
        reject(`non-runtime dependency ${path}`);
    } else if (!fonts.has(path) && !/^(?:src\/.*\.(?:ts|tsx)|styles\/.*\.scss)$/.test(path)) {
      reject(`input outside production roots ${path}`);
    }
    // An upstream literal exception cannot be reused by authored code to embed a URL or identity.
    if (/^src\/.*\.(?:ts|tsx)$/.test(path)) inspectJavascript(await readFile(path, 'utf8'), path);
  }
  for (const input of Object.values(metafile.inputs)) {
    if (input.imports.some((entry) => entry.external && !embeddedImport(entry)))
      reject('external source import');
  }
  const outputs = [];
  const fontOutput =
    /^(?:ubuntu|open-sans)-(?:latin|latin-ext|greek|greek-ext|cyrillic|cyrillic-ext)-(?:400|500|600|700)-normal-[A-Z0-9]{8}\.woff2$/;
  const emittedFonts = new Set(
    files.filter((file) => fontOutput.test(basename(file.path))).map((file) => basename(file.path))
  );
  for (const file of files) {
    const name = basename(file.path);
    const sha256 = createHash('sha256').update(file.contents).digest('hex');
    if (fontOutput.test(name)) {
      if (!fontInventory.some((font) => font.sha256 === sha256)) reject(`unqualified font ${name}`);
      outputs.push({ name, bytes: file.contents.byteLength, sha256 });
      continue;
    }
    if (
      !/^(?:main|chunk|prepaint)-[A-Z0-9]{8}\.(?:js|css)(?:\.LEGAL\.txt)?$/.test(name) &&
      !/^(?:ubuntu|open-sans)-[A-Z0-9]{8}\.LEGAL\.txt$/.test(name)
    ) {
      reject(`unexpected output ${name}`);
    }
    const text = file.text;
    if (/sourceMappingURL|sourceURL/.test(text)) reject(`source map/reference in ${name}`);
    if (credential.test(text) || privateConfiguration.test(text))
      reject(`private content in ${name}`);
    if (name.endsWith('.js')) inspectJavascript(text, name, qualifiedLiterals);
    if (name.endsWith('.css')) {
      if (/@import\b/i.test(text)) reject(`unbundled stylesheet import in ${name}`);
      for (const match of text.matchAll(/url\(\s*(['"]?)(.*?)\1\s*\)/gi)) {
        // Only screened SVG, exact qualified embedded pixels and this build's pinned fonts are allowed.
        if (
          !embeddedSvg(match[2]) &&
          !embeddedRaster(match[2]) &&
          !emittedFonts.has(match[2].replace(/^\.\//, ''))
        )
          reject(`unapproved CSS resource in ${name}`);
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
