// Mini serveur HTTP local (127.0.0.1) qui sert en Range les octets DÉJÀ
// écrits sur disque d'un téléchargement en cours. Le player lit ce fichier
// local en croissance — il n'y a jamais de second flux HTTP distant.
//
// Politique de taille (choix documenté dans le README) :
//  - fichier complet → Content-Range: bytes s-e/<totalSize> (vrai total) ;
//  - fichier partiel → Content-Range: bytes s-e/<écrit>, i.e. un fichier
//    valide qui grandit. On ne ment que si c'est auto-cohérent : le player
//    n'ouvre l'URL Range QUE quand la sonde mp4 a prouvé un moov complet
//    (faststart), donc la durée vient du moov, pas de cette taille.
//    Un seek au-delà des octets écrits est borné au disponible (jamais de
//    503 en lecture séquentielle) ; un start non disponible renvoie 503
//    après un temps d'attente borné.
//
// Écriture réseau : chunks lus en base64 depuis expo-file-system puis
// écrits avec socket.write(b64, "base64") → zéro décodage Uint8Array/Buffer
// côté JS (rapide, faible RAM).

const PORT = 18765;
const CHUNK = 256 * 1024;
const START_WAIT_MS = 8000;
const STREAM_STALL_MS = 15000;

let server = null;
let TcpSocket = null;
let FileSystem = null;

try {
  TcpSocket = require("react-native-tcp-socket");
} catch {
  TcpSocket = null;
}
try {
  FileSystem = require("expo-file-system");
} catch {
  FileSystem = null;
}

/** id -> { path, totalSize, getWritten, isComplete } */
const files = new Map();

export function registerLocalFile(id, { path, totalSize, getWritten, isComplete }) {
  files.set(id, { path, totalSize: totalSize || 0, getWritten, isComplete });
}

export function unregisterLocalFile(id) {
  files.delete(id);
}

export function getPlayUrl(id) {
  return `http://127.0.0.1:${PORT}/${encodeURIComponent(id)}`;
}

export function isRangeServerAvailable() {
  return Boolean(TcpSocket && FileSystem);
}

function wait(ms) {
  return new Promise((r) => setTimeout(r, ms));
}

async function writtenOf(entry) {
  try {
    return (await entry.getWritten()) || 0;
  } catch {
    return 0;
  }
}

async function isComplete(entry) {
  if (!entry.isComplete) return false;
  try {
    return Boolean(await entry.isComplete());
  } catch {
    return false;
  }
}

async function waitWritten(entry, need, timeoutMs) {
  const deadline = Date.now() + timeoutMs;
  let written = await writtenOf(entry);
  while (written < need) {
    if (await isComplete(entry)) break;
    if (Date.now() >= deadline) break;
    await wait(150);
    written = await writtenOf(entry);
  }
  return written;
}

function parseRequest(raw) {
  const [head] = raw.split("\r\n\r\n");
  const lines = head.split("\r\n");
  const [method, path] = (lines[0] || "").split(" ");
  const headers = {};
  for (const line of lines.slice(1)) {
    const i = line.indexOf(":");
    if (i > 0) headers[line.slice(0, i).toLowerCase()] = line.slice(i + 1).trim();
  }
  return { method, path, headers };
}

function respond(socket, status, extraHeaders = {}) {
  const lines = [`HTTP/1.1 ${status}`, "Content-Type: video/mp4", "Accept-Ranges: bytes"];
  for (const [k, v] of Object.entries(extraHeaders)) lines.push(`${k}: ${v}`);
  lines.push("Connection: close", "\r\n");
  socket.write(lines.join("\r\n"));
}

function fail(socket, status, text, retryAfter) {
  const body = `${text}\n`;
  const extra = { "Content-Length": String(body.length) };
  if (retryAfter) extra["Retry-After"] = String(retryAfter);
  respond(socket, status, extra);
  socket.write(body);
  socket.destroy();
}

