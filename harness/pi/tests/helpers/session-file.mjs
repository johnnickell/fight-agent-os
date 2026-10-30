import fs from 'node:fs';
import { syncBuiltinESMExports } from 'node:module';

// Narrow owning seam: substitute native stat evidence, never the production witness or session content.
export function fileStats(t, alter) {
  for (const name of ['lstatSync', 'fstatSync']) {
    const original = fs[name];
    t.mock.method(fs, name, (...args) => {
      const info = original(...args);
      return info.isFile() ? Object.assign(Object.create(Object.getPrototypeOf(info)), info, alter(info)) : info;
    });
  }
  syncBuiltinESMExports();
  t.after(() => { t.mock.restoreAll(); syncBuiltinESMExports(); });
}
export function recycledInode(t) {
  fileStats(t, info => typeof info.ino === 'bigint' ? { dev: 66305n, ino: 9699377n } : { dev: 66305, ino: 9699377 });
}
