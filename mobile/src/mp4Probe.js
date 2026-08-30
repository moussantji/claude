// Sonde la structure de boîtes MP4 d'un fichier PARTIEL (les octets déjà
// téléchargés) pour décider si la lecture progressive est possible.
//
// Règle produit documentée (voir README) :
//  - si `moov` est entièrement présent AVANT `mdat` (faststart) → jouable
//    en progressif via le serveur Range local ;
//  - si `mdat` arrive avant `moov` (moov en fin de fichier, cas classique)
//    → on attend le fichier complet avant d'ouvrir le player.

const HEADER = 8;
const MAX_PROBE_BYTES = 8 * 1024 * 1024;
const WINDOW = 512 * 1024;

function typeFromBytes(buf, at) {
  return String.fromCharCode(buf[at], buf[at + 1], buf[at + 2], buf[at + 3]);
}

async function FileSystemMod() {
  return require("expo-file-system");
}

async function readWindow(FileSystem, path, start, length) {
  const b64 = await FileSystem.readAsStringAsync(path, {
    encoding: FileSystem.EncodingType.Base64,
    position: start,
    length,
  });
  // global.atob existe dans Hermes (RN >= 0.74).
  if (typeof global.atob !== "function") return null;
  const bin = global.atob(b64);
  const out = new Uint8Array(bin.length);
  for (let i = 0; i < bin.length; i += 1) out[i] = bin.charCodeAt(i);
  return out;
}

/**
 * @returns {"faststart" | "tail" | "undetermined"}
 */
export async function probeMoovPosition(path, writtenBytes) {
  if (!path || !writtenBytes || writtenBytes < HEADER + 8) return "undetermined";
  let FileSystem;
  try {
    FileSystem = await FileSystemMod();
  } catch {
    return "undetermined";
  }

  let buffer = null;
  let buffered = 0; // octets valides dans `buffer` depuis l'offset 0 du fichier

  async function ensure(need) {
    if (need <= buffered) return true;
    if (need > Math.min(writtenBytes, MAX_PROBE_BYTES)) return false;
    const target = Math.min(Math.max(need, buffered + WINDOW), writtenBytes, MAX_PROBE_BYTES);
    const win = await readWindow(FileSystem, path, buffered, target - buffered);
    if (!win) return false;
    const merged = new Uint8Array(buffered + win.length);
    if (buffer) merged.set(buffer.subarray(0, buffered), 0);
    merged.set(win, buffered);
    buffer = merged;
    buffered += win.length;
    return need <= buffered;
  }

  let offset = 0;
  while (offset < Math.min(writtenBytes, MAX_PROBE_BYTES)) {
    if (!(await ensure(offset + HEADER))) return "undetermined";
    let size = 0;
    for (let i = 0; i < 4; i += 1) size = size * 256 + buffer[offset + i];
    const typeAt = offset + 4;
    let headerSize = HEADER;
    if (size === 1) {
      // largesize 64 bits
      if (!(await ensure(offset + 16))) return "undetermined";
      size = 0;
      for (let i = 8; i < 16; i += 1) size = size * 256 + buffer[offset + i];
      headerSize = 16;
    }
    const type = typeFromBytes(buffer, typeAt);
    if (type === "mdat" && size !== 0) {
      // mdat rencontré sans moov avant → moov à la fin
      return "tail";
    }
    if (size === 0) {
      // boîte jusqu'à EOF : ne peut pas être un moov exploitable en partiel
      return "undetermined";
    }
    if (size < headerSize) return "undetermined"; // fichier corrompu/tronqué
    if (type === "moov") {
      const moovEnd = offset + size;
      if (writtenBytes >= totalNeededForProbe(moovEnd)) return "faststart";
      return "undetermined"; // moov entamé mais pas complet
    }
    offset += size;
  }
  return "undetermined";
}

function totalNeededForProbe(moovEnd) {
  // marge : on veut aussi les premiers octets après moov pour que ExoPlayer
  // puisse lire le début de mdat.
  return moovEnd + 16 * 1024;
}