async function readSliceBase64(path, start, length) {
  return FileSystem.readAsStringAsync(path, {
    encoding: FileSystem.EncodingType.Base64,
    position: start,
    length,
  });
}

async function serve(socket, req) {
  const id = decodeURIComponent((req.path || "/").replace(/^\//, "").split("?")[0]);
  const entry = files.get(id);
  if (!entry || !FileSystem) {
    fail(socket, "404 Not Found", "no such download");
    return;
  }

  const complete = await isComplete(entry);
  let written = await writtenOf(entry);

  const range = req.headers.range;
  let start = 0;
  let end = null; // inclusif ; null = jusqu'à dispo

  if (range) {
    const m = /bytes=(\d*)-(\d*)/.exec(range);
    if (m && (m[1] || m[2])) {
      if (m[1]) start = parseInt(m[1], 10);
      if (m[2]) end = parseInt(m[2], 10);
    }
  }

  if (!complete && start > 0) {
    // Seek vers une zone pas encore écrite : attente bornée puis 503.
    written = await waitWritten(entry, start + 1, START_WAIT_MS);
    if (written <= start) {
      fail(socket, "503 Service Unavailable", "bytes not ready yet", 1);
      return;
    }
  }

  if (end === null) {
    end = complete ? (entry.totalSize || written) - 1 : written - 1;
  } else if (end > written - 1) {
    // borne la fin aux octets disponibles (attente courte pour laisser le
    // téléchargement avancer pendant la requête)
    const need = Math.min(end + 1, start + 4 * CHUNK);
    written = await waitWritten(entry, need, STREAM_STALL_MS);
    end = Math.min(end, written - 1);
  }

  const length = end - start + 1;
  if (length <= 0) {
    fail(socket, "503 Service Unavailable", "bytes not ready yet", 1);
    return;
  }

  // Taille annoncée : vraie totale si complet, sinon « écrit » (fichier qui
  // grandit — voir note d'en-tête).
  const advertisedTotal = complete ? entry.totalSize || written : Math.max(written, end + 1);
  respond(socket, req.method === "HEAD" ? "200 OK" : "206 Partial Content", {
    "Content-Range": `bytes ${start}-${end}/${advertisedTotal}`,
    "Content-Length": req.method === "HEAD" ? String(Math.max(0, advertisedTotal - start)) : String(length),
  });
  if (req.method === "HEAD") {
    socket.destroy();
    return;
  }

  let offset = start;
  while (offset <= end) {
    const avail = await waitWritten(entry, offset + 1, complete ? 1000 : STREAM_STALL_MS);
    if (avail <= offset) break; // stall ou fin
    const take = Math.min(CHUNK, end - offset + 1, avail - offset);
    let b64;
    try {
      b64 = await readSliceBase64(entry.path, offset, take);
    } catch {
      break;
    }
    const flushed = socket.write(b64, "base64");
    if (!flushed) await wait(10); // back-pressure légère
    offset += take;
  }
  socket.end();
}

function handleSocket(socket) {
  let buf = "";
  let busy = false;
  socket.on("data", async (data) => {
    if (busy) return;
    buf += data.toString();
    if (!buf.includes("\r\n\r\n")) {
      if (buf.length > 32 * 1024) socket.destroy();
      return;
    }
    busy = true;
    const req = parseRequest(buf);
    buf = "";
    try {
      await serve(socket, req);
    } catch {
      try {
        socket.destroy();
      } catch {
        /* ignore */
      }
    }
  });
  socket.on("error", () => {});
}

export async function ensureRangeServer() {
  if (!TcpSocket || !FileSystem) return false;
  if (server) return true;
  return new Promise((resolve) => {
    try {
      server = TcpSocket.createServer(handleSocket);
      server.listen({ port: PORT, host: "127.0.0.1" }, () => resolve(true));
      server.on("error", () => {
        server = null;
        resolve(false);
      });
    } catch {
      resolve(false);
    }
  });
}
